<div class="bg-(--color-card) shadow rounded-lg">
    <div class="grid gap-8 p-4 sm:p-8 lg:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">
        <section aria-labelledby="add-project-type-heading">
            <header>
                <h2 id="add-project-type-heading" class="text-lg font-medium text-(--color-text)">
                    {{ __('Add new project type') }}
                </h2>

                <p class="mt-1 text-sm text-(--color-subtletext)">
                    {{ __('Enter a project type name and add it to the list.') }}
                </p>
            </header>

            <form method="post" action="{{ route('admin.project-types.create') }}" class="mt-6 space-y-6">
                @csrf

                <div>
                    <x-input-label for="name" :value="__('Project Type')" />
                    <x-text-input id="name" class="block mt-1 w-full" type="text" required maxlength="255" name="name"
                        :value="old('_project_type_id') ? '' : old('name')" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Add') }}</x-primary-button>
                </div>
            </form>
        </section>

        <section aria-labelledby="project-types-list-heading">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 id="project-types-list-heading" class="text-lg font-medium text-(--color-text)">
                        {{ __('Project types') }}
                    </h2>

                    <p class="mt-1 text-sm text-(--color-subtletext)">
                        {{ __('Project types currently available when assigning to clients.') }}
                    </p>
                </div>

                <p
                    class="inline-flex w-fit items-center rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-alt)] px-3 py-1 text-xs font-semibold uppercase tracking-widest text-[var(--color-subtletext)]">
                    <span>{{ $types->total() }}</span>
                    <span class="ml-1">
                        {{ \Illuminate\Support\Str::plural('project type', $types->total()) }}
                    </span>
                </p>
            </header>

            <div class="mt-6 overflow-hidden rounded-lg border border-[var(--color-border)] bg-[var(--color-card)]">
                @if ($types->count() > 0)
                    <ul role="list" class="divide-y divide-[var(--color-border)]">
                        @foreach ($types as $type)
                            <li
                                class="flex flex-wrap items-center justify-between gap-4 px-4 py-4 transition hover:bg-[var(--color-surface-alt)] sm:px-5">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[var(--color-primary)] text-sm font-semibold uppercase text-[var(--color-background)]"
                                        aria-hidden="true">
                                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($type->name, 0, 1)) }}
                                    </span>

                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-[var(--color-text)]">
                                            {{ $type->name }}
                                        </p>

                                        <p class="mt-0.5 text-xs text-[var(--color-subtletext)]">
                                            {{ __('Project type') }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-3">
                                    @php($updateErrors = $errors->getBag('updateProjectType'.$type->id))
                                    <details @if ($updateErrors->any()) open @endif>
                                        <summary class="cursor-pointer text-sm font-medium text-[var(--color-primary)]">{{ __('Edit') }}<span class="sr-only"> {{ $type->name }}</span></summary>
                                        <form method="post" action="{{ route('admin.project-types.update', $type) }}" class="mt-3 space-y-3">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="_project_type_id" value="{{ $type->id }}">
                                            <x-input-label for="project-type-name-{{ $type->id }}" :value="__('Project type name')" />
                                            <x-text-input id="project-type-name-{{ $type->id }}" name="name" type="text" required maxlength="255"
                                                class="block w-full" :value="$updateErrors->any() ? old('name', $type->name) : $type->name" />
                                            <x-input-error :messages="$updateErrors->get('name')" />
                                            <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                                        </form>
                                    </details>
                                    <button type="button" x-data=""
                                        x-on:click.prevent="$dispatch('open-modal', 'confirm-project-type-deletion-{{ $type->id }}')"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-[var(--color-subtletext)] transition hover:bg-[var(--color-surface-alt)] hover:text-[var(--color-danger)] focus:outline-none focus:ring-2 focus:ring-[var(--color-danger)] focus:ring-offset-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" aria-hidden="true">
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
                                            <path d="M3 6h18" />
                                            <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                        </svg>
                                        <span class="sr-only">{{ __('Delete project type') }}</span>
                                    </button>

                                    <x-confirm-dialog name="confirm-project-type-deletion-{{ $type->id }}"
                                        :title="__('Delete project type?')" :description="__(
                                            'This project type will be permanently removed.',
                                        )" :action="route('admin.project-types.delete', $type)" method="delete">
                                        <p class="text-sm font-medium text-center text-[var(--color-text)]">
                                            {{ $type->name }}
                                        </p>
                                    </x-confirm-dialog>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <section class="px-4 py-10 text-center sm:px-6" aria-labelledby="empty-project-types-heading">
                        <div
                            class="mx-auto flex h-11 w-11 items-center justify-center rounded-lg bg-[var(--color-surface-alt)] text-[var(--color-subtletext)]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                aria-hidden="true">
                                <path d="M3 21h18" />
                                <path d="M5 21V7l8-4v18" />
                                <path d="M19 21V11l-6-4" />
                                <path d="M9 9v.01" />
                                <path d="M9 12v.01" />
                                <path d="M9 15v.01" />
                                <path d="M9 18v.01" />
                            </svg>
                        </div>

                        <h3 id="empty-project-types-heading" class="mt-4 text-sm font-semibold text-[var(--color-text)]">
                            {{ __('No project types added') }}
                        </h3>

                        <p class="mt-1 text-sm text-[var(--color-subtletext)]">
                            {{ __('Create your first project type using the form on this page.') }}
                        </p>
                    </section>
                @endif
            </div>
        </section>
    </div>
</div>
