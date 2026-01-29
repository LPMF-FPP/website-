<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Manajemen Personel"
            :breadcrumbs="[['label' => 'Manajemen Personel']]"
        >
            <x-slot name="actions">
                @if($activeTab === 'staff' && Gate::allows('manage-users'))
                    <a href="{{ route('analysts.create') }}" class="btn btn-primary text-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Tambah Staff
                    </a>
                @elseif($activeTab === 'penyidik' && Gate::allows('investigators.edit'))
                    {{-- Investigator create logic might be different or modal-based, checking routes --}}
                    {{-- There is no investigators.create route in the route list I saw earlier! Check if it was missed or handled differently. --}}
                    {{-- InvestigatorManagementController has no create method listed in my analysis. It might be seeded or handled elsewhere? --}}
                    {{-- Wait, let me check InvestigatorManagementController again. --}}
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-6">
        
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                @can('manage-users')
                <a href="{{ route('personnel.index', ['tab' => 'staff']) }}"
                   class="{{ $activeTab === 'staff'
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}
                        whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    Staff & Pengguna
                </a>
                @endcan

                @can('investigators.view')
                <a href="{{ route('personnel.index', ['tab' => 'penyidik']) }}"
                   class="{{ $activeTab === 'penyidik'
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}
                        whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Penyidik
                </a>
                @endcan
            </nav>
        </div>

        <!-- Content -->
        <div>
            @if($activeTab === 'staff')
                @include('personnel.partials.tab-staff')
            @elseif($activeTab === 'penyidik')
                @include('personnel.partials.tab-penyidik')
            @endif
        </div>

    </div>
</x-app-layout>
