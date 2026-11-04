<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-(--color-text) sm:text-4xl">
            {{ __('Edit Client Details') }}
        </h1>
    </x-slot>

    <main class="py-6">

        <section class="max-w-7xl mx-auto py-3.5 px-4 sm:px-6 lg:px-8">

            <x-primary-link class="mb-5" href="{{ route('clients.index') }}">Back to Client List<span
                    aria-hidden="true">&#8599;</span></x-primary-link>

            <div class="p-4 sm:p-8 bg-(--color-card) shadow rounded-lg">
                <div class="max-w-xl">

                    <form method="post" action="{{ route('clients.update', $client) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="name" :value="__('Client Name')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" required name="name"
                                :value="old('name', $client->name)" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Update Client') }}</x-primary-button>
                        </div>
                    </form>

                </div>

            </div>

        </section>
        
    </main>

</x-app-layout>
