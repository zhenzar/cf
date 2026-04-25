<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Add New Area
            </h2>
            <a href="{{ route('areas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Back to Areas</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('areas.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Area Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="realm" class="block text-sm font-medium text-gray-700">Realm</label>
                        <input type="text" name="realm" id="realm" value="{{ old('realm') }}" required
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                        @error('realm')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="min_level" class="block text-sm font-medium text-gray-700">Min Level</label>
                            <input type="number" name="min_level" id="min_level" value="{{ old('min_level', 1) }}" min="1" max="51" required
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            @error('min_level')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="max_level" class="block text-sm font-medium text-gray-700">Max Level</label>
                            <input type="number" name="max_level" id="max_level" value="{{ old('max_level', 51) }}" min="1" max="51" required
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            @error('max_level')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" name="area_explored" id="area_explored" value="1" {{ old('area_explored') ? 'checked' : '' }}
                               class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                        <label for="area_explored" class="ml-2 block text-sm text-gray-700">
                            Area Explorer <span class="text-gray-500">(marks this as an explorable area)</span>
                        </label>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700">
                            Create Area
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
