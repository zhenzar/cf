<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit') }} {{ $character->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('characters.update', $character) }}" class="space-y-6">
                        @csrf
                        @method('PATCH')

                        <div>
                            <x-input-label for="level" :value="__('Level')" />
                            <x-text-input id="level" name="level" type="number" min="1" max="51" step="1"
                                          class="mt-1 block w-32"
                                          :value="old('level', $character->level)" required />
                            <p class="mt-1 text-xs text-gray-500">Between 1 and 51.</p>
                            <x-input-error :messages="$errors->get('level')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Save') }}</x-primary-button>
                            <a href="{{ route('characters.show', $character) }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
