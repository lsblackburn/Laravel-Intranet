<button
    {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex min-h-[42px] items-center gap-4 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 justify-center rounded-lg border border-[var(--color-border)] bg-[var(--color-card)] px-[18px] py-2.5 text-sm font-medium normal-case tracking-normal text-[var(--color-text)] shadow-sm transition ease-in-out duration-150 hover:bg-[var(--color-surface-alt)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50']) }}>
    {{ $slot }}
</button>
