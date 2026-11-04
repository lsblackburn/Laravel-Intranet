<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-(--color-text) sm:text-4xl">
            {{ __('List of passwords') }}
        </h1>
    </x-slot>

    <main class="py-6">

        <section class="max-w-7xl mx-auto py-3.5 px-4 sm:px-6 lg:px-8">

            <div class="flex justify-between items-end gap-5">

                <x-primary-link class="mb-5" href="{{ route('passwords.create') }}">Add Password<span
                    aria-hidden="true">&#8599;</span></x-primary-link>


                <form id="password-search-form" action="{{ route('passwords.index') }}" method="GET" role="search" class="mb-5 ml-auto md:w-[35%] w-[50%]">
                    <label for="password-search" class="block mb-2 font-medium">Search passwords</label>
                    <x-text-input id="password-search" name="search" type="search" class="w-full sm:max-w-lg"
                        :value="$search" maxlength="255" placeholder="Search by name, client or username…"
                        aria-controls="password-results" autocomplete="off" />
                    <noscript><x-primary-button class="mt-2">Search</x-primary-button></noscript>
                </form>

            </div>


            <p id="password-search-status" role="status" class="mb-3 text-sm"></p>

            <div id="password-results" aria-busy="false">
                @include('auth.credentials.passwords.partials.results')
            </div>

        </section>
        
    </main>

    <script>
        (() => {
            const form = document.getElementById('password-search-form');
            const input = document.getElementById('password-search');
            const results = document.getElementById('password-results');
            const status = document.getElementById('password-search-status');
            let timer;
            let controller;
            let version = 0;

            function cancelPending() {
                clearTimeout(timer);
                controller?.abort();
                version++;
            }

            function searchUrl() {
                const url = new URL(form.action);
                if (input.value.trim()) url.searchParams.set('search', input.value.trim());
                return url;
            }

            async function load(url) {
                cancelPending();
                const currentVersion = version;
                controller = new AbortController();
                results.setAttribute('aria-busy', 'true');
                status.textContent = 'Searching…';

                try {
                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                        signal: controller.signal,
                        cache: 'no-store',
                    });
                    if (response.redirected) {
                        window.location.assign(response.url);
                        return;
                    }
                    if (!response.ok) throw new Error('Search failed');
                    const html = await response.text();
                    if (currentVersion !== version) return;
                    results.innerHTML = html;
                    window.history.replaceState(null, '', url);
                    status.textContent = 'Results updated.';
                } catch (error) {
                    if (error.name !== 'AbortError' && currentVersion === version) {
                        status.textContent = 'Unable to load results. Please try searching again.';
                    }
                } finally {
                    if (currentVersion === version) results.setAttribute('aria-busy', 'false');
                }
            }

            input.addEventListener('input', () => {
                cancelPending();
                results.setAttribute('aria-busy', 'true');
                status.textContent = 'Searching…';
                timer = setTimeout(() => load(searchUrl()), 300);
            });

            form.addEventListener('submit', event => {
                event.preventDefault();
                load(searchUrl());
            });

            results.addEventListener('click', event => {
                const link = event.target.closest('nav[aria-label="Pagination Navigation"] a');
                if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
                event.preventDefault();
                load(new URL(link.href));
            });
        })();
    </script>
</x-app-layout>

