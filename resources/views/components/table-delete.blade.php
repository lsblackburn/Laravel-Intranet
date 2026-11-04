@props(['resource', 'record', 'label'])
@php($modalName = 'delete-'.$resource.'-'.$record->getKey())

<button type="button" {{ $attributes->merge(['class' => 'inline-flex min-h-[42px] items-center justify-center gap-4 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 px-[18px] py-2.5 bg-red-600 border border-transparent rounded-lg font-medium text-sm text-white normal-case tracking-normal hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150']) }} aria-label="Delete {{ $label }}" x-data="" x-on:click="$dispatch('open-modal', '{{ $modalName }}')">Delete</button>

<x-modal :name="$modalName" maxWidth="lg" focusable :aria-labelledby="$modalName.'-title'" :aria-describedby="$modalName.'-description'">
    <form method="POST" action="{{ route($resource.'.destroy', $record) }}" class="p-6 text-left sm:p-8">
        @csrf
        @method('DELETE')
        <h2 id="{{ $modalName }}-title" class="text-xl font-bold">Delete {{ $label }}?</h2>
        <p id="{{ $modalName }}-description" class="mt-3 break-words text-sm md:text-base">
            {{ $slot }}
            This action cannot be undone.
        </p>
        <div class="mt-6 flex flex-wrap justify-end gap-3">
            <x-secondary-button x-on:click="$dispatch('close')">Cancel</x-secondary-button>
            <x-danger-button>Delete</x-danger-button>
        </div>
    </form>
</x-modal>
