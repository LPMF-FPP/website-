@php
    $fieldIds = ['item_id' => 'item-select', 'lot_id' => 'lot-select', 'location_id' => 'location-select', 'from_location_id' => 'from-location', 'to_location_id' => 'to-location'];
@endphp
<ul class="mt-2 list-inside list-disc" x-data="{}">
    @foreach($errors->messages() as $field => $messages)
        @foreach($messages as $message)
            <li><a href="#{{ $fieldIds[$field] ?? $operation.'-form' }}" @click.prevent="const field = document.querySelector(@js('[name="'.$field.'"]')); const target = field && field.type !== 'hidden' && !field.disabled && field.offsetParent !== null ? field : document.getElementById(@js($operation.'-form')); if (target) { if (target.tagName === 'FORM') target.tabIndex = -1; target.scrollIntoView({ block: 'center' }); target.focus(); }" class="inline-flex min-h-11 items-center underline">{{ $message }}</a></li>
        @endforeach
    @endforeach
</ul>
