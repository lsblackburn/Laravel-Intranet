<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-(--color-text)">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-(--color-subtletext)">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <x-danger-button type="button"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Delete Account') }}</x-danger-button>

    <x-confirm-dialog name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()"
        :title="__('Are you sure you want to delete your account?')" :description="__('Once the account is deleted, all of its resources and data will be permanently deleted. Enter your password to confirm.')"
        :action="route('profile.destroy')" :confirm-text="__('Delete Account')">
            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full"
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>
    </x-confirm-dialog>
</section>
