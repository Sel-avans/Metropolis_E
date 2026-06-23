@props(['active'])

@php
$classes = ($active ?? false)
            ? 'app-navbar-link is-active block w-full ps-3 pe-4 py-2 border-l-4 border-indigo-600 text-start text-base font-medium !text-indigo-300 !bg-indigo-900/50 no-underline focus:outline-none focus:!text-indigo-200 focus:!bg-indigo-900 focus:border-indigo-500 transition duration-150 ease-in-out'
            : 'app-navbar-link block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium !text-gray-400 hover:!text-gray-200 hover:!bg-gray-700 hover:border-gray-600 no-underline focus:outline-none focus:!text-gray-200 focus:!bg-gray-700 focus:border-gray-600 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
