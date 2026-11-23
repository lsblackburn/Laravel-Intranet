<nav x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="if (open) { open = false; $refs.mobileToggle.focus() }" aria-label="Main navigation" class="relative z-40 border-b border-(--color-border) bg-(--color-card) shadow-[0_4px_24px_rgb(48_32_43_/_3%)]">
    @php
        $unreadNotifications = Auth::user()->unreadNotifications()->latest()->take(5)->get();
        $unreadNotificationCount = Auth::user()->unreadNotifications()->count();
    @endphp

    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex min-h-20 items-center justify-between gap-4">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" aria-label="{{ __('Go to dashboard') }}">
                        <x-application-logo class="block h-6 sm:h-7 md:h-8 lg:h-9 w-auto" />
                    </a>
                </div>

                <!-- Desktop navigation -->
                <div class="hidden items-center gap-1 lg:ms-8 lg:flex">
                    <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-3.5 py-2.5 text-sm font-medium whitespace-nowrap transition-colors hover:bg-(--color-surface-alt) hover:text-(--color-text) aria-expanded:bg-(--color-surface-alt) aria-expanded:text-(--color-text) {{ request()->routeIs('dashboard') ? 'bg-[color-mix(in_srgb,var(--color-primary)_11%,var(--color-card))] text-(--color-primary)' : 'text-(--color-subtletext)' }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg>
                        {{ __('Dashboard') }}
                    </a>
                    <x-navigation-menu label="Annual Leave" :active="request()->routeIs('leave.*')" :items="[
                        ['route' => 'leave.view', 'label' => 'View Your Leave', 'description' => 'Balances and leave history'],
                        ['route' => 'leave.form', 'label' => 'Request Leave', 'description' => 'Plan your next time away'],
                    ]" />
                    <x-navigation-menu label="Credentials" :active="request()->routeIs('clients.*', 'passwords.*')" :items="[
                        ['route' => 'clients.index', 'label' => 'Clients', 'description' => 'Browse your client directory'],
                        ['route' => 'passwords.index', 'label' => 'Passwords', 'description' => 'Manage saved credentials'],
                    ]" />
                    @if (auth()->user()->isAdmin())
                        <x-navigation-menu label="Admin Panel" :active="request()->routeIs('admin.*', 'jobs.*', 'job.*', 'invoice.*')" :items="[
                            ['route' => 'admin.leave-requests', 'label' => 'Leave Requests', 'description' => 'Review team leave requests'],
                            ['route' => 'admin.users', 'label' => 'User Management', 'description' => 'Manage your team'],
                            ['route' => 'jobs.index', 'label' => 'Jobs', 'description' => 'Projects and invoices'],
                        ]" />
                    @endif
                </div>
            </div>

            <!-- Notification and Settings Dropdowns -->
            <div class="hidden lg:flex lg:items-center lg:ms-4 lg:gap-3">
                <x-dropdown align="right" width="w-80">
                    <x-slot name="trigger">
                        <button type="button"
                            class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg text-(--color-subtletext) transition hover:bg-(--color-surface-alt) hover:text-(--color-text) focus:outline-none focus:ring-2 focus:ring-(--color-primary) focus:ring-offset-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                aria-hidden="true">
                                <path d="M10.268 21a2 2 0 0 0 3.464 0" />
                                <path
                                    d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326" />
                            </svg>
                            <span class="sr-only">{{ __('Notifications') }}</span>

                            @if ($unreadNotificationCount > 0)
                                <span
                                    class="absolute right-1 top-1 inline-flex min-h-4 min-w-4 items-center justify-center rounded-full bg-(--color-danger) px-1 text-[10px] font-semibold leading-none text-(--color-background)">
                                    {{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}
                                </span>
                            @endif
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="border-b border-(--color-border) px-4 py-3">
                            <p class="text-sm font-semibold text-(--color-text)">{{ __('Notifications') }}</p>
                            <p class="mt-1 text-xs text-(--color-subtletext)">
                                {{ $unreadNotificationCount }}
                                {{ \Illuminate\Support\Str::plural('unread item', $unreadNotificationCount) }}
                            </p>
                        </div>

                        @forelse ($unreadNotifications as $notification)
                            <x-notification-item :notification="$notification" />
                        @empty
                            <p class="px-4 py-6 text-sm text-(--color-subtletext)">
                                {{ __('No new notifications.') }}
                            </p>
                        @endforelse
                    </x-slot>
                </x-dropdown>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-3 py-2 border border-(--color-border) text-sm leading-4 font-medium rounded-xl text-(--color-subtletext) hover:text-(--color-text) hover:bg-(--color-surface-alt) focus:outline-none transition ease-in-out duration-150">
                            <span class="mr-[9px] inline-grid size-[30px] place-items-center rounded-full bg-(--color-surface-alt) font-semibold text-(--color-primary)" aria-hidden="true">{{ mb_substr(Auth::user()->name, 0, 1) }}</span><div class="max-w-32 truncate">{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="flex items-center lg:hidden">
                <button type="button" x-ref="mobileToggle" @click="open = ! open" :aria-expanded="open" aria-controls="mobile-navigation" aria-label="{{ __('Toggle navigation') }}"
                    class="inline-flex size-11 items-center justify-center border border-(--color-border) bg-(--color-surface-alt) rounded-xl text-(--color-subtletext) hover:text-(--color-text) hover:bg-(--color-surface-alt) focus:outline-none focus:bg-(--color-surface-alt)  focus:text-(--color-text)  transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{ 'hidden': open, 'inline-flex': !open }" class="inline-flex"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{ 'hidden': !open, 'inline-flex': open }" class="hidden" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile workspace menu -->
    <div id="mobile-navigation" x-cloak x-show="open"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
        class="absolute inset-x-0 top-full max-h-[calc(100dvh-5rem)] overflow-y-auto overscroll-contain border-b border-(--color-border) bg-(--color-background) shadow-xl lg:hidden">
        <div class="mx-auto max-w-3xl space-y-5 px-4 py-5 sm:px-6">
            <div class="flex items-center justify-between px-1">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-(--color-subtletext)">Your workspace</p>
                <span class="text-xs text-(--color-subtletext)">{{ now()->format('D, j M') }}</span>
            </div>

            <x-mobile-navigation-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="dashboard" label="Dashboard" description="Your day at a glance" />

            <section aria-label="Annual leave">
                <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wider text-(--color-subtletext)">Annual leave</h2>
                <div class="grid grid-cols-1 gap-2 min-[380px]:grid-cols-2">
                    <x-mobile-navigation-link :href="route('leave.view')" :active="request()->routeIs('leave.view', 'leave.edit')" icon="calendar" label="Your leave" description="Balances &amp; history" />
                    <x-mobile-navigation-link :href="route('leave.form')" :active="request()->routeIs('leave.form')" icon="plus" label="Request leave" description="Plan your time away" />
                </div>
            </section>

            <section aria-label="Credentials">
                <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wider text-(--color-subtletext)">Credentials</h2>
                <div class="grid grid-cols-1 gap-2 min-[380px]:grid-cols-2">
                    <x-mobile-navigation-link :href="route('clients.index')" :active="request()->routeIs('clients.*')" icon="users" label="Clients" description="Client directory" />
                    <x-mobile-navigation-link :href="route('passwords.index')" :active="request()->routeIs('passwords.*')" icon="lock" label="Passwords" description="Saved credentials" />
                </div>
            </section>

            @if (auth()->user()->isAdmin())
                <section aria-label="Administration">
                    <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wider text-(--color-subtletext)">Administration</h2>
                    <div class="space-y-2">
                        <x-mobile-navigation-link :href="route('admin.leave-requests')" :active="request()->routeIs('admin.leave-requests*')" icon="calendar" label="Leave requests" />
                        <x-mobile-navigation-link :href="route('admin.users')" :active="request()->routeIs('admin.users*')" icon="users" label="User management" />
                        <x-mobile-navigation-link :href="route('jobs.index')" :active="request()->routeIs('jobs.*', 'job.*', 'invoice.*')" icon="briefcase" label="Jobs" />
                    </div>
                </section>
            @endif

            <details class="overflow-hidden rounded-2xl border border-(--color-border) bg-(--color-card)">
                <summary class="flex min-h-14 cursor-pointer items-center gap-3 px-4 py-3 text-sm font-medium text-(--color-text)">
                    <span>Notifications</span>
                    <span class="ml-auto rounded-full bg-(--color-surface-alt) px-2.5 py-1 text-xs text-(--color-subtletext)">{{ $unreadNotificationCount }} unread</span>
                    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 9 6 6 6-6" /></svg>
                </summary>
                <div class="border-t border-(--color-border)">
                    @forelse ($unreadNotifications as $notification)
                        <x-notification-item :notification="$notification" />
                    @empty
                        <p class="px-4 py-5 text-sm text-(--color-subtletext)">You're all caught up.</p>
                    @endforelse
                </div>
            </details>

            <div class="rounded-2xl border border-(--color-border) bg-(--color-card) p-4">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-(--color-surface-alt) text-lg font-semibold text-(--color-primary)" aria-hidden="true">{{ mb_substr(Auth::user()->name, 0, 1) }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-(--color-text)">{{ Auth::user()->name }}</p>
                        <p class="truncate text-xs text-(--color-subtletext)">{{ Auth::user()->email }}</p>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2 border-t border-(--color-border) pt-3">
                    <a href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif class="flex min-h-11 items-center justify-center rounded-xl bg-(--color-surface-alt) px-3 text-sm font-medium text-(--color-text) transition hover:text-(--color-primary)">My profile</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="min-h-11 w-full rounded-xl px-3 text-sm font-medium text-(--color-danger-text) transition hover:bg-(--color-surface-alt)">Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</nav>
