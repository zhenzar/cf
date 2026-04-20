<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Areas <span class="text-sm text-gray-500 font-normal">({{ $areas->count() }})</span>
            </h2>
            <div class="text-xs text-gray-500">
                No character selected &mdash; select one in the sidebar to track progress.
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
                                    @if ($area->url)
                                        <a href="{{ $area->url }}" target="_blank" rel="noopener"
                                           class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                            {{ $area->name }}
                                        </a>
                                    @else
                                        {{ $area->name }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">No areas found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
