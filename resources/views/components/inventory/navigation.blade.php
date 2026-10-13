@php
    $user = auth()->user();
    $dataLinks = [];
    $transactionLinks = [];
    $reviewLinks = [];
    $sampleLinks = [];

    if ($user?->hasPermission('inventori.view')) {
        $dataLinks[] = ['label' => 'Master Item', 'route' => 'inventory.items.index', 'active' => 'inventory.items.*'];
        $dataLinks[] = ['label' => 'Lokasi Penyimpanan', 'route' => 'inventory.locations.index', 'active' => 'inventory.locations.*'];
        $dataLinks[] = ['label' => 'Kartu Stok', 'route' => 'inventory.stock-card', 'active' => 'inventory.stock-card*'];
        $dataLinks[] = ['label' => 'Peringatan', 'route' => 'inventory.alerts.index', 'active' => 'inventory.alerts.*'];
    } elseif ($user?->hasPermission('inventori.location-access.manage')) {
        $dataLinks[] = ['label' => 'Lokasi Penyimpanan', 'route' => 'inventory.locations.index', 'active' => 'inventory.locations.*'];
    }

    if ($user?->hasPermission('inventori.view') && $user->hasPermission('inventori.location-access.manage')) {
        $dataLinks[] = ['label' => 'Kebijakan lot', 'route' => 'inventory.baseline.policies', 'active' => 'inventory.baseline.policies'];
    }

    if ($user?->hasPermission('inventori.receipt.create')) {
        $transactionLinks[] = ['label' => 'Terima Stok', 'route' => 'inventory.transaction.receipt', 'active' => 'inventory.transaction.receipt'];
    }

    if ($user?->hasPermission('inventori.issue.create')) {
        $transactionLinks[] = ['label' => 'Pengeluaran Stok', 'route' => 'inventory.transaction.issue', 'active' => 'inventory.transaction.issue'];
    }

    if ($user?->hasPermission('inventori.transfer.create')) {
        $transactionLinks[] = ['label' => 'Transfer Stok', 'route' => 'inventory.transaction.transfer', 'active' => 'inventory.transaction.transfer'];
    }

    if ($user?->hasPermission('inventori.material-disposal.create')) {
        $transactionLinks[] = ['label' => 'Disposal Material', 'route' => 'inventory.transaction.dispose', 'active' => 'inventory.transaction.dispose'];
    }

    if ($user?->hasPermission('inventori.view') && ($user->hasPermission('inventori.stocktake.submit') || $user->hasPermission('inventori.stocktake.approve') || $user->hasPermission('inventori.location-access.manage') || $user->hasPermission('inventori.readiness.inventory-admin.accept') || $user->hasPermission('inventori.readiness.system-owner.accept'))) {
        $reviewLinks[] = ['label' => 'Catat Stok Awal', 'route' => 'inventory.opening-baseline.index', 'active' => 'inventory.opening-baseline.*'];
    }

    if ($user?->hasPermission('inventori.view') && ($user->hasPermission('inventori.stocktake.submit') || $user->hasPermission('inventori.stocktake.approve'))) {
        $reviewLinks[] = ['label' => 'Periksa Sebagian Stok', 'route' => 'inventory.cycle-counts.index', 'active' => 'inventory.cycle-counts.*'];
    }

    if ($user?->hasPermission('sample-disposal.view')) {
        $sampleLinks[] = ['label' => 'Pemusnahan Sampel', 'route' => 'inventory.disposal.index', 'active' => 'inventory.disposal.*'];
    }

    $navigationGroups = array_filter([
        ['id' => 'inventory-data-links', 'label' => $user?->hasPermission('inventori.view') ? 'Data' : 'Lokasi', 'items' => $dataLinks],
        ['id' => 'inventory-transaction-links', 'label' => 'Transaksi', 'items' => $transactionLinks],
        ['id' => 'inventory-review-links', 'label' => 'Pemeriksaan', 'items' => $reviewLinks],
        ['id' => 'inventory-sample-links', 'label' => 'Sampel', 'items' => $sampleLinks],
    ], static fn (array $group): bool => $group['items'] !== []);
@endphp

@if($user?->hasPermission('inventori.view') || $navigationGroups !== [])
    <nav x-data aria-label="Navigasi inventori" class="border-t border-sem" @click.outside="$el.querySelectorAll('details[open]').forEach((menu) => { menu.open = false; })">
        <ul class="container mx-auto flex max-w-7xl flex-nowrap items-center gap-2 px-4 py-2 sm:px-6 lg:px-8">
            @if($user?->hasPermission('inventori.view'))
                @php($isDashboard = request()->routeIs('inventory.dashboard'))
                <li class="shrink-0">
                    <a href="{{ route('inventory.dashboard') }}"
                        @if($isDashboard) aria-current="page" @endif
                        @class([
                            'inline-flex min-h-11 items-center whitespace-nowrap rounded-md px-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:focus-visible:ring-primary-300 dark:focus-visible:ring-offset-slate-900',
                            'bg-primary-800 text-white hover:bg-primary-900 dark:bg-primary-300 dark:text-slate-900 dark:hover:bg-primary-200' => $isDashboard,
                            'text-gray-700 hover:bg-gray-100 hover:text-gray-950 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white' => ! $isDashboard,
                        ])>
                        Ikhtisar
                    </a>
                </li>
            @endif

            @foreach($navigationGroups as $group)
                @php($isCurrentGroup = collect($group['items'])->contains(fn (array $item): bool => request()->routeIs($item['active'])))
                <li class="shrink-0">
                    <details class="group relative" @keydown.escape.window="if ($el.open) { $el.open = false; $el.querySelector('summary').focus(); }">
                        <summary aria-controls="{{ $group['id'] }}" data-current-group="{{ $isCurrentGroup ? 'true' : 'false' }}" @click="$el.closest('nav').querySelectorAll('details').forEach((menu) => { if (menu !== $el.parentElement) menu.open = false; })"
                            @class([
                                'inline-flex min-h-11 list-none cursor-pointer items-center gap-2 whitespace-nowrap rounded-md px-3 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:focus-visible:ring-primary-300 dark:focus-visible:ring-offset-slate-900',
                                'bg-primary-50 text-primary-900 dark:bg-slate-800 dark:text-primary-200' => $isCurrentGroup,
                                'text-gray-700 hover:bg-gray-100 hover:text-gray-950 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white' => ! $isCurrentGroup,
                            ])>
                            {{ $group['label'] }}
                            @if($isCurrentGroup)<span class="sr-only">, mencakup halaman saat ini</span>@endif
                            <span aria-hidden="true" class="text-xs group-open:rotate-180">▾</span>
                        </summary>
                        <ul id="{{ $group['id'] }}" class="absolute left-0 top-full z-50 mt-1 min-w-56 rounded-md border border-gray-200 bg-white p-1 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                            @foreach($group['items'] as $item)
                                @php($isCurrentItem = request()->routeIs($item['active']))
                                <li>
                                    <a href="{{ route($item['route']) }}"
                                        @if($isCurrentItem) aria-current="page" @endif
                                        @class([
                                            'flex min-h-11 items-center rounded px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-inset dark:focus-visible:ring-primary-300',
                                            'bg-primary-50 font-semibold text-primary-900 dark:bg-slate-700 dark:text-primary-200' => $isCurrentItem,
                                            'text-gray-800 hover:bg-gray-100 dark:text-slate-200 dark:hover:bg-slate-700' => ! $isCurrentItem,
                                        ])>
                                        {{ $item['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
