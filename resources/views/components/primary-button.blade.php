<button
    {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-[42px] items-center justify-center gap-4 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 px-[18px] py-2.5 bg-(--color-primary) border border-transparent rounded-lg font-medium text-sm text-(--color-background) normal-case tracking-normal hover:bg-(--color-primary-hover) focus:bg-(--color-primary-hover) active:bg-(--color-primary-hover) focus:outline-none focus:ring-2 focus:ring-(--color-primary) focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
