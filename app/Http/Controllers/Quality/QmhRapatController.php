<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\QmhRapat;
use App\Models\QmhRapatActionItem;
use App\Models\QmhRapatAttachment;
use App\Models\QmhRapatNotulensi;
use App\Models\QmhRapatPeserta;
use App\Models\User;
use App\Services\Quality\AuditTrailService;
use App\Services\Quality\QmhActionItemStateMachine;
use App\Services\Quality\QmhRapatWhatsappService;
use App\Services\WhatsApp\GowaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class QmhRapatController extends Controller
{
    public function __construct(
        private readonly QmhActionItemStateMachine $stateMachine,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canView($user), 403);

        $filters = validator($request->only(['search', 'meeting_type', 'status', 'from', 'to']), [
            'search' => ['nullable', 'string'],
            'meeting_type' => ['nullable', 'in:mingguan,bulanan,ad_hoc'],
            'status' => ['nullable', 'in:draft,scheduled,in_progress,completed,cancelled'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ])->validate();

        $rapats = QmhRapat::query()
            ->with(['creator', 'pesertas'])
            ->search($filters['search'] ?? null)
            ->when(isset($filters['meeting_type']), fn ($query) => $query->where('meeting_type', $filters['meeting_type']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['from']), fn ($query) => $query->whereDate('scheduled_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->whereDate('scheduled_at', '<=', $filters['to']))
            ->when(! $this->canViewAll($user), fn ($query) => $query->where('created_by', $user->id))
            ->orderByDesc('scheduled_at')
            ->paginate(15)
            ->appends($request->query());

        return view('quality.rapat.index', [
            'rapats' => $rapats,
            'canCreate' => $this->canCreate($user),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canCreate($user), 403);

        return view('quality.rapat.create', [
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canCreate($user), 403);

        $validated = validator($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'meeting_type' => ['required', 'in:mingguan,bulanan,ad_hoc'],
            'scheduled_at' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'agenda' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,scheduled,in_progress,completed,cancelled'],
            'participants' => ['nullable', 'array'],
            'participants.*' => ['integer', 'exists:users,id'],
        ])->validate();

        $rapat = DB::transaction(function () use ($validated, $user): QmhRapat {
            $rapat = QmhRapat::query()->create([
                'title' => $validated['title'],
                'meeting_type' => $validated['meeting_type'],
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'location' => $validated['location'] ?? null,
                'agenda' => $validated['agenda'] ?? null,
                'status' => $validated['status'] ?? 'draft',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach (($validated['participants'] ?? []) as $participantId) {
                QmhRapatPeserta::query()->create([
                    'rapat_id' => $rapat->id,
                    'user_id' => (int) $participantId,
                    'attendance_status' => 'hadir',
                ]);
            }

            return $rapat;
        });

        return redirect()
            ->route('quality.rapat.show', $rapat)
            ->with('success', 'Rapat QMH berhasil dibuat.');
    }

    public function show(Request $request, QmhRapat $rapat): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canViewRapat($user, $rapat), 403);

        $rapat->load([
            'creator',
            'updater',
            'pesertas.user',
            'notulensis.creator',
            'actionItems.assignee',
            'attachments.uploader',
        ]);

        return view('quality.rapat.show', [
            'rapat' => $rapat,
            'canManage' => $this->canEditRapat($user, $rapat),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function pdf(Request $request, QmhRapat $rapat, QmhRapatWhatsappService $service): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canViewRapat($user, $rapat), 403);

        $binary = $service->renderSummaryPdf($rapat);
        $filename = 'hasil-rapat-'.$rapat->id.'.pdf';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function sendWhatsAppSummary(Request $request, QmhRapat $rapat, QmhRapatWhatsappService $service): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canEditRapat($user, $rapat), 403);

        $validated = validator($request->all(), [
            'recipient_type' => ['required', 'in:individual,group'],
            'recipient_value' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        try {
            $result = $service->sendSummaryPdf($rapat, $user, $validated);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('quality.rapat.show', $rapat)
                ->withInput()
                ->withErrors(['whatsapp' => $exception->getMessage()]);
        }

        if (! ($result['success'] ?? false)) {
            return redirect()
                ->route('quality.rapat.show', $rapat)
                ->withInput()
                ->withErrors(['whatsapp' => (string) ($result['error'] ?? 'Gagal mengirim PDF ke WhatsApp.')]);
        }

        return redirect()
            ->route('quality.rapat.show', $rapat)
            ->with('success', 'PDF hasil rapat berhasil dikirim ke WhatsApp.');
    }

    public function whatsappGroups(Request $request, GowaClient $gowaClient): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canCreate($user), 403);

        $result = $gowaClient->getJoinedGroups();

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'groups' => [],
                'message' => (string) ($result['error'] ?? 'Gagal mengambil daftar grup WhatsApp.'),
            ], 422);
        }

        $groups = collect($result['groups'] ?? [])
            ->filter(fn ($group) => str_ends_with((string) ($group['JID'] ?? $group['jid'] ?? ''), '@g.us'))
            ->map(fn ($group) => [
                'jid' => (string) ($group['JID'] ?? $group['jid'] ?? ''),
                'name' => (string) ($group['Name'] ?? $group['name'] ?? 'Grup Tanpa Nama'),
            ])
            ->values();

        return response()->json([
            'groups' => $groups,
        ]);
    }

    public function storeAttachments(Request $request, QmhRapat $rapat): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canEditRapat($user, $rapat), 403);

        $validated = validator($request->all(), [
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => [
                'required',
                'file',
                'mimetypes:'.implode(',', $this->allowedAttachmentMimes()),
                'max:'.$this->maxAttachmentSizeKb(),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $disk = $this->attachmentDisk();
        $baseDir = $this->attachmentDir();
        $storedRecords = [];

        try {
            DB::transaction(function () use ($validated, $rapat, $user, $disk, $baseDir, &$storedRecords): void {
                foreach ($validated['files'] as $file) {
                    $extension = strtolower((string) $file->getClientOriginalExtension());
                    $filename = Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
                    $directory = trim($baseDir !== '' ? $baseDir.'/'.$rapat->id : (string) $rapat->id, '/');
                    $storedPath = Storage::disk($disk)->putFileAs($directory, $file, $filename);
                    if (! is_string($storedPath) || $storedPath === '') {
                        throw new InvalidArgumentException('Gagal menyimpan salah satu file dokumentasi.');
                    }

                    $record = QmhRapatAttachment::query()->create([
                        'rapat_id' => $rapat->id,
                        'file_disk' => $disk,
                        'file_path' => $storedPath,
                        'file_name' => (string) $file->getClientOriginalName(),
                        'file_mime' => (string) $file->getClientMimeType(),
                        'file_size' => (int) $file->getSize(),
                        'notes' => $validated['notes'] ?? null,
                        'uploaded_by' => $user->id,
                    ]);

                    $storedRecords[] = $record;
                }
            });
        } catch (\Throwable $exception) {
            foreach ($storedRecords as $record) {
                if ($record instanceof QmhRapatAttachment) {
                    Storage::disk((string) $record->file_disk)->delete((string) $record->file_path);
                    $record->delete();
                }
            }

            return redirect()
                ->route('quality.rapat.show', $rapat)
                ->withInput()
                ->withErrors(['attachments' => 'Gagal mengunggah dokumentasi rapat.']);
        }

        return redirect()
            ->route('quality.rapat.show', $rapat)
            ->with('success', 'Dokumentasi rapat berhasil diunggah.');
    }

    public function fileAttachment(Request $request, QmhRapat $rapat, QmhRapatAttachment $attachment): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canViewRapat($user, $rapat), 403);
        abort_unless((int) $attachment->rapat_id === (int) $rapat->id, 404);

        $disk = (string) $attachment->file_disk;
        $path = (string) $attachment->file_path;

        abort_unless($path !== '' && Storage::disk($disk)->exists($path), 404, 'File tidak ditemukan');

        $download = $request->boolean('download');
        $filename = (string) $attachment->file_name;

        if ($download) {
            return Storage::disk($disk)->download($path, $filename);
        }

        return Storage::disk($disk)->response($path, $filename, [
            'Content-Type' => (string) ($attachment->file_mime ?: 'application/octet-stream'),
        ]);
    }

    public function destroyAttachment(Request $request, QmhRapat $rapat, QmhRapatAttachment $attachment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canEditRapat($user, $rapat), 403);
        abort_unless((int) $attachment->rapat_id === (int) $rapat->id, 404);

        $disk = (string) $attachment->file_disk;
        $path = (string) $attachment->file_path;

        if ($path !== '' && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }

        $attachment->delete();

        return redirect()
            ->route('quality.rapat.show', $rapat)
            ->with('success', 'Dokumentasi rapat berhasil dihapus.');
    }

    public function update(Request $request, QmhRapat $rapat): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canEditRapat($user, $rapat), 403);

        $validated = validator($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'meeting_type' => ['required', 'in:mingguan,bulanan,ad_hoc'],
            'scheduled_at' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'agenda' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,scheduled,in_progress,completed,cancelled'],
        ])->validate();

        $rapat->fill($validated);
        $rapat->updated_by = $user->id;
        $rapat->save();

        return redirect()
            ->route('quality.rapat.show', $rapat)
            ->with('success', 'Rapat QMH berhasil diperbarui.');
    }

    public function destroy(Request $request, QmhRapat $rapat): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canDeleteRapat($user, $rapat), 403);

        $rapat->delete();

        return redirect()
            ->route('quality.rapat.index')
            ->with('success', 'Rapat QMH berhasil dihapus.');
    }

    public function storePeserta(Request $request, QmhRapat $rapat): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canEditRapat($user, $rapat), 403);

        $validated = validator($request->all(), [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'attendance_status' => ['required', 'in:hadir,tidak_hadir,izin'],
            'notes' => ['nullable', 'string', 'max:255'],
        ])->validate();

        QmhRapatPeserta::query()->updateOrCreate(
            [
                'rapat_id' => $rapat->id,
                'user_id' => $validated['user_id'],
            ],
            [
                'attendance_status' => $validated['attendance_status'],
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return redirect()->route('quality.rapat.show', $rapat)->with('success', 'Peserta rapat berhasil disimpan.');
    }

    public function storeNotulensi(Request $request, QmhRapat $rapat): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canEditRapat($user, $rapat), 403);

        $validated = validator($request->all(), [
            'content' => ['required', 'string'],
        ])->validate();

        $nextVersion = ((int) $rapat->notulensis()->max('version')) + 1;

        QmhRapatNotulensi::query()->create([
            'rapat_id' => $rapat->id,
            'version' => $nextVersion,
            'content' => $validated['content'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('quality.rapat.show', $rapat)->with('success', 'Notulensi rapat berhasil disimpan.');
    }

    public function storeActionItem(Request $request, QmhRapat $rapat): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canEditRapat($user, $rapat), 403);

        $validated = validator($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ])->validate();

        QmhRapatActionItem::query()->create([
            'rapat_id' => $rapat->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'assignee_id' => $validated['assignee_id'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'status' => QmhRapatActionItem::STATUS_OPEN,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('quality.rapat.show', $rapat)->with('success', 'Action item berhasil ditambahkan.');
    }

    public function updateActionItemStatus(Request $request, QmhRapat $rapat, QmhRapatActionItem $actionItem): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless((int) $actionItem->rapat_id === (int) $rapat->id, 404);

        $validated = validator($request->all(), [
            'status' => ['required', 'in:in_progress,resolved,verified,closed'],
        ])->validate();

        abort_unless($this->canTransitionActionItem($user, $rapat, $actionItem, $validated['status']), 403);

        try {
            $before = $actionItem->toArray();
            $this->stateMachine->transition($actionItem, $validated['status'], (int) $user->id);

            $this->auditTrailService->log(
                tableName: 'qmh_rapat_action_items',
                recordId: $actionItem->id,
                action: 'STATE_CHANGE',
                oldValues: $before,
                newValues: $actionItem->fresh()?->toArray(),
                changedBy: (int) $user->id,
                reason: 'Perubahan status action item rapat'
            );
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('quality.rapat.show', $rapat)
                ->withErrors(['status' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()->route('quality.rapat.show', $rapat)->with('success', 'Status action item berhasil diperbarui.');
    }

    private function canTransitionActionItem(User $user, QmhRapat $rapat, QmhRapatActionItem $actionItem, string $nextStatus): bool
    {
        if ($this->canEditRapat($user, $rapat)) {
            return true;
        }

        if ((int) $actionItem->assignee_id === (int) $user->id && in_array($nextStatus, [
            QmhRapatActionItem::STATUS_IN_PROGRESS,
            QmhRapatActionItem::STATUS_RESOLVED,
        ], true)) {
            return true;
        }

        if ((int) $actionItem->created_by === (int) $user->id && $nextStatus === QmhRapatActionItem::STATUS_CLOSED) {
            return true;
        }

        return false;
    }

    private function canView(User $user): bool
    {
        return $user->hasAnyPermission(['qmh.rapat.view', 'qmh.rapat.view.all', 'qmh.view']);
    }

    private function canViewAll(User $user): bool
    {
        return $user->hasAnyPermission(['qmh.rapat.view.all', 'qmh.view']);
    }

    private function canCreate(User $user): bool
    {
        return $user->hasAnyPermission(['qmh.rapat.create', 'qmh.rapat.create.all', 'qmh.create']);
    }

    private function canEditRapat(User $user, QmhRapat $rapat): bool
    {
        if ($user->hasAnyPermission(['qmh.rapat.edit', 'qmh.rapat.create.all', 'qmh.create'])) {
            return true;
        }

        return (int) $rapat->created_by === (int) $user->id;
    }

    private function canDeleteRapat(User $user, QmhRapat $rapat): bool
    {
        if ($user->hasAnyPermission(['qmh.rapat.delete', 'qmh.rapat.create.all', 'qmh.create'])) {
            return true;
        }

        return (int) $rapat->created_by === (int) $user->id;
    }

    private function canViewRapat(User $user, QmhRapat $rapat): bool
    {
        if ($this->canViewAll($user)) {
            return true;
        }

        if ((int) $rapat->created_by === (int) $user->id) {
            return true;
        }

        return $rapat->pesertas()->where('user_id', $user->id)->exists();
    }

    /**
     * @return array<int, string>
     */
    private function allowedAttachmentMimes(): array
    {
        $configured = config('quality.rapat_attachments.allowed_mimes', []);

        if (! is_array($configured) || count($configured) === 0) {
            return ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        }

        return array_values(array_filter($configured, static fn ($item): bool => is_string($item) && $item !== ''));
    }

    private function maxAttachmentSizeKb(): int
    {
        $configured = (int) config('quality.rapat_attachments.max_file_size_kb', 15360);

        return $configured > 0 ? $configured : 15360;
    }

    private function attachmentDisk(): string
    {
        return (string) config('quality.rapat_attachments.storage_disk', 'local');
    }

    private function attachmentDir(): string
    {
        return trim((string) config('quality.rapat_attachments.storage_dir', 'qmh-rapat-attachments'), '/');
    }
}
