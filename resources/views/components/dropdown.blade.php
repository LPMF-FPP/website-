@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white'])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};
@endphp

<div class="relative" data-dropdown>
    <button type="button" class="inline-flex items-center" data-dropdown-trigger>
        {{ $trigger }}
    </button>

    <div class="absolute z-50 mt-2 {{ $width }} rounded-md shadow-lg {{ $alignmentClasses }} hidden" data-dropdown-panel>
        <div class="rounded-md ring-1 ring-black ring-opacity-5 {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function(){
                function setup(el){
                    const trigger = el.querySelector('[data-dropdown-trigger]');
                    const panel = el.querySelector('[data-dropdown-panel]');
                    if(!trigger || !panel) return;
                    let open = false;
                    const show = ()=>{ panel.classList.remove('hidden'); open = true; }
                    const hide = ()=>{ panel.classList.add('hidden'); open = false; }
                    const toggle = ()=> open ? hide() : show();
                    trigger.addEventListener('click', (e)=>{ e.stopPropagation(); toggle(); });
                    panel.addEventListener('click', ()=> hide());
                    document.addEventListener('click', (e)=>{
                        if(!el.contains(e.target)) hide();
                    });
                }
                document.querySelectorAll('[data-dropdown]').forEach(setup);
                document.addEventListener('turbo:load', ()=>{
                    document.querySelectorAll('[data-dropdown]').forEach(setup);
                });
            })();
        </script>
    @endpush
@endonce
