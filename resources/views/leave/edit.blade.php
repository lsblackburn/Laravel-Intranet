<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-[--color-text] leading-tight">
            {{ __('Annual Leave') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 space-y-6">

            <div class="p-4 sm:p-8 bg-[--color-card] shadow sm:rounded-lg">
                <div class="max-w-xl">

                    <h2 class="text-3xl font-medium text-[--color-text]">
                        Annual Leave Request Details
                    </h2>

                    <form method="POST" action="{{ route('leave.update', ['leaveRequest' => $request->id]) }}" class="mt-6 space-y-6">

                        @csrf
                        @method('PUT')

                        @php
                            $start_date = \Carbon\Carbon::parse($request->start_date)->format('d-m-Y');
                            $end_date = \Carbon\Carbon::parse($request->end_date)->format('d-m-Y');
                        @endphp

                        <div class="mt-5">
                            <x-input-label for="start_date" :value="__('Start Date*')" />
                            <x-text-input id="start_date" class="block mt-1 w-full" type="text" name="start_date" :value="$start_date" required autofocus />
                            <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                        </div>
                        
                        <div class="mt-5">
                            <x-input-label for="end_date" :value="__('End Date*')" />
                            <x-text-input id="end_date" class="block mt-1 w-full" type="text" name="end_date" :value="$end_date" required autofocus />
                            <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                        </div>

                        <div class="block mt-5">
                            <label for="is_half_day" class="inline-flex items-center">
                                <input type="hidden" name="is_half_day" value="0">
                                <input id="is_half_day" value="1" type="checkbox" class="rounded bg-[--color-background] border-[--color-border] text-[--color-primary] shadow-sm focus:ring-[--color-primary]" name="is_half_day" {{ $request->is_half_day ? 'checked' : '' }}>
                                <span class="ms-2 text-sm text-[--color-subtletext]">{{ __('Is this a half-day leave?') }}</span>
                            </label>
                        </div>

                        <div class="mt-5">
                            <x-input-label for="reason" :value="__('Reason for Leave*')" />
                            <x-text-textarea id="reason" class="block mt-1 w-full" type="textarea" name="reason" :value="old('reason', $request->reason)" required autofocus />
                            <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                        </div>

                        <div class="mt-5">
                            <x-input-label for="additional_info" :value="__('Additional Information')" />
                            <x-text-textarea id="additional_info" class="block mt-1 w-full" type="textarea" name="additional_info" :value="old('additional_info', $request->additional_info)" />
                        </div>

                        <x-primary-button>
                            {{ __('Update Request') }}
                        </x-primary-button>

                    </form>

                </div>
                
            </div>

        </div>
    </div>
    
</x-app-layout>
