@props([
  'label',
  'value',
  'delta' => null, // e.g. +12%, -3.4%
  'trend' => null, // up|down|flat (auto inferred from delta if numeric)
  'icon' => null,
  'href' => null,
])
@php
  $parsedTrend = $trend;
  if ($parsedTrend === null && $delta !== null) {
      if (preg_match('/^-/', $delta)) $parsedTrend = 'down';
      elseif (preg_match('/^\+/', $delta)) $parsedTrend = 'up';
      else $parsedTrend = 'flat';
  }
  $colors = [
    'up' => 'text-success-600 bg-success-50',
    'down' => 'text-danger-600 bg-danger-50',
    'flat' => 'text-accent-600 bg-accent-100',
  ];
  $trendIcon = [
    'up' => 'arrow-up',
    'down' => 'arrow-down',
    'flat' => 'minus',
  ][$parsedTrend] ?? null;
@endphp
<figure {{ $attributes->merge(['class' => 'kpi-card relative rounded-xl border border-accent-200 bg-white p-5 shadow-sm hover:shadow-md transition']) }}>
  <div class="flex items-start justify-between gap-4">
    <div class="space-y-2">
      <figcaption class="text-xs font-medium uppercase tracking-wide text-accent-500">{{ $label }}</figcaption>
      <div class="text-2xl font-semibold text-primary-700">{{ $value }}</div>
      @if($delta !== null)
        <div class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium {{ $colors[$parsedTrend] ?? 'text-accent-600 bg-accent-100' }}">
          @if($trendIcon)
            <x-icon :name="$trendIcon" size="xs" />
          @endif
          <span>{{ $delta }}</span>
        </div>
      @endif
    </div>
    @if($icon)
      <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
        <x-icon :name="$icon" size="md" />
      </div>
    @endif
  </div>
  @if($href)
    <a href="{{ $href }}" class="absolute inset-0" aria-label="Lihat detail {{ $label }}"><span class="sr-only">Detail</span></a>
  @endif
</figure>
