<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-(--color-text) sm:text-4xl">
            {{ __('Edit a Job') }}
        </h1>
    </x-slot>

    <main class="py-6">

        <section class="max-w-7xl mx-auto py-3.5 px-4 sm:px-6 lg:px-8">

            @if ($client)
                <x-primary-link class="mb-5" href="{{ route('jobs.index', ['client_id' => $client->id]) }}">Back to Jobs
                    List<span aria-hidden="true">&#8599;</span></x-primary-link>
            @else
                <x-primary-link class="mb-5" href="{{ route('jobs.index') }}">Back to Jobs List<span
                        aria-hidden="true">&#8599;</span></x-primary-link>
            @endif

            <nav>

                <ul class="flex flex-row flex-wrap gap-4">

                    <li>

                        <a href="{{ route('jobs.edit', $job) }}"
                            class="block bg-(--color-card) px-5 py-3 border-box rounded-t-lg">Job
                            Details</a>

                    </li>

                    <li>

                        <a href=""
                            class="block bg-(--color-card)/65 px-5 py-3 border-box rounded-t-lg transition-colors duration-200 hover:bg-(--color-card)/85">Invoice
                            Amounts</a>

                    </li>

                </ul>

            </nav>

            <div class="p-4 sm:p-8 bg-(--color-card) shadow rounded-lg rounded-tl-none">
                <div class="w-full">

                    <form method="post" action="{{ route('jobs.store') }}" class="space-y-6">
                        @csrf

                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

                            <div class="space-y-6">

                                <div>
                                    <x-input-label for="title" :value="__('Job Title*')" />
                                    <x-text-input id="title" class="block mt-1 w-full" type="text" required
                                        name="title" :value="old('name', $job->title)" />
                                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="project_type" :value="__('Project Type*')" />
                                    <x-select-dropdown id="project_type" name="project_type" class="block mt-1 w-full"
                                        :options="$projectTypes" :value="old('project_type', $job->project_type)" :placeholder="__('Select a project type')" />
                                    <x-input-error :messages="$errors->get('project_type')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="client_id" :value="__('Client*')" />
                                    <x-select-dropdown id="client_id" name="client_id" class="block mt-1 w-full"
                                        :options="$clients" :value="old('client_id', $job->client_id) ?? $client?->id" :placeholder="__('Select a client')" />
                                    <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
                                </div>

                            </div>

                            <div>

                                <div>
                                    <x-input-label for="notes" :value="__('Notes')" />
                                    <x-text-textarea id="notes" class="block mt-1 w-full" type="text"
                                        name="notes" :value="old('notes', $job->notes)" />
                                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                                </div>

                                <div class="block mt-5">
                                    <label for="completed" class="inline-flex items-center">
                                        <input type="hidden" name="completed" value="0">
                                        <input id="completed" value="1" type="checkbox"
                                            class="rounded bg-(--color-background) border-(--color-border) text-(--color-primary) shadow-sm focus:ring-(--color-primary)"
                                            name="completed" x-model="completed">
                                        <span
                                            class="ms-2 text-sm text-(--color-subtletext)">{{ __('Job completed?') }}</span>
                                    </label>
                                </div>

                                <div class="block mt-5">
                                    <label for="active" class="inline-flex items-center">
                                        <input type="hidden" name="active" value="0">
                                        <input id="active" value="1" type="checkbox"
                                            @checked(old('active', $job->active) == 1)
                                            class="rounded bg-(--color-background) border-(--color-border) text-(--color-primary) accent-(--color-primary) shadow-sm focus:ring-(--color-primary)"
                                            name="active">
                                        <span
                                            class="ms-2 text-sm text-(--color-subtletext)">{{ __('Job active?') }}</span>
                                    </label>
                                </div>

                            </div>

                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Update') }}</x-primary-button>
                        </div>

                    </form>

                </div>

            </div>

        </section>

    </main>

</x-app-layout>
