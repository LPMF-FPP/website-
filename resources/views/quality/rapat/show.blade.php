<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                :title="$rapat->title"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Rapat', 'route' => 'quality.rapat.index'],
                    ['label' => 'Detail Rapat'],
                ]"
            />

            <x-qmh-subnav active="rapat" />
        </div>
    </x-slot>

    <div class="space-y-6" x-data="qmhRapatPage()">
        <div class="grid gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Jenis</p>
                <p class="mt-1 text-sm font-semibold text-gray-900">{{ ucfirst(str_replace('_', ' ', $rapat->meeting_type)) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Jadwal</p>
                <p class="mt-1 text-sm font-semibold text-gray-900">{{ $rapat->scheduled_at?->format('d M Y H:i') ?? '-' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Lokasi</p>
                <p class="mt-1 text-sm font-semibold text-gray-900">{{ $rapat->location ?: '-' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Status</p>
                <p class="mt-1">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass('{{ $rapat->status }}')">
                        {{ strtoupper($rapat->status) }}
                    </span>
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900">Agenda</h3>
            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $rapat->agenda ?: 'Belum ada agenda.' }}</p>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Peserta</h3>
                </div>

                <div class="space-y-2 text-sm">
                    @forelse($rapat->pesertas as $peserta)
                        <div class="rounded-md border border-gray-200 px-3 py-2">
                            <p class="font-medium text-gray-900">{{ $peserta->user?->name ?? 'User tidak ditemukan' }}</p>
                            <p class="text-xs text-gray-500">
                                Status:
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 font-semibold text-gray-700">{{ str_replace('_', ' ', strtoupper($peserta->attendance_status)) }}</span>
                            </p>
                        </div>
                    @empty
                        <p class="text-gray-500">Belum ada peserta.</p>
                    @endforelse
                </div>

                @if($canManage)
                    <form method="POST" action="{{ route('quality.rapat.peserta.store', $rapat) }}" class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                        @csrf
                        <select name="user_id" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" required>
                            <option value="">Pilih peserta</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                            @endforeach
                        </select>
                        <select name="attendance_status" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                            <option value="hadir">Hadir</option>
                            <option value="tidak_hadir">Tidak Hadir</option>
                            <option value="izin">Izin</option>
                        </select>
                        <button type="submit" class="rounded-md bg-primary-600 px-3 py-2 text-xs font-medium text-white hover:bg-primary-700">Simpan Peserta</button>
                    </form>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">Notulensi</h3>

                <div class="mt-3 space-y-3 text-sm">
                    @forelse($rapat->notulensis as $notulensi)
                        <div class="rounded-md border border-gray-200 px-3 py-2">
                            <p class="text-xs font-semibold text-gray-500">Versi {{ $notulensi->version }}</p>
                            <p class="mt-1 whitespace-pre-line text-gray-700">{{ $notulensi->content }}</p>
                        </div>
                    @empty
                        <p class="text-gray-500">Belum ada notulensi.</p>
                    @endforelse
                </div>

                @if($canManage)
                    <form method="POST" action="{{ route('quality.rapat.notulensi.store', $rapat) }}" class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                        @csrf
                        <textarea name="content" rows="4" placeholder="Tulis notulensi rapat" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" required></textarea>
                        <button type="submit" class="rounded-md bg-primary-600 px-3 py-2 text-xs font-medium text-white hover:bg-primary-700">Tambah Notulensi</button>
                    </form>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">Action Items</h3>

                <div class="mt-3 space-y-2 text-sm">
                    @forelse($rapat->actionItems as $item)
                        <div class="rounded-md border border-gray-200 px-3 py-2">
                            <p class="font-medium text-gray-900">{{ $item->title }}</p>
                            <p class="text-xs text-gray-500">PIC: {{ $item->assignee?->name ?? '-' }} | Jatuh tempo: {{ $item->due_date?->format('d M Y') ?? '-' }}</p>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="statusBadgeClass('{{ $item->status }}')">{{ strtoupper($item->status) }}</span>
                                @if($canManage)
                                    <form method="POST" action="{{ route('quality.rapat.action-items.status', [$rapat, $item]) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="rounded-md border-gray-300 text-xs focus:border-primary-600 focus:ring-primary-600">
                                            @foreach(['in_progress', 'resolved', 'verified', 'closed', 'overdue'] as $status)
                                                <option value="{{ $status }}" @selected($item->status === $status)>{{ strtoupper($status) }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">Update</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500">Belum ada action item.</p>
                    @endforelse
                </div>

                @if($canManage)
                    <form method="POST" action="{{ route('quality.rapat.action-items.store', $rapat) }}" class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                        @csrf
                        <input type="text" name="title" placeholder="Judul action item" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" required>
                        <textarea name="description" rows="3" placeholder="Deskripsi" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600"></textarea>
                        <select name="assignee_id" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                            <option value="">Pilih penanggung jawab</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                            @endforeach
                        </select>
                        <input type="date" name="due_date" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                        <button type="submit" class="rounded-md bg-primary-600 px-3 py-2 text-xs font-medium text-white hover:bg-primary-700">Tambah Action Item</button>
                    </form>
                @endif
            </div>
        </div>

        @if($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900">Quick Actions</h3>
            <div class="mt-3 flex flex-wrap gap-2 text-sm">
                <a href="{{ route('quality.rapat.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50">Kembali ke Daftar Rapat</a>
                <a href="{{ route('quality.governance.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50">Buka Governance Workspace</a>
            </div>
        </div>
    </div>
</x-app-layout>
