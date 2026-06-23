<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight break-words">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    
    <div class="py-6 sm:py-12">
        
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                
                
                <div class="p-4 sm:p-6 text-gray-900 dark:text-gray-100 text-sm sm:text-base">
                    {{ __("You're logged in!") }}
                </div>
                
            </div>
        </div>
    </div>
</x-app-layout>