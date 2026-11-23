@props(['title'])

<div class="mb-4">
    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $slot }}</p>
</div>
