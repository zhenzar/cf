<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                World Map
            </h2>
            <div class="text-sm text-gray-500">
                Map of Thera from <a href="http://wiki.qhcf.net/index.php?title=WorldMap" target="_blank" class="text-indigo-600 hover:underline">QHCF Wiki</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-6">
                    @if ($mapContent)
                        <div class="overflow-x-auto">
                            <div class="font-mono text-xs leading-tight text-gray-800 whitespace-pre">{!! $mapContent !!}</div>
                        </div>
                    @else
                        <div class="text-center py-12 text-gray-500">
                            <p class="mb-2">Unable to fetch map from wiki.</p>
                            <a href="http://wiki.qhcf.net/index.php?title=WorldMap" target="_blank" class="text-indigo-600 hover:underline">
                                View on QHCF Wiki
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-6 bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Areas on Map</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Areas listed in the database that appear on the world map.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 text-sm">
                    @foreach ($areas as $area)
                        <div class="flex items-center gap-2">
                            @if ($area->wiki_content || $area->url)
                                <a href="{{ route('areas.wiki', $area) }}" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                    {{ $area->name }}
                                </a>
                            @else
                                <span class="text-gray-700">{{ $area->name }}</span>
                            @endif
                            <span class="text-xs text-gray-500">({{ $area->levelRangeLabel() }})</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
