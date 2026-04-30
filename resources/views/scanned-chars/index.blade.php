<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Scanned Characters
            <span class="text-sm text-gray-500 font-normal">({{ $chars->total() }})</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <!-- Search -->
            <form method="GET" action="{{ route('scanned-chars.index') }}" class="bg-white shadow-sm rounded-lg p-4 mb-4">
                <div class="flex gap-3">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search names..."
                           class="flex-1 rounded-md border-gray-300 shadow-sm">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Search
                    </button>
                    @if(request('q'))
                        <a href="{{ route('scanned-chars.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                            Clear
                        </a>
                    @endif
                </div>
            </form>

            <!-- Character List -->
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Race</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lvl</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Scanned At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($chars as $char)
                            <tr>
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    {{ $char->name }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    {{ $char->race ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    {{ $char->class ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    {{ $char->level ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 font-mono truncate max-w-md">
                                    {{ Str::limit($char->source_line, 60) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $char->created_at->format('Y-m-d H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    No characters scanned yet. Upload log files to populate.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $chars->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
