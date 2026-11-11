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

            <nav>

                <ul class="flex flex-row flex-wrap gap-4">

                    <li>

                        <a href="{{ route('clients.edit', $client) }}" class="block bg-(--color-card) px-5 py-3 border-box rounded-t-lg">Client Details</a>

                    </li>

                    <li>

                        <a href="{{ route('passwords.index', ['client_id' => $client->id]) }}" class="block bg-(--color-card)/65 px-5 py-3 border-box rounded-t-lg transition-colors duration-200 hover:bg-(--color-card)/85">Credentials</a>

                    </li>

                    <li>

                        <a href="{{ route('jobs.index', ['client_id' => $client->id]) }}" class="block bg-(--color-card)/65 px-5 py-3 border-box rounded-t-lg transition-colors duration-200 hover:bg-(--color-card)/85">Jobs</a>

                    </li>

                </ul>

            </nav>

            <div class="p-4 sm:p-8 bg-(--color-card) shadow rounded-lg rounded-tl-none">
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

                        @php
                            $contacts = array_values(old('contacts', old('contacts_present') ? [] : $client->contacts->map->only(['id', 'name', 'role', 'email', 'phone', 'notes'])->all()));
                        @endphp

                        <section class="space-y-4" x-data="{
                            contacts: {{ Illuminate\Support\Js::from($contacts) }},
                            errors: {{ Illuminate\Support\Js::from($errors->getMessages()) }},
                            nextKey: {{ count($contacts) }},
                            init() { this.contacts = this.contacts.map((contact, index) => ({ ...contact, key: index, errorIndex: index })); },
                            addContact() {
                                this.contacts.push({ key: this.nextKey++, id: '', name: '', role: '', email: '', phone: '', notes: '' });
                                this.$nextTick(() => this.$refs.contactList.lastElementChild.querySelector('input[type=text]').focus());
                            },
                            fieldErrors(contact, field) { return this.errors['contacts.' + contact.errorIndex + '.' + field] || []; }
                        }">
                            <input type="hidden" name="contacts_present" value="1">
                            <div class="flex items-center justify-between gap-4">
                                <h2 class="text-lg font-semibold">{{ __('Client Contacts') }}</h2>
                                <x-secondary-button @click="addContact()">
                                    <span aria-hidden="true">+</span> {{ __('Add contact') }}
                                </x-secondary-button>
                            </div>
                            <x-input-error :messages="$errors->get('contacts')" />
                            <p x-show="contacts.length === 0" class="text-sm">{{ __('No contacts added. Use the + button to add a contact.') }}</p>

                            <div class="space-y-4" x-ref="contactList">
                                <template x-for="(contact, index) in contacts" :key="contact.key">
                                    <fieldset class="space-y-4 rounded-lg border border-(--color-border) p-4">
                                        <legend class="px-2 font-medium" x-text="'Contact ' + (index + 1)"></legend>
                                        <input type="hidden" :name="'contacts[' + index + '][id]'" x-model="contact.id">
                                        <template x-for="message in fieldErrors(contact, 'id')">
                                            <p class="text-sm text-red-600" x-text="message"></p>
                                        </template>

                                        @foreach (['name' => 'Name', 'role' => 'Role', 'email' => 'Email', 'phone' => 'Phone'] as $field => $label)
                                            <div>
                                                <x-input-label ::for="'contact-' + contact.key + '-{{ $field }}'" :value="__($label)" />
                                                <input :id="'contact-' + contact.key + '-{{ $field }}'"
                                                    :name="'contacts[' + index + '][{{ $field }}]'"
                                                    x-model="contact.{{ $field }}" class="block mt-1 w-full bg-(--color-card) border-(--color-border) text-(--color-text) focus:border-(--color-primary) focus:ring-(--color-primary) rounded-[7px] px-3 py-2.5 text-sm"
                                                    type="{{ $field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text') }}"
                                                    maxlength="255" @required($field !== 'phone') />
                                                <template x-for="message in fieldErrors(contact, '{{ $field }}')">
                                                    <p class="mt-2 text-sm text-red-600" x-text="message"></p>
                                                </template>
                                            </div>
                                        @endforeach

                                        <div>
                                            <x-input-label ::for="'contact-' + contact.key + '-notes'" :value="__('Notes')" />
                                            <textarea :id="'contact-' + contact.key + '-notes'"
                                                :name="'contacts[' + index + '][notes]'" x-model="contact.notes"
                                                class="block mt-1 w-full bg-(--color-card) border-(--color-border) text-(--color-text) focus:border-(--color-primary) focus:ring-(--color-primary) rounded-[7px] px-3 py-2.5 text-sm" rows="3" maxlength="65535"></textarea>
                                            <template x-for="message in fieldErrors(contact, 'notes')">
                                                <p class="mt-2 text-sm text-red-600" x-text="message"></p>
                                            </template>
                                        </div>
                                        <x-secondary-button @click="contacts.splice(index, 1)">{{ __('Remove contact') }}</x-secondary-button>
                                    </fieldset>
                                </template>
                            </div>
                            <p class="text-sm">{{ __('Contact changes are saved when you update the client.') }}</p>
                        </section>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Update Client') }}</x-primary-button>
                        </div>
                    </form>

                </div>

            </div>

        </section>
        
    </main>

</x-app-layout>
