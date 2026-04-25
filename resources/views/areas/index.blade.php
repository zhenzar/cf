<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Areas <span class="text-sm text-gray-500 font-normal">({{ $areas->count() }})</span>
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('areas.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">
                    + New Area
                </a>
                <div class="text-xs text-gray-500">
                    No character selected &mdash; select one in the sidebar to track progress.
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('areas.index') }}" class="flex flex-wrap gap-2">
                <input type="search" name="q" value="{{ $q }}" placeholder="Search area or realm..."
                       class="flex-1 min-w-[200px] border-gray-300 rounded-md shadow-sm text-sm">
                <button class="px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700">Search</button>
                @if ($q !== '')
                    <a href="{{ route('areas.index') }}" class="text-sm text-gray-600 hover:text-gray-900 self-center">Clear</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="text-left text-xs font-semibold text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-2 w-24">Level</th>
                            <th class="px-4 py-2 w-40">Realm</th>
                            <th class="px-4 py-2">Area</th>
                            <th class="px-4 py-2 w-20"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($areas as $area)
                            <tr>
                                <td class="px-4 py-2 font-mono text-xs text-gray-600">
                                    @if ($area->min_level === 1 && $area->max_level === 51)
                                        All
                                    @else
                                        {{ $area->min_level }}&ndash;{{ $area->max_level }}
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $area->realm }}</td>
                                <td class="px-4 py-2 font-medium">
                                    @if ($area->wiki_content || $area->url)
                                        <a href="{{ route('areas.wiki', $area) }}"
                                           class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                            {{ $area->name }}
                                        </a>
                                    @else
                                        {{ $area->name }}
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if ($area->area_explored)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800" title="Area Explorer">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                            Explorer
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No areas found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
