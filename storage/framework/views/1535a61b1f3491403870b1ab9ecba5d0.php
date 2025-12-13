<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'title' => '',
    'description' => null,
    'class' => ''
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
    'title' => '',
    'description' => null,
    'class' => ''
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<section class="<?php echo e($class); ?>">
    <?php if($title || isset($actions)): ?>
        <div class="mb-3 flex items-start justify-between gap-4">
            <?php if($title): ?>
                <h3 class="text-base font-semibold text-gray-900"><?php echo e($title); ?></h3>
            <?php endif; ?>
            <?php if(isset($actions)): ?>
                <div class="shrink-0"><?php echo e($actions); ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if($description): ?>
        <p class="mb-3 text-sm text-gray-600"><?php echo e($description); ?></p>
    <?php endif; ?>
    <?php echo e($slot); ?>

</section>
<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/components/page-section.blade.php ENDPATH**/ ?>