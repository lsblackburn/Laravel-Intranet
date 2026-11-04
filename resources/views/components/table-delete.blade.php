@props(['resource', 'record', 'label'])
@php($modalName = 'delete-'.$resource.'-'.$record->getKey())

<button type="button" class="text-red-600 underline" aria-label="Delete {{ $label }}" x-data="" x-on:click="$dispatch('open-modal', '{{ $modalName }}')">Delete</button>

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
