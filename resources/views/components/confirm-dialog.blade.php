@props([
    'name',
    'title',
    'description',
    'action',
    'method' => 'delete',
    'confirmText' => __('Delete'),
    'cancelText' => __('Cancel'),
    'show' => false,
])

<x-modal :name="$name" :show="$show" maxWidth="md" centered focusable
    :aria-labelledby="$name.'-title'" :aria-describedby="$name.'-description'">
    <div class="relative p-6">
        <button type="button" x-on:click="$dispatch('close')"
            class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg text-[var(--color-subtletext)] transition hover:bg-[var(--color-surface-alt)] hover:text-[var(--color-text)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:ring-offset-2">
            <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 17.94 6M18 18 6.06 6" />
            </svg>
            <span class="sr-only">{{ __('Close modal') }}</span>
        </button>

        <form method="post" action="{{ $action }}">
            @csrf
            @if (strtolower($method) !== 'post')
                @method($method)
            @endif

            <div class="text-center">
                <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-[var(--color-surface-alt)] text-[var(--color-danger)]">
                    <svg class="h-8 w-8" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 13V8m0 8h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>

                <h2 id="{{ $name }}-title" class="mb-2 break-words text-lg font-semibold text-[var(--color-text)]">{{ $title }}</h2>
                <p id="{{ $name }}-description" class="mb-6 break-words text-sm leading-6 text-[var(--color-subtletext)]">{{ $description }}</p>

                @if ($slot->isNotEmpty())
                    <div class="mb-6 text-left">{{ $slot }}</div>
                @endif

                <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <x-danger-button>{{ $confirmText }}</x-danger-button>
                    <x-secondary-button x-on:click="$dispatch('close')">{{ $cancelText }}</x-secondary-button>
                </div>
            </div>
        </form>
    </div>
</x-modal>
