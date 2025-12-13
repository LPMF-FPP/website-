<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    // Backward compatibility: either pass label+variant, or a single 'status' value
    'status' => null,
    'label' => null,
    'variant' => null,
    'subtle' => false,
    'icon' => null,
    'showIcon' => false,
    'dot' => false,
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
    // Backward compatibility: either pass label+variant, or a single 'status' value
    'status' => null,
    'label' => null,
    'variant' => null,
    'subtle' => false,
    'icon' => null,
    'showIcon' => false,
    'dot' => false,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    // Legacy mapping
    $raw = $status ?? $variant ?? 'neutral';
    $map = [
        'pending' => ['warning', 'Menunggu'],
        'in_progress' => ['info', 'Diproses'],
        'processing' => ['info', 'Diproses'],
        'completed' => ['success', 'Selesai'],
        'done' => ['success', 'Selesai'],
        'rejected' => ['danger', 'Ditolak'],
        'failed' => ['danger', 'Gagal'],
        'cancelled' => ['danger', 'Dibatalkan'],
        'ready_for_delivery' => ['primary', 'Siap Diserahkan'],
        'delivered' => ['secondary', 'Diserahkan'],
        'draft' => ['neutral', 'Draft'],
    ];

    if (!$variant) {
        [$variant, $autoLabel] = $map[$raw] ?? ['neutral', ucfirst(str_replace('_', ' ', $raw))];
        $label = $label ?? $autoLabel;
    } else {
        $label = $label ?? ucfirst(str_replace('_', ' ', $raw));
    }

    $variant = $variant ?: 'neutral';

    // Semantic color fallback mapping (subtle background via color family 100) remains for now.
    $variantColors = [
        'primary' => ['bg-primary-100 text-primary-800 ring-primary-300', 'bg-primary-600 text-white'],
        'secondary' => ['bg-secondary-100 text-secondary-800 ring-secondary-300', 'bg-secondary-600 text-white'],
        'success' => ['bg-success-100 text-success-800 ring-success-300', 'bg-success-600 text-white'],
        'warning' => ['bg-warning-100 text-warning-800 ring-warning-300', 'bg-warning-500 text-accent-900'],
        'danger' => ['bg-danger-100 text-danger-800 ring-danger-300', 'bg-danger-600 text-white'],
        'info' => ['bg-info-100 text-info-800 ring-info-300', 'bg-info-600 text-white'],
        'neutral' => ['bg-accent-100 text-accent-700 ring-accent-300', 'bg-accent-500 text-white'],
    ];

    [$bgSubtle, $bgBold] = $variantColors[$variant] ?? $variantColors['neutral'];

    $base = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset';

    // When semantic subtle mode desired in future: could map to --pd-sem-color-* tokens.
    $classes = $base . ' ' . ($subtle ? $bgSubtle : $bgBold . ' shadow-sm');

    $iconMap = [
        'success' => 'check-circle',
        'warning' => 'alert-triangle',
        'danger' => 'x-circle',
        'error' => 'x-circle',
        'info' => 'info',
        'primary' => 'dot',
        'secondary' => 'dot',
        'neutral' => 'dot',
    ];

    $chosenIcon = $icon ?? ($iconMap[$variant] ?? null);
    $showIcon = $showIcon || $dot; // dot implies icon placeholder
?>

<span <?php echo e($attributes->merge(['class' => $classes])); ?>>
    <?php if($showIcon): ?>
        <?php if($dot): ?>
            <span class="inline-block h-2 w-2 rounded-full bg-current opacity-70"></span>
        <?php elseif($chosenIcon): ?>
            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $chosenIcon,'size' => 'xs','class' => 'opacity-70']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($chosenIcon),'size' => 'xs','class' => 'opacity-70']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
    <span><?php echo e($label); ?></span>
</span>


<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/components/status-badge.blade.php ENDPATH**/ ?>