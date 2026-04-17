<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Areas &mdash; {{ $character->name }} <span class="text-gray-400 font-normal">(Level {{ $character->level }})</span>
            </h2>
            <a href="{{ route('characters.show', $character) }}" class="text-sm text-gray-600 hover:text-gray-900">
                &larr; Back to character
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 font-medium text-sm text-green-600">{{ session('status') }}</div>
            @endif

            @php
                $tabs = [
                    'in-range' => ['label' => 'In range', 'color' => 'indigo'],
                    'out-of-range' => ['label' => 'Out of range', 'color' => 'gray'],
                    'completed' => ['label' => 'Completed', 'color' => 'green'],
                    'all' => ['label' => 'All', 'color' => 'slate'],
                ];
            @endphp

            <div class="mb-4 bg-white rounded-lg shadow-sm p-1 flex gap-1">
                @foreach ($tabs as $key => $tab)
                    <a href="{{ route('characters.areas', ['character' => $character, 'filter' => $key, 'q' => $q ?: null]) }}"
                       @class([
                           'flex-1 text-center px-4 py-2 rounded-md text-sm font-medium transition',
                           'bg-indigo-600 text-white' => $filter === $key,
                           'text-gray-600 hover:bg-gray-100' => $filter !== $key,
                       ])>
                        {{ $tab['label'] }}
                        <span @class([
                            'ml-1 text-xs',
                            'text-indigo-200' => $filter === $key,
                            'text-gray-400' => $filter !== $key,
                        ])>{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('characters.areas', $character) }}" class="mb-4 flex gap-2">
                <input type="hidden" name="filter" value="{{ $filter }}">
                <input type="search" name="q" value="{{ $q }}"
                       placeholder="Search area or realm..."
                       class="flex-1 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                <button type="submit"
                        class="px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700">
                    Search
                </button>
                @if ($q !== '')
                    <a href="{{ route('characters.areas', ['character' => $character, 'filter' => $filter]) }}"
                       class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 self-center">
                        Clear
                    </a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            <th class="px-4 py-2 w-12">Done</th>
                            <th class="px-4 py-2 w-24">Level</th>
                            <th class="px-4 py-2">Realm</th>
                            <th class="px-4 py-2">Area</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($areas as $area)
                            <tr @class([
                                'bg-gray-50 text-gray-400' => $area->completed,
                                'opacity-70' => ! $area->completed && $area->is_all,
                            ])>
                                <td class="px-4 py-2">
                                    <form method="POST" action="{{ route('characters.areas.toggle', $character) }}">
                                        @csrf
                                        <input type="hidden" name="area_id" value="{{ $area->id }}">
                                        <input type="hidden" name="filter" value="{{ $filter }}">
                                        <input type="hidden" name="q" value="{{ $q }}">
                                        <input type="checkbox"
                                               onchange="this.form.submit()"
                                               @checked($area->completed)
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    </form>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs">
                                    @if ($area->min_level === 1 && $area->max_level === 51)
                                        All
                                    @else
                                        {{ $area->min_level }}&ndash;{{ $area->max_level }}
                                    @endif
                                </td>
                                <td class="px-4 py-2">{{ $area->realm }}</td>
                                <td class="px-4 py-2 font-medium">
                                    {{ $area->name }}
                                    @if ($area->completed)
                                        <span class="ml-2 text-xs text-green-600">✓ completed</span>
                                    @elseif ($area->is_all)
                                        <span class="ml-2 text-xs text-gray-400">wide range</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
