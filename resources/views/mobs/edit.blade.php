<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Mob: {{ $mob->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('mobs.update', $mob) }}">
                    @csrf
                    @method('PUT')

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" name="name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                   value="{{ old('name', $mob->name) }}">
                            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Area</label>
                            <select name="area_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">- Select Area -</option>
                                @foreach($areas as $id => $name)
                                    <option value="{{ $id }}" {{ old('area_id', $mob->area_id) == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('notes', $mob->notes) }}</textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Equipment</label>
                            <div id="equipment-list" class="space-y-2">
                                @forelse($mob->equipment as $i => $eq)
                                    <div class="flex gap-2 equipment-row">
                                        <select name="equipment[{{ $i }}][slot]" class="rounded-md border-gray-300 shadow-sm w-32">
                                            @foreach($slots as $slot)
                                                <option value="{{ $slot }}" {{ $eq->slot === $slot ? 'selected' : '' }}>{{ ucfirst($slot) }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="equipment[{{ $i }}][item_name]" value="{{ $eq->item_name }}"
                                               class="flex-1 rounded-md border-gray-300 shadow-sm">
                                        <button type="button" onclick="this.closest('.equipment-row').remove()"
                                                class="px-2 py-1 text-red-600 hover:text-red-800">×</button>
                                    </div>
                                @empty
                                    <div class="flex gap-2 equipment-row">
                                        <select name="equipment[0][slot]" class="rounded-md border-gray-300 shadow-sm w-32">
                                            @foreach($slots as $slot)
                                                <option value="{{ $slot }}">{{ ucfirst($slot) }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="equipment[0][item_name]" placeholder="Item name..."
                                               class="flex-1 rounded-md border-gray-300 shadow-sm">
                                        <button type="button" onclick="this.closest('.equipment-row').remove()"
                                                class="px-2 py-1 text-red-600 hover:text-red-800">×</button>
                                    </div>
                                @endforelse
                            </div>
                            <button type="button" onclick="addEquipmentRow()"
                                    class="mt-2 px-3 py-1 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">
                                + Add Equipment
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            Update Mob
                        </button>
                        <a href="{{ route('mobs.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let equipmentCount = {{ $mob->equipment->count() > 0 ? $mob->equipment->count() : 1 }};
        function addEquipmentRow() {
            const container = document.getElementById('equipment-list');
            const row = document.createElement('div');
            row.className = 'flex gap-2 equipment-row';
            row.innerHTML = `
                <select name="equipment[${equipmentCount}][slot]" class="rounded-md border-gray-300 shadow-sm w-32">
                    @foreach($slots as $slot)
                        <option value="{{ $slot }}">{{ ucfirst($slot) }}</option>
                    @endforeach
                </select>
                <input type="text" name="equipment[${equipmentCount}][item_name]" placeholder="Item name..."
                       class="flex-1 rounded-md border-gray-300 shadow-sm">
                <button type="button" onclick="this.closest('.equipment-row').remove()"
                        class="px-2 py-1 text-red-600 hover:text-red-800">×</button>
            `;
            container.appendChild(row);
            equipmentCount++;
        }
    </script>
</x-app-layout>
