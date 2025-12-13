<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'title' => 'Data belum tersedia',
    'description' => 'Belum ada data yang dapat ditampilkan.',
    'actionHref' => null,
    'actionText' => null,
    'icon' => 'document',
    'size' => 'md', // sm|md|lg
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
    'title' => 'Data belum tersedia',
    'description' => 'Belum ada data yang dapat ditampilkan.',
    'actionHref' => null,
    'actionText' => null,
    'icon' => 'document',
    'size' => 'md', // sm|md|lg
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $sizes = [
        'sm' => ['icon' => 'h-10 w-10', 'title' => 'text-base', 'desc' => 'text-xs', 'pad' => 'py-8 px-4'],
        'md' => ['icon' => 'h-12 w-12', 'title' => 'text-lg', 'desc' => 'text-sm', 'pad' => 'py-12 px-4'],
        'lg' => ['icon' => 'h-16 w-16', 'title' => 'text-xl', 'desc' => 'text-base', 'pad' => 'py-16 px-6'],
    ];
    $cfg = $sizes[$size] ?? $sizes['md'];
?>

<div class="text-center <?php echo e($cfg['pad']); ?>">
    <div class="mx-auto mb-4 inline-flex items-center justify-center rounded-full bg-primary-50 <?php echo e($cfg['icon']); ?>">
        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon,'size' => 'md','color' => 'primary','decorative' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'size' => 'md','color' => 'primary','decorative' => true]); ?>
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
    </div>
    <h3 class="font-semibold text-primary-900 <?php echo e($cfg['title']); ?>"><?php echo e($title); ?></h3>
    <p class="mt-1 text-accent-600 <?php echo e($cfg['desc']); ?>"><?php echo e($description); ?></p>
    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
        <?php if($actionHref && $actionText): ?>
            <a href="<?php echo e($actionHref); ?>" class="btn btn-primary">
                <?php echo e($actionText); ?>

            </a>
        <?php endif; ?>
        <?php if(isset($actions)): ?>
            <?php echo e($actions); ?>

        <?php endif; ?>
    </div>
</div>
<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/components/empty-state.blade.php ENDPATH**/ ?>