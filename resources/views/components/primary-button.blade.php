<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-primary inline-flex min-h-11 items-center justify-center px-4 py-2 rounded-md font-semibold text-xs uppercase tracking-widest transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed']) }}>
    {{ $slot }}
</button>
