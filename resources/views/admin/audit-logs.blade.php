<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-(--color-text) sm:text-4xl">
            {{ __('View Audit Logs') }}
        </h1>
    </x-slot>

    <main class="py-6">

        <section class="max-w-7xl mx-auto py-3.5 px-4 sm:px-6 lg:px-8">

            <x-ajax-list label="Logs">
                <x-slot name="filters">
                    @foreach (['from' => 'From', 'to' => 'To'] as $field => $label)
                        <div>
                            <label for="logs-{{ $field }}" class="mb-2 block text-sm font-medium">{{ $label }} ({{ config('app.timezone') }})</label>
                            <input id="logs-{{ $field }}" name="{{ $field }}" type="datetime-local"
                                value="{{ request($field) }}" @change="load()"
                                class="min-h-11 rounded-lg border border-(--color-border) bg-(--color-background) px-3 text-sm text-(--color-text)">
                            <x-input-error :messages="$errors->get($field)" class="mt-2" />
                        </div>
                    @endforeach
                </x-slot>
                @include('admin.partials.audit-log-results')
            </x-ajax-list>

        </section>
        
    </main>

</x-app-layout>
