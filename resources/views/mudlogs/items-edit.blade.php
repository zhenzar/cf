<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Item: {{ $item->name }}</h2>
            <a href="{{ route('mudlogs.items') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Back to items</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <form method="POST" action="{{ route('mudlogs.items.update', $item) }}" class="p-6 space-y-6">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Item Name</label>
                            <div class="text-gray-900 font-medium">{{ $item->name }}</div>
                            @if ($item->keyword)
                                <div class="text-xs text-gray-500">Keywords: {{ $item->keyword }}</div>
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Parsed Slot</label>
                            <div class="text-gray-600">{{ $item->slot ?? '—' }}</div>
                            <div class="text-xs text-gray-500">Override below to change grouping</div>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-sm font-semibold text-gray-900 mb-4">Override Settings</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="slot_override" class="block text-sm font-medium text-gray-700 mb-1">Slot Override</label>
                                <select name="slot_override" id="slot_override" class="w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">— No override —</option>
                                    @foreach ($slots as $slot)
                                        <option value="{{ $slot }}" @selected($item->slot_override === $slot)>{{ $slot }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Force item to appear in this slot group</p>
                            </div>

                            <div>
                                <label for="area_id" class="block text-sm font-medium text-gray-700 mb-1">Area</label>
                                <select name="area_id" id="area_id" class="w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">— Select area —</option>
                                    @foreach ($areas as $id => $name)
                                        <option value="{{ $id }}" @selected($item->area_id === $id)>{{ $name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Where this item is found</p>
                            </div>
                        </div>

                        <div class="mt-6">
                            <label for="note" class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                            <textarea name="note" id="note" rows="4" class="w-full border-gray-300 rounded-md shadow-sm" placeholder="Add notes about this item...">{{ $item->note }}</textarea>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 pt-4 border-t border-gray-200">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700">
                            Save Changes
                        </button>
                        <a href="{{ route('mudlogs.items') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                    </div>
                </form>
            </div>

            <div class="mt-6 bg-gray-50 rounded-lg p-4 text-sm text-gray-600">
                <h4 class="font-medium text-gray-900 mb-2">Item Details</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <span class="text-gray-500">Type:</span>
                        <span class="text-gray-900">{{ $item->item_type ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Level:</span>
                        <span class="text-gray-900">{{ $item->level ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Material:</span>
                        <span class="text-gray-900">{{ $item->material ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Worth:</span>
                        <span class="text-gray-900">{{ $item->worth_copper ? number_format($item->worth_copper) . ' cp' : '—' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
