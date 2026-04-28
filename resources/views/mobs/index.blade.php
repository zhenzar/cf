<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Mobs
                <span class="text-sm text-gray-500 font-normal">({{ $mobs->total() }})</span>
            </h2>
            <a href="{{ route('mobs.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">
                + Add Mob
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <!-- Filters -->
            <form method="GET" action="{{ route('mobs.index') }}" class="bg-white shadow-sm rounded-lg p-4 mb-4 space-y-4">
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex flex-col">
                        <label class="text-xs font-medium text-gray-600 mb-1">Area</label>
                        <select name="area_id" class="border-gray-300 rounded-md shadow-sm text-sm min-w-[180px]">
                            <option value="">All Areas</option>
                            @foreach($areas as $id => $name)
                                <option value="{{ $id }}" {{ request('area_id') == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-col">
                        <label class="text-xs font-medium text-gray-600 mb-1">Sort By</label>
                        <select name="sort" class="border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="name" {{ request('sort') !== 'area' ? 'selected' : '' }}>Name</option>
                            <option value="area" {{ request('sort') === 'area' ? 'selected' : '' }}>Area</option>
                        </select>
                    </div>

                    <div class="flex gap-2 ml-auto">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                            Filter
                        </button>
                        <a href="{{ route('mobs.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">
                            Clear
                        </a>
                    </div>
                </div>
            </form>

            <!-- Mob List -->
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Equipment</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($mobs as $mob)
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900">{{ $mob->name }}</div>
                                    @if($mob->notes)
                                        <div class="text-sm text-gray-500">{{ Str::limit($mob->notes, 50) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($mob->area)
                                        <a href="{{ route('areas.wiki', $mob->area) }}" class="text-emerald-600 hover:text-emerald-800 hover:underline">
                                            {{ $mob->area->name }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="space-y-1">
                                        @foreach($mob->equipment as $eq)
                                            <div class="text-sm">
                                                <span class="text-gray-500">{{ $eq->slot }}:</span>
                                                @if($eq->item_id)
                                                    <a href="{{ route('mudlogs.items.edit', $eq->item_id) }}" class="text-indigo-600 hover:underline">
                                                        {{ $eq->item_name }}
                                                    </a>
                                                @else
                                                    <span class="text-gray-700">{{ $eq->item_name }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex gap-2 justify-end">
                                        <a href="{{ route('mobs.edit', $mob) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">Edit</a>
                                        <form method="POST" action="{{ route('mobs.destroy', $mob) }}" class="inline" onsubmit="return confirm('Delete this mob?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600 hover:text-red-900 text-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                    No mobs yet. <a href="{{ route('mobs.create') }}" class="text-indigo-600 hover:underline">Add one</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $mobs->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
