<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('My Characters') }}
            </h2>
            <a href="{{ route('characters.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('New Character') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 font-medium text-sm text-green-600">{{ session('status') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($characters->isEmpty())
                        <p class="text-gray-500">You haven't created any characters yet.</p>
                    @else
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="px-4 py-2">Name</th>
                                    <th class="px-4 py-2">Lvl</th>
                                    <th class="px-4 py-2">Race</th>
                                    <th class="px-4 py-2">Class</th>
                                    <th class="px-4 py-2">Sphere</th>
                                    <th class="px-4 py-2">Alignment</th>
                                    <th class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($characters as $character)
                                    <tr>
                                        <td class="px-4 py-3 font-medium">{{ $character->name }}</td>
                                        <td class="px-4 py-3">{{ $character->level }}</td>
                                        <td class="px-4 py-3">{{ $character->race->name }}</td>
                                        <td class="px-4 py-3">{{ $character->characterClass->name }}</td>
                                        <td class="px-4 py-3">{{ $character->sphere?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 capitalize">{{ $character->alignment }}</td>
                                        <td class="px-4 py-3 text-right space-x-3">
                                            <a href="{{ route('characters.areas', $character) }}"
                                               class="text-indigo-600 hover:text-indigo-900">Areas</a>
                                            <a href="{{ route('characters.show', $character) }}"
                                               class="text-indigo-600 hover:text-indigo-900">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
