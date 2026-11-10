<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-(--color-text) sm:text-4xl">
            {{ __($password->client->name . ' Password') }}
        </h1>
    </x-slot>

    <main class="py-6">

        <section class="max-w-7xl mx-auto py-3.5 px-4 sm:px-6 lg:px-8">

            @if ($client)
                <x-primary-link class="mb-5" href="{{ route('passwords.index', ['client_id' => $client->id]) }}">Back to Password List<span
                    aria-hidden="true">&#8599;</span></x-primary-link>
            @else
                <x-primary-link class="mb-5" href="{{ route('passwords.index') }}">Back to Password List<span
                    aria-hidden="true">&#8599;</span></x-primary-link>
            @endif

            <div class="p-4 sm:p-8 bg-(--color-card) shadow rounded-lg">
                <div class="w-full">

                    <form method="post" action="{{ route('passwords.update', $password->id) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

                            <div class="space-y-6">

                                <div>
                                    <x-input-label for="name" :value="__('Password Name')" />
                                    <x-text-input id="name" class="block mt-1 w-full" type="text" required name="name"
                                        :value="old('name', $password->name )" />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="username" :value="__('Username')" />
                                    <x-text-input id="username" class="block mt-1 w-full" type="text" required name="username"
                                        :value="old('username', $password->username )" />
                                    <x-input-error :messages="$errors->get('username')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="password" :value="__('Password')" />
                                    <x-text-input id="password" class="block mt-1 w-full" type="text" required name="password"
                                        :value="old('password', $password->password )" />
                                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="client_id" :value="__('Client')" />
                                    <x-select-dropdown id="client_id" name="client_id" class="block mt-1 w-full"
                                        :options="$clients" :value="old('client_id', $password->client_id)" :placeholder="__('Select a client')" />
                                    <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
                                </div>

                            </div>

                            <div>
                                <x-input-label for="notes" :value="__('Notes')" />
                                <x-text-textarea id="notes" class="block mt-1 w-full" type="text" name="notes"
                                    :value="old('notes', $password->notes)" />
                                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                            </div>

                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Update Password') }}</x-primary-button>
                        </div>

                    </form>

                </div>

            </div>

        </section>
        
    </main>

</x-app-layout>
