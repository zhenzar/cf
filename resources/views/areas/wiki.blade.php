<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $area->name }}
            </h2>
            <div class="flex items-center gap-4">
                @if($area->url)
                    <a href="{{ $area->url }}" target="_blank" rel="noopener noreferrer"
                       class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        View on Original Wiki &rarr;
                    </a>
                @endif
                <a href="{{ route('areas.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                    &larr; Back to Areas
                </a>
            </div>
        </div>
        @if($area->realm)
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Realm: {{ $area->realm }}</p>
        @endif
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="wiki-content prose dark:prose-invert max-w-none">
                        {!! $area->wiki_content !!}
                    </div>

                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 text-sm text-gray-500">
                        @if($area->wiki_fetched_at)
                            <p>Last fetched: {{ \Carbon\Carbon::parse($area->wiki_fetched_at)->format('Y-m-d H:i:s') }}</p>
                        @endif
                        @if($area->wiki_title && $area->wiki_title !== $area->name)
                            <p>Original title: {{ $area->wiki_title }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
