<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $character->name }}
            </h2>
            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('characters.areas', $character) }}" class="text-indigo-600 hover:text-indigo-900">Areas</a>
                <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-gray-900">&larr; Back</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 font-medium text-sm text-green-600">{{ session('status') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div class="pb-4 border-b">
                        <form method="POST" action="{{ route('characters.update', $character) }}"
                              class="flex items-end gap-3">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="level" class="block text-xs font-semibold text-gray-500 uppercase">Level</label>
                                <input id="level" name="level" type="number" min="1" max="51" step="1"
                                       value="{{ old('level', $character->level) }}"
                                       class="mt-1 w-24 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            </div>
                            <x-primary-button>Save</x-primary-button>
                            @error('level')
                                <span class="text-sm text-red-600 ml-2">{{ $message }}</span>
                            @enderror
                        </form>
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs font-semibold text-gray-500 uppercase">Race</dt>
                            <dd class="mt-1">{{ $character->race->name }} <span class="text-gray-400">(cost {{ $character->race->cost }})</span></dd>
                            <dd class="text-sm text-gray-500">{{ $character->race->description }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-gray-500 uppercase">Class</dt>
                            <dd class="mt-1">{{ $character->characterClass->name }}</dd>
                            <dd class="text-sm text-gray-500">{{ $character->characterClass->description }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-gray-500 uppercase">Sphere</dt>
                            <dd class="mt-1">{{ $character->sphere?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-gray-500 uppercase">Alignment</dt>
                            <dd class="mt-1 capitalize">{{ $character->alignment }}</dd>
                        </div>
                    </dl>

                    <form method="POST" action="{{ route('characters.destroy', $character) }}"
                          onsubmit="return confirm('Delete this character?');"
                          class="pt-4 border-t">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>{{ __('Delete Character') }}</x-danger-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
