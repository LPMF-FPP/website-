<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Buat Audit QMH"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Audit', 'route' => 'quality.audit.index'],
                    ['label' => 'Buat Audit'],
                ]"
            />

            <x-qmh-subnav active="audit" />
        </div>
    </x-slot>

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('quality.audit.store') }}" class="space-y-5">
            @csrf

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="title">Judul Audit</label>
                    <input id="title" name="title" type="text" required value="{{ old('title') }}" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="audit_type">Tipe Audit</label>
                    <select id="audit_type" name="audit_type" required class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                        <option value="internal" @selected(old('audit_type') === 'internal')>Internal</option>
                        <option value="eksternal" @selected(old('audit_type') === 'eksternal')>Eksternal</option>
                        <option value="surveillance" @selected(old('audit_type', 'surveillance') === 'surveillance')>Surveillance</option>
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="scheduled_at">Jadwal</label>
                    <input id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="location">Lokasi</label>
                    <input id="location" name="location" type="text" value="{{ old('location') }}" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="status">Status</label>
                    <select id="status" name="status" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                        <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                        <option value="scheduled" @selected(old('status') === 'scheduled')>Terjadwal</option>
                        <option value="in_progress" @selected(old('status') === 'in_progress')>Berjalan</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="scope">Ruang Lingkup</label>
                <textarea id="scope" name="scope" rows="3" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">{{ old('scope') }}</textarea>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Auditor</label>
                <div class="grid max-h-60 gap-2 overflow-auto rounded-md border border-gray-200 p-3 sm:grid-cols-2 lg:grid-cols-3">
                    @php
                        $selectedAuditors = collect(old('auditors', old('auditors_json', [])))->map(fn ($id) => (int) $id);
                    @endphp
                    @foreach($users as $user)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="auditors[]" value="{{ $user->id }}" @checked($selectedAuditors->contains((int) $user->id)) class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600">
                            <span>{{ $user->name }} <span class="text-xs text-gray-500">({{ $user->role }})</span></span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="notes">Catatan</label>
                <textarea id="notes" name="notes" rows="4" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">{{ old('notes') }}</textarea>
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

            <div class="flex items-center justify-end gap-2">
                <a href="{{ route('quality.audit.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</a>
                <button type="submit" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Simpan</button>
            </div>
        </form>
    </div>
</x-app-layout>
