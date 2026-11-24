@props(['label', 'projectTypes' => null])

<div x-data="ajaxList" @popstate.window="restore()">
    <form x-ref="filters" method="GET" action="{{ url()->current() }}" @submit.prevent="load()"
        class="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-(--color-border) bg-(--color-card) p-4">
        @if(request()->filled('client_id'))
            <input type="hidden" name="client_id" value="{{ request('client_id') }}">
        @endif
        <div class="min-w-48 flex-1">
            <label for="list-search" class="mb-2 block text-sm font-medium">Search {{ strtolower($label) }}</label>
            <input id="list-search" name="search" type="search" maxlength="255" value="{{ request('search') }}"
                @input="schedule()" placeholder="Search {{ strtolower($label) }}…"
                class="min-h-11 w-full rounded-lg border border-(--color-border) bg-(--color-background) px-3 text-sm text-(--color-text) focus:border-(--color-primary) focus:ring-(--color-primary)">
        </div>
        @if($projectTypes !== null)
            <div class="w-full sm:w-60">
                <label for="project-type-filter" class="mb-2 block text-sm font-medium">Project type</label>
                <select id="project-type-filter" name="project_type" @change="load()"
                    class="min-h-11 w-full rounded-lg border border-(--color-border) bg-(--color-background) px-3 text-sm text-(--color-text) focus:border-(--color-primary) focus:ring-(--color-primary)">
                    <option value="">All project types</option>
                    @foreach($projectTypes as $type)
                        <option value="{{ $type }}" @selected(request('project_type') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        {{ $filters ?? '' }}
        <button type="submit" class="min-h-11 rounded-lg bg-(--color-primary) px-4 text-sm font-medium text-(--color-background)">Search</button>
    </form>
    <p x-cloak x-show="loading" role="status" class="mb-3 text-sm text-(--color-subtletext)">Updating results…</p>
    <p x-cloak x-show="error" role="alert" class="mb-3 text-sm text-(--color-danger-text)">
        Could not load results. <button type="button" @click="load(pendingUrl)" class="underline">Try again</button>
    </p>
    <div x-ref="results" :aria-busy="loading" @click="paginate($event)">
        {{ $slot }}
    </div>
</div>
