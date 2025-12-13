<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'columns' => 5,
    'rows' => 8,
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'columns' => 5,
    'rows' => 8,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div role="status" aria-live="polite" aria-busy="true" class="animate-pulse">
    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
        <div class="min-w-full">
            <div class="grid grid-cols-<?php echo e($columns); ?> gap-2 bg-gray-50 px-4 py-3">
                <?php for($i = 0; $i < $columns; $i++): ?>
                    <div class="h-4 w-24 rounded bg-gray-200"></div>
                <?php endfor; ?>
            </div>
            <div>
                <?php for($r = 0; $r < $rows; $r++): ?>
                    <div class="grid grid-cols-<?php echo e($columns); ?> gap-2 border-t border-gray-100 px-4 py-3">
                        <?php for($c = 0; $c < $columns; $c++): ?>
                            <div class="h-4 w-full rounded bg-gray-100"></div>
                        <?php endfor; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    <span class="sr-only">Memuat data...</span>
    <style>
      @media (prefers-color-scheme: dark) {
        .animate-pulse .bg-gray-50 { background-color: rgb(39, 39, 42); }
        .animate-pulse .bg-gray-200 { background-color: rgb(82, 82, 91); }
        .animate-pulse .bg-gray-100 { background-color: rgb(63, 63, 70); }
      }
    </style>
</div>
<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/components/skeleton-table.blade.php ENDPATH**/ ?>