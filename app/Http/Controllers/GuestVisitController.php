<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GuestBook\StoreGuestVisitRequest;
use App\Http\Requests\GuestBook\UpdateGuestVisitRequest;
use App\Http\Requests\GuestBook\UpdateVisitorRequest;
use App\Models\GuestVisit;
use App\Models\Investigator;
use App\Models\User;
use Illuminate\Http\Request;

class GuestVisitController extends Controller
{
    public function index(Request $request)
    {
        $query = GuestVisit::with(['investigator', 'host'])
            ->orderBy('visit_date', 'desc')
            ->orderBy('visit_time', 'desc');

        if ($search = $request->get('q')) {
            $escaped = addcslashes($search, '%_');
            $query->where(function ($q) use ($escaped) {
                $q->whereRaw('LOWER(visitor_name) LIKE ?', ['%'.mb_strtolower($escaped).'%'])
                    ->orWhereRaw('LOWER(visitor_phone) LIKE ?', ['%'.mb_strtolower($escaped).'%'])
                    ->orWhereHas('investigator', function ($sub) use ($escaped) {
                        $sub->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($escaped).'%'])
                            ->orWhereRaw('LOWER(nrp) LIKE ?', ['%'.mb_strtolower($escaped).'%'])
                            ->orWhereRaw('LOWER(institution) LIKE ?', ['%'.mb_strtolower($escaped).'%'])
                            ->orWhereRaw('LOWER(jurisdiction) LIKE ?', ['%'.mb_strtolower($escaped).'%']);
                    });
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->get('from')) {
            $query->where('visit_date', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->where('visit_date', '<=', $to);
        }

        if ($hostId = $request->get('host_id')) {
            $query->where('host_id', $hostId);
        }

        $visits = $query->paginate(15)->withQueryString();

        $hosts = User::whereHas('permissions', function ($q) {
            $q->whereIn('name', ['guest-book.create', 'request.create']);
        })->orderBy('name')->get(['id', 'name']);

        return view('guest-book.index', compact('visits', 'hosts'));
    }

    public function create()
    {
        $investigators = Investigator::orderBy('name')->get(['id', 'name', 'nrp', 'rank', 'jurisdiction', 'institution', 'phone', 'is_polri']);
        $hosts = User::orderBy('name')->get(['id', 'name']);

        return view('guest-book.create', compact('investigators', 'hosts'));
    }

    public function store(StoreGuestVisitRequest $request)
    {
        $validated = $request->validated();
        $sameAsOwner = $request->boolean('same_as_owner');

        $investigator = Investigator::findOrFail($validated['investigator_id']);

        $visitorData = $this->resolveVisitorData($validated, $investigator, $sameAsOwner);

        $visit = GuestVisit::create([
            'investigator_id' => $investigator->id,
            'visit_date' => $validated['visit_date'],
            'visit_time' => $validated['visit_time'],
            'purpose' => $validated['purpose'],
            'host_id' => $validated['host_id'] ?? null,
            'visitor_name' => $visitorData['visitor_name'],
            'visitor_identity' => $visitorData['visitor_identity'],
            'visitor_relation' => $visitorData['visitor_relation'],
            'visitor_phone' => $visitorData['visitor_phone'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);
        $visit->forceFill([
            'nda_accepted' => true,
            'nda_accepted_at' => now(),
        ])->save();

        return redirect()->route('guest-book.index')
            ->with('success', 'Kunjungan berhasil dicatat.');
    }

    public function show(GuestVisit $visit)
    {
        $visit->load(['investigator', 'testRequest', 'host', 'createdBy']);

        return view('guest-book.show', compact('visit'));
    }

    public function edit(GuestVisit $visit)
    {
        $visit->load('investigator');
        $hosts = User::orderBy('name')->get(['id', 'name']);

        return view('guest-book.edit', compact('visit', 'hosts'));
    }

    public function update(UpdateGuestVisitRequest $request, GuestVisit $visit)
    {
        $validated = $request->validated();
        $sameAsOwner = $request->boolean('same_as_owner');

        $investigator = $visit->investigator;

        $visitorData = $this->resolveVisitorData($validated, $investigator, $sameAsOwner);

        $visit->update([
            'visit_date' => $validated['visit_date'],
            'visit_time' => $validated['visit_time'],
            'purpose' => $validated['purpose'],
            'host_id' => $validated['host_id'] ?? null,
            'visitor_name' => $visitorData['visitor_name'],
            'visitor_identity' => $visitorData['visitor_identity'],
            'visitor_relation' => $visitorData['visitor_relation'],
            'visitor_phone' => $visitorData['visitor_phone'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('guest-book.show', $visit)
            ->with('success', 'Data kunjungan berhasil diperbarui.');
    }

    public function checkout(GuestVisit $visit)
    {
        $this->authorize('update', $visit);

        $affected = GuestVisit::where('id', $visit->id)
            ->where('status', 'active')
            ->update([
                'status' => 'checked_out',
                'check_out_at' => now(),
            ]);

        if (! $affected) {
            return back()->with('error', 'Kunjungan ini sudah checkout sebelumnya.');
        }

        $visit->refresh();

        return back()->with('success', 'Tamu berhasil dicatat keluar.');
    }

    public function updateVisitor(UpdateVisitorRequest $request, GuestVisit $visit)
    {
        $this->authorize('update', $visit);

        $sameAsOwner = $request->boolean('same_as_owner');
        $investigator = $visit->investigator;

        if ($sameAsOwner) {
            $visit->update([
                'visitor_name' => $investigator->name,
                'visitor_identity' => $investigator->nrp,
                'visitor_relation' => 'Penyidik',
                'visitor_phone' => $investigator->phone,
            ]);
        } else {
            $visit->update([
                'visitor_name' => $validated['visitor_name'] ?? null,
                'visitor_identity' => $validated['visitor_identity'] ?? null,
                'visitor_relation' => $validated['visitor_relation'] ?? null,
                'visitor_phone' => $validated['visitor_phone'] ?? null,
            ]);
        }

        return back()->with('success', 'Data pihak yang datang berhasil diverifikasi.');
    }

    public function destroy(GuestVisit $visit)
    {
        $visit->delete();

        return redirect()->route('guest-book.index')
            ->with('success', 'Data kunjungan berhasil dihapus.');
    }

    private function resolveVisitorData(array $validated, Investigator $investigator, bool $sameAsOwner): array
    {
        if ($sameAsOwner) {
            return [
                'visitor_name' => $investigator->name,
                'visitor_identity' => $investigator->nrp,
                'visitor_relation' => 'Penyidik',
                'visitor_phone' => $investigator->phone,
            ];
        }

        return [
            'visitor_name' => $validated['visitor_name'] ?? null,
            'visitor_identity' => $validated['visitor_identity'] ?? null,
            'visitor_relation' => $validated['visitor_relation'] ?? null,
            'visitor_phone' => $validated['visitor_phone'] ?? null,
        ];
    }
}
