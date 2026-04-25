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

            <!-- Filter Form -->
            <form method="GET" action="{{ route('mudlogs.items') }}" class="bg-white shadow-sm rounded-lg p-4 space-y-4">
                <div class="flex flex-wrap gap-3 items-end">
                    <!-- Type Filter -->
                    <div class="flex flex-col">
                        <label class="text-xs font-medium text-gray-600 mb-1">Type</label>
                        <select name="type" class="border-gray-300 rounded-md shadow-sm text-sm min-w-[140px]">
                            <option value="">All Types</option>
                            @foreach($types as $type)
                                <option value="{{ $type }}" {{ $currentType == $type ? 'selected' : '' }}>
                                    {{ ucfirst($type) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Material Filter -->
                    <div class="flex flex-col">
                        <label class="text-xs font-medium text-gray-600 mb-1">Material</label>
                        <select name="material" class="border-gray-300 rounded-md shadow-sm text-sm min-w-[140px]">
                            <option value="">All Materials</option>
                            @foreach($materials as $material)
                                <option value="{{ $material }}" {{ $currentMaterial == $material ? 'selected' : '' }}>
                                    {{ ucfirst($material) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Attack Type Filter -->
                    <div class="flex flex-col">
                        <label class="text-xs font-medium text-gray-600 mb-1">Attack Type</label>
                        <select name="attack_type" class="border-gray-300 rounded-md shadow-sm text-sm min-w-[140px]">
                            <option value="">All Attack Types</option>
                            @foreach($attackTypes as $attackType)
                                <option value="{{ $attackType }}" {{ $currentAttackType == $attackType ? 'selected' : '' }}>
                                    {{ ucfirst($attackType) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Flag Filter -->
                    <div class="flex flex-col">
                        <label class="text-xs font-medium text-gray-600 mb-1">Flag</label>
                        <select name="flag" class="border-gray-300 rounded-md shadow-sm text-sm min-w-[140px]">
                            <option value="">All Flags</option>
                            @foreach($flags as $flag)
                                <option value="{{ $flag }}" {{ $currentFlag == $flag ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $flag)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Sort By -->
                    <div class="flex flex-col">
                        <label class="text-xs font-medium text-gray-600 mb-1">Sort By</label>
                        <div class="flex gap-2">
                            <select name="sort_by" class="border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="level" {{ $currentSortBy == 'level' ? 'selected' : '' }}>Level</option>
                                <option value="av_damage" {{ $currentSortBy == 'av_damage' ? 'selected' : '' }}>Avg Damage</option>
                                <option value="weight_pounds" {{ $currentSortBy == 'weight_pounds' ? 'selected' : '' }}>Weight</option>
                                <option value="name" {{ $currentSortBy == 'name' ? 'selected' : '' }}>Name</option>
                            </select>
                            <select name="sort_order" class="border-gray-300 rounded-md shadow-sm text-sm w-[80px]">
                                <option value="asc" {{ $currentSortOrder == 'asc' ? 'selected' : '' }}>Asc</option>
                                <option value="desc" {{ $currentSortOrder == 'desc' ? 'selected' : '' }}>Desc</option>
                            </select>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="flex gap-2 ml-auto">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                            Apply Filters
                        </button>
                        <a href="{{ route('mudlogs.items') }}" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">
                            Clear
                        </a>
                    </div>
                </div>

                <!-- Search & Collapse/Expand -->
                <div class="flex flex-wrap gap-2 items-center pt-3 border-t border-gray-200">
                    <input type="search" x-model="q" placeholder="Search name / keyword..."
                           class="flex-1 min-w-[240px] border-gray-300 rounded-md shadow-sm text-sm">
                    <button type="button" @click="open = {}" class="px-3 py-1.5 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">Collapse all</button>
                    <button type="button"
                            @click="document.querySelectorAll('[data-group]').forEach(g => open[g.dataset.group] = true)"
                            class="px-3 py-1.5 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">Expand all</button>
                </div>
            </form>

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
                                    @php
                                        $hasWeaponClass = $items->contains(fn($i) => !empty($i->weapon_class));
                                    @endphp
                                    <th class="px-3 py-2">Name</th>
                                    <th class="px-3 py-2">Lvl</th>
                                    <th class="px-3 py-2">Material</th>
                                    <th class="px-3 py-2">Weight</th>
                                    <th class="px-3 py-2">Align</th>
                                    @if ($hasWeaponClass)
                                        <th class="px-3 py-2 cursor-help" title="Attack/Damage type">Dmg Type</th>
                                        <th class="px-3 py-2">Affects</th>
                                        <th class="px-3 py-2">Flags</th>
                                        <th class="px-3 py-2 cursor-help" title="Average damage (dice)">Av Dmg</th>
                                    @else
                                        <th class="px-3 py-2 cursor-help" title="Piercing, Bashing, Slashing, Magic, Element">Armor</th>
                                        <th class="px-3 py-2">Affects</th>
                                        <th class="px-3 py-2">Flags</th>
                                    @endif
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
                                    <tr data-item data-search="{{ $searchBlob }} {{ strtolower($item->spells->map(fn ($s) => $s->name)->implode(' ')) }}" x-show="matches($el)" x-cloak>
                                        <td class="px-3 py-2 font-medium text-gray-900">
                                            <a href="{{ route('mudlogs.items.edit', $item) }}" class="hover:text-indigo-600 hover:underline">
                                                {{ $item->name }}
                                            </a>
                                            @if ($item->keyword)
                                                <div class="text-xs text-gray-400">{{ $item->keyword }}</div>
                                            @endif
                                            @if ($item->spells->isNotEmpty())
                                                <div class="mt-1 flex flex-wrap gap-1">
                                                    @foreach ($item->spells as $s)
                                                        <span class="text-[10px] px-1.5 py-0.5 bg-purple-50 text-purple-700 rounded">
                                                            {{ $s->name }}@if ($s->level) <span class="text-purple-400">(Lv {{ $s->level }})</span>@endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if ($item->area)
                                                <div class="mt-1 text-[10px] text-emerald-600" title="Area: {{ $item->area->name }}">
                                                    {{ $item->area->name }}
                                                </div>
                                            @endif
                                            @if ($item->note)
                                                <div class="mt-1 text-[10px] text-blue-600 cursor-help" title="Note: {{ $item->note }}">
                                                    📝 {{ Str::limit($item->note, 30) }}
                                                </div>
                                            @endif
                                            @if ($item->raw_text)
                                                <div class="mt-1">
                                                    <button type="button"
                                                            @click="$event.target.nextElementSibling.classList.toggle('hidden')"
                                                            class="text-[10px] text-gray-400 hover:text-indigo-600 underline">
                                                        Raw
                                                    </button>
                                                    <pre class="hidden mt-1 p-2 bg-gray-900 text-gray-300 text-[10px] rounded overflow-x-auto whitespace-pre-wrap">{{ $item->raw_text }}</pre>
                                                </div>
                                            @endif
                                            @if ($item->logFile)
                                                <div class="mt-1 text-[10px] text-gray-400">
                                                    <a href="{{ route('mudlogs.show', $item->log_file_id) }}"
                                                       class="hover:text-indigo-600 hover:underline">
                                                        {{ $item->logFile->filename }}
                                                    </a>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-gray-600">{{ $item->level }}</td>
                                        <td class="px-3 py-2 text-gray-600">{{ $item->material }}</td>
                                        <td class="px-3 py-2 text-gray-600">
                                            @if ($item->weight_pounds || $item->weight_ounces)
                                                {{ $item->weight_pounds ? $item->weight_pounds.' lb' : '' }}{{ $item->weight_ounces ? ' '.$item->weight_ounces.' oz' : '' }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            @if ($item->alignment)
                                                <span class="text-xs px-1.5 py-0.5 bg-amber-100 text-amber-800 rounded font-mono">{{ $item->alignment }}</span>
                                            @endif
                                        </td>
                                        @if ($hasWeaponClass)
                                            <td class="px-3 py-2 text-xs text-gray-600">
                                                @if ($item->attack_type)
                                                    {{ $item->attack_type }}
                                                @elseif ($item->damage_type)
                                                    {{ $item->damage_type }}
                                                @endif
                                            </td>
                                        @else
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
                                        @endif
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
                                        @if ($hasWeaponClass)
                                            <td class="px-3 py-2 text-xs font-mono whitespace-nowrap" title="{{ $item->attack_type ? 'Attack: '.$item->attack_type : '' }}{{ $item->damage_type ? ' · '.$item->damage_type : '' }}">
                                                @if ($item->av_damage || $item->damage_dice)
                                                    <span class="text-gray-800">{{ $item->av_damage ?? '—' }}</span>
                                                    @if ($item->damage_dice)
                                                        <span class="text-gray-400">({{ $item->damage_dice }})</span>
                                                    @endif
                                                @endif
                                            </td>
                                        @endif
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
