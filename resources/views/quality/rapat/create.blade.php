<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Buat Rapat QMH"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Rapat', 'route' => 'quality.rapat.index'],
                    ['label' => 'Buat Rapat'],
                ]"
            />

            <x-qmh-subnav active="rapat" />
        </div>
    </x-slot>

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('quality.rapat.store') }}" class="space-y-5">
            @csrf

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="title">Judul Rapat</label>
                    <input id="title" name="title" type="text" required value="{{ old('title') }}"
                           class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="meeting_type">Jenis Rapat</label>
                    <select id="meeting_type" name="meeting_type" required class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                        <option value="mingguan" @selected(old('meeting_type') === 'mingguan')>Mingguan</option>
                        <option value="bulanan" @selected(old('meeting_type') === 'bulanan')>Bulanan</option>
                        <option value="ad_hoc" @selected(old('meeting_type', 'ad_hoc') === 'ad_hoc')>Ad-hoc</option>
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="scheduled_at">Jadwal</label>
                    <input id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}"
                           class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="location">Lokasi</label>
                    <input id="location" name="location" type="text" value="{{ old('location') }}"
                           class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
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
                <label class="mb-1 block text-sm font-medium text-gray-700" for="agenda">Agenda</label>
                <textarea id="agenda" name="agenda" rows="5" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">{{ old('agenda') }}</textarea>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Peserta</label>
                <div class="grid max-h-60 gap-2 overflow-auto rounded-md border border-gray-200 p-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($users as $user)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="participants[]" value="{{ $user->id }}" @checked(collect(old('participants', []))->contains($user->id)) class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600">
                            <span>{{ $user->name }} <span class="text-xs text-gray-500">({{ $user->role }})</span></span>
                        </label>
                    @endforeach
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

            <div class="flex items-center justify-end gap-2">
                <a href="{{ route('quality.rapat.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</a>
                <button type="submit" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Simpan</button>
            </div>
        </form>
    </div>
</x-app-layout>
