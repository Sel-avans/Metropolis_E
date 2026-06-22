@props(['active'])

@php
$classes = ($active ?? false)
            ? 'app-navbar-link is-active inline-flex items-center px-1 pt-1 border-b-2 border-indigo-600 text-sm font-medium leading-5 !text-gray-100 no-underline focus:outline-none focus:border-indigo-500 transition duration-150 ease-in-out'
            : 'app-navbar-link inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 !text-gray-400 hover:!text-gray-300 hover:border-gray-700 no-underline focus:outline-none focus:!text-gray-300 focus:border-gray-700 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
