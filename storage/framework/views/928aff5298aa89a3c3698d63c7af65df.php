<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['status']));

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

foreach (array_filter((['status']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $map = [
        'completed' => [__('tracking.badge_status.completed'), 'bg-success-600 text-white'],
        'current' => [__('tracking.badge_status.current'), 'bg-primary-600 text-white'],
        'pending' => [__('tracking.badge_status.pending'), 'bg-sem-mute text-sem-dim'],
    ];
    [$label, $classes] = $map[$status] ?? ['-', 'bg-sem-mute text-sem-dim'];
?>
<span <?php echo e($attributes->merge(['class' => 'inline-block px-2 py-0.5 text-xs rounded ' . $classes])); ?>><?php echo e($label); ?></span>
<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/components/timeline-status-badge.blade.php ENDPATH**/ ?>