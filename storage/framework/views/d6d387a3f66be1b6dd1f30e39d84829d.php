<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['active']));

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

foreach (array_filter((['active']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
$classes = 'mobile-nav-link '.(($active ?? false) ? 'mobile-nav-link-active' : 'mobile-nav-link-inactive');
?>

<a <?php echo e($attributes->merge(['class' => $classes])); ?> <?php if($active ?? false): ?> aria-current="page" <?php endif; ?>>
    <?php echo e($slot); ?>

</a>
<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/components/responsive-nav-link.blade.php ENDPATH**/ ?>