<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Item Database</h2>
            <div class="flex gap-4 text-sm">
                @if (($pendingCount ?? 0) > 0)
                    <a href="{{ route('mudlogs.pending') }}" class="text-amber-700 hover:text-amber-900 font-medium">
                        Pending ({{ $pendingCount }}) &rarr;
                    </a>
                @endif
                <a href="{{ route('mudlogs.index') }}" class="text-gray-600 hover:text-gray-900">Log files</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <form method="GET" class="flex flex-wrap gap-2 items-center">
                <input type="search" name="q" value="{{ $q }}" placeholder="name / keyword / material..."
                       class="flex-1 min-w-[200px] border-gray-300 rounded-md shadow-sm text-sm">
                <select name="type" class="border-gray-300 rounded-md shadow-sm text-sm">
                    <option value="">All types</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}" @selected($type === $t)>{{ $t }}</option>
                    @endforeach
                </select>
                <select name="slot" class="border-gray-300 rounded-md shadow-sm text-sm">
                    <option value="">All slots</option>
                    @foreach($slots as $s)
                        <option value="{{ $s }}" @selected($slot === $s)>{{ $s }}</option>
                    @endforeach
                </select>
                <button class="px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700">Filter</button>
            </form>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold text-gray-500 uppercase">
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Slot</th>
                            <th class="px-3 py-2">Lvl</th>
                            <th class="px-3 py-2">Material</th>
                            <th class="px-3 py-2">Affects</th>
                            <th class="px-3 py-2">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items as $item)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $item->name }}
                                    @if ($item->keyword) <div class="text-xs text-gray-400">{{ $item->keyword }}</div>@endif
                                </td>
                                <td class="px-3 py-2">{{ $item->weapon_class ?: $item->item_type }}</td>
                                <td class="px-3 py-2">{{ $item->slot }}</td>
                                <td class="px-3 py-2">{{ $item->level }}</td>
                                <td class="px-3 py-2">{{ $item->material }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($item->affects as $a)
                                            <span class="text-xs px-1.5 py-0.5 bg-indigo-50 text-indigo-700 rounded">
                                                {{ $a->stat }} {{ $a->modifier > 0 ? '+'.$a->modifier : $a->modifier }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-xs">
                                    <a href="{{ route('mudlogs.show', $item->log_file_id) }}" class="text-indigo-600 hover:text-indigo-900">
                                        {{ $item->logFile->filename }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No items.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $items->links() }}</div>
        </div>
    </div>
</x-app-layout>
