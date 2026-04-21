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
                    'in-range' => 'In range',
                    'out-of-range' => 'Out of range',
                    'completed' => 'Completed',
                    'not-completed' => 'Not completed',
                    'all' => 'All',
                ];
            @endphp

            <form method="GET" action="{{ route('characters.areas', $character) }}" class="mb-4 flex flex-wrap gap-2 items-center">
                <select name="filter" onchange="this.form.submit()"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                    @foreach ($tabs as $key => $label)
                        <option value="{{ $key }}" @selected($filter === $key)>
                            {{ $label }} ({{ $counts[$key] }})
                        </option>
                    @endforeach
                </select>

                <input type="search" name="q" value="{{ $q }}"
                       placeholder="Search area or realm..."
                       class="flex-1 min-w-[200px] border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                <button type="submit"
                        class="px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700">
                    Search
                </button>
                @if ($q !== '')
                    <a href="{{ route('characters.areas', ['character' => $character, 'filter' => $filter]) }}"
                       class="text-sm text-gray-600 hover:text-gray-900">
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
                                <td class="px-4 py-2 font-medium">
                                    @if ($area->url)
                                        <a href="{{ $area->url }}" target="_blank" rel="noopener"
                                           class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                            {{ $area->name }}
                                        </a>
                                    @else
                                        {{ $area->name }}
                                    @endif
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
