@props(['label', 'active' => false, 'items' => []])

<div class="relative" x-data="{ expanded: false }" @click.outside="expanded = false"
    @keydown.escape.stop="expanded = false; $refs.trigger.focus()"
    @focusout="if (!$el.contains($event.relatedTarget)) expanded = false">
    <button type="button" x-ref="trigger" @click="expanded = !expanded"
        @keydown.arrow-down.prevent="expanded = true; $nextTick(() => $refs.panel.querySelector('a').focus())"
        :aria-expanded="expanded" aria-controls="navigation-{{ Str::slug($label) }}"
        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-3.5 py-2.5 text-sm font-medium whitespace-nowrap transition-colors hover:bg-(--color-surface-alt) hover:text-(--color-text) aria-expanded:bg-(--color-surface-alt) aria-expanded:text-(--color-text) {{ $active ? 'bg-[color-mix(in_srgb,var(--color-primary)_11%,var(--color-card))] text-(--color-primary)' : 'text-(--color-subtletext)' }}">
        {{ $label }}
        <svg aria-hidden="true" class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': expanded }"
            viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>
    <div id="navigation-{{ Str::slug($label) }}" x-ref="panel" x-cloak x-show="expanded" x-transition.opacity
        class="absolute start-0 top-full z-50 mt-3 w-72 rounded-2xl border border-(--color-border) bg-(--color-card) p-2 shadow-xl">
        <p class="px-3 pb-2 pt-2 text-xs font-semibold uppercase tracking-wider text-(--color-subtletext)">{{ $label }}</p>
        @foreach ($items as $item)
            <a href="{{ route($item['route']) }}"
                @if(request()->routeIs($item['route'])) aria-current="page" @endif
                class="aria-[current=page]:bg-[color-mix(in_srgb,var(--color-primary)_11%,var(--color-card))] aria-[current=page]:text-(--color-primary) block rounded-xl px-3 py-3 transition hover:bg-(--color-surface-alt)">
                <span class="block text-sm font-semibold text-(--color-text)">{{ $item['label'] }}</span>
                <span class="mt-1 block text-xs text-(--color-subtletext)">{{ $item['description'] }}</span>
            </a>
        @endforeach
    </div>
</div>
