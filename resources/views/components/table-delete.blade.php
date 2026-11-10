@props(['resource', 'record', 'label'])
@php($modalName = 'delete-'.$resource.'-'.$record->getKey())

<x-danger-button type="button" {{ $attributes }} aria-label="Delete {{ $label }}" x-data=""
    x-on:click="$dispatch('open-modal', '{{ $modalName }}')">Delete</x-danger-button>

<x-confirm-dialog :name="$modalName" :title="__('Delete :label?', ['label' => $label])"
    :description="trim((string) $slot).' '.__('This action cannot be undone.')"
    :action="route($resource.'.destroy', $record)" />
