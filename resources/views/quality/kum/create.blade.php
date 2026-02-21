<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Buat KUM QMH"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'KUM', 'route' => 'quality.kum.index'],
                    ['label' => 'Buat KUM'],
                ]"
            />

            <x-qmh-subnav active="kum" />
        </div>
    </x-slot>

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('quality.kum.store') }}" class="space-y-5">
            @csrf

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="title">Judul KUM</label>
                    <input id="title" name="title" type="text" required value="{{ old('title') }}" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="location">Lokasi</label>
                    <input id="location" name="location" type="text" value="{{ old('location') }}" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="year">Tahun</label>
                    <input id="year" name="year" type="number" min="2000" max="2100" required value="{{ old('year', now()->year) }}" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="period">Periode</label>
                    <select id="period" name="period" required class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                        <option value="q1" @selected(old('period') === 'q1')>Q1</option>
                        <option value="q2" @selected(old('period') === 'q2')>Q2</option>
                        <option value="q3" @selected(old('period') === 'q3')>Q3</option>
                        <option value="q4" @selected(old('period') === 'q4')>Q4</option>
                        <option value="annual" @selected(old('period', 'annual') === 'annual')>Annual</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="scheduled_at">Jadwal</label>
                    <input id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
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
                <textarea id="agenda" name="agenda" rows="4" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">{{ old('agenda') }}</textarea>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="minutes_content">Notulensi Awal</label>
                <textarea id="minutes_content" name="minutes_content" rows="4" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">{{ old('minutes_content') }}</textarea>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="participants_json_text">Peserta (satu nama per baris)</label>
                <textarea id="participants_json_text" name="participants_json_text" rows="4" class="w-full rounded-md border-gray-300 font-mono text-xs focus:border-primary-600 focus:ring-primary-600">{{ old('participants_json_text') }}</textarea>
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
                <a href="{{ route('quality.kum.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</a>
                <button type="submit" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Simpan</button>
            </div>
        </form>
    </div>
</x-app-layout>
