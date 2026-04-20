<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Item Database
                <span class="text-sm text-gray-500 font-normal">({{ $totalCount }} items)</span>
            </h2>
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

    <div class="py-8" x-data="{
            q: '',
            open: {},
            matches(el) {
                if (!this.q) return true;
                const needle = this.q.toLowerCase();
                return (el.dataset.search || '').indexOf(needle) !== -1;
            },
            groupCount(groupEl) {
                return [...groupEl.querySelectorAll('[data-item]')].filter(el => this.matches(el)).length;
            },
            init() {
                this.$watch('q', () => {
                    // Auto-expand groups with matches while searching.
                    if (this.q) {
                        document.querySelectorAll('[data-group]').forEach(g => {
                            if (this.groupCount(g) > 0) this.open[g.dataset.group] = true;
                        });
                    }
                });
            }
         }">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-3">

            <div class="flex flex-wrap gap-2 items-center">
                <input type="search" x-model="q" placeholder="Search name / keyword / material / flag..."
                       class="flex-1 min-w-[240px] border-gray-300 rounded-md shadow-sm text-sm">
                <button type="button" @click="open = {}" class="px-3 py-1.5 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">Collapse all</button>
                <button type="button"
                        @click="document.querySelectorAll('[data-group]').forEach(g => open[g.dataset.group] = true)"
                        class="px-3 py-1.5 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">Expand all</button>
            </div>

            @forelse ($groups as $label => $items)
                <div data-group="{{ $label }}" class="bg-white shadow-sm rounded-lg overflow-hidden"
                     x-show="!q || groupCount($el) > 0" x-cloak>
                    <button type="button" @click="open['{{ $label }}'] = !open['{{ $label }}']"
                            class="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50">
                        <div class="flex items-center gap-2">
                            <span class="text-gray-500 text-xs"
                                  x-text="open['{{ $label }}'] ? '▼' : '▶'">▶</span>
                            <h3 class="font-semibold text-gray-900">{{ $label }}</h3>
                        </div>
                        <span class="text-xs text-gray-500">
                            <span x-show="q" x-text="groupCount($el) + ' / {{ count($items) }}'" x-cloak></span>
                            <span x-show="!q">{{ count($items) }}</span>
                        </span>
                    </button>
                    <div x-show="open['{{ $label }}']" x-cloak class="border-t border-gray-100">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase">
                                <tr class="text-left">
                                    <th class="px-3 py-2">Name</th>
                                    <th class="px-3 py-2">Lvl</th>
                                    <th class="px-3 py-2">Material</th>
                                    <th class="px-3 py-2">Align</th>
                                    <th class="px-3 py-2 cursor-help" title="Piercing, Bashing, Slashing, Magic, Element">Armor</th>
                                    <th class="px-3 py-2">Affects</th>
                                    <th class="px-3 py-2">Flags</th>
                                    <th class="px-3 py-2">Source</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($items as $item)
                                    @php
                                        $searchBlob = strtolower(implode(' ', array_filter([
                                            $item->name, $item->keyword, $item->material, $item->item_type,
                                            $item->weapon_class, $item->damage_type, $item->attack_type,
                                            $item->alignment,
                                            $item->affects->map(fn ($a) => $a->stat)->implode(' '),
                                            $item->flags->map(fn ($f) => $f->flag)->implode(' '),
                                            $item->protections->map(fn ($p) => $p->type)->implode(' '),
                                        ])));
                                    @endphp
                                    <tr data-item data-search="{{ $searchBlob }}" x-show="matches($el)" x-cloak>
                                        <td class="px-3 py-2 font-medium text-gray-900">
                                            {{ $item->name }}
                                            @if ($item->keyword)
                                                <div class="text-xs text-gray-400">{{ $item->keyword }}</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-gray-600">{{ $item->level }}</td>
                                        <td class="px-3 py-2 text-gray-600">{{ $item->material }}</td>
                                        <td class="px-3 py-2">
                                            @if ($item->alignment)
                                                <span class="text-xs px-1.5 py-0.5 bg-amber-100 text-amber-800 rounded font-mono">{{ $item->alignment }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 font-mono text-xs text-gray-700 whitespace-nowrap" title="Piercing, Bashing, Slashing, Magic, Element">
                                            @php
                                                $protMap = $item->protections->mapWithKeys(fn ($p) => [strtolower($p->type) => $p->value]);
                                                $hasArmor = collect(['piercing','bashing','slashing','magic','element'])
                                                    ->contains(fn ($t) => $protMap->has($t));
                                            @endphp
                                            @if ($hasArmor)
                                                {{ implode(' ', array_map(fn ($t) => $protMap[$t] ?? '-', ['piercing','bashing','slashing','magic','element'])) }}
                                                @php
                                                    $extra = $item->protections->filter(fn ($p) => ! in_array(strtolower($p->type), ['piercing','bashing','slashing','magic','element'], true));
                                                @endphp
                                                @if ($extra->isNotEmpty())
                                                    <div class="mt-0.5 flex flex-wrap gap-1">
                                                        @foreach ($extra as $p)
                                                            <span class="px-1 py-0.5 bg-blue-50 text-blue-700 rounded">{{ $p->type }} {{ $p->value }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($item->affects as $a)
                                                    <span class="text-xs px-1.5 py-0.5 bg-indigo-50 text-indigo-700 rounded">
                                                        {{ $a->stat }} {{ $a->modifier > 0 ? '+'.$a->modifier : $a->modifier }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($item->flags as $fl)
                                                    <span class="text-xs px-1.5 py-0.5 bg-gray-100 text-gray-700 rounded">{{ $fl->flag }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-xs">
                                            <a href="{{ route('mudlogs.show', $item->log_file_id) }}" class="text-indigo-600 hover:text-indigo-900">
                                                {{ $item->logFile->filename }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="bg-white shadow-sm rounded-lg p-8 text-center text-gray-500">No items yet.</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
