<button
    {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-[42px] items-center justify-center gap-4 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 px-[18px] py-2.5 bg-red-600 border border-transparent rounded-lg font-medium text-sm text-white normal-case tracking-normal hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
