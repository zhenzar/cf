<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight truncate max-w-3xl" title="{{ $file->path }}">
                {{ $file->filename }}
            </h2>
            <a href="{{ route('mudlogs.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Back</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="flex items-center gap-3 bg-white shadow-sm rounded-lg p-3 text-sm">
                <form method="POST" action="{{ route('mudlogs.toggle', $file) }}">
                    @csrf
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" onchange="this.form.submit()" @checked($file->reviewed)
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span>Reviewed</span>
                    </label>
                </form>
                <span class="text-gray-400">|</span>
                <span>Items: <strong>{{ $file->items->count() }}</strong></span>
                <span class="text-gray-400">|</span>
                <span>Source: {{ $file->source }}</span>
                <span class="text-gray-400">|</span>
                <span class="truncate">Path: <code class="text-xs">{{ $file->path }}</code></span>
            </div>

            @forelse ($file->items as $item)
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="flex items-start justify-between flex-wrap gap-2">
                        <div>
                            <div class="font-semibold text-gray-900">{{ $item->name }}</div>
                            @if ($item->keyword)
                                <div class="text-xs text-gray-500">keyword: <code>{{ $item->keyword }}</code></div>
                            @endif
                        </div>
                        <div class="text-xs text-gray-600 text-right space-y-0.5">
                            @php($displayType = $item->weapon_class ?: $item->item_type)
                            @if ($displayType)  <div><strong>{{ $displayType }}</strong> @if($item->slot)— {{ $item->slot }} @endif</div>@endif
                            @if ($item->level !== null) <div>Level {{ $item->level }}</div> @endif
                            @if ($item->worth_copper !== null) <div>{{ number_format($item->worth_copper) }} copper</div> @endif
                            @if ($item->alignment) <div><span class="text-gray-500">Align:</span> <span class="font-mono">{{ $item->alignment }}</span></div> @endif
                        </div>
                    </div>

                    <div class="mt-2 grid md:grid-cols-2 gap-3 text-xs text-gray-700">
                        <div class="space-y-0.5">
                            @if ($item->material) <div><span class="text-gray-500">Material:</span> {{ $item->material }}</div>@endif
                            @if ($item->weight_pounds !== null)
                                <div><span class="text-gray-500">Weight:</span> {{ $item->weight_pounds }}lb {{ $item->weight_ounces ?? 0 }}oz</div>
                            @endif
                            @if ($item->weapon_class)
                                <div><span class="text-gray-500">Weapon:</span> {{ $item->weapon_class }} ({{ $item->damage_type }})
                                    @if($item->av_damage) — av {{ $item->av_damage }} @endif
                                </div>
                            @endif
                            @if ($item->flags->isNotEmpty())
                                <div><span class="text-gray-500">Flags:</span>
                                    @foreach($item->flags as $fl)
                                        <span class="inline-block px-1.5 py-0.5 bg-gray-100 rounded mr-1">{{ $fl->flag }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="space-y-0.5">
                            @if ($item->protections->isNotEmpty())
                                <div class="text-gray-500">Protections:</div>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($item->protections as $p)
                                        <span class="px-1.5 py-0.5 bg-blue-50 text-blue-700 rounded">{{ $p->type }} {{ $p->value }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @if ($item->affects->isNotEmpty())
                                <div class="text-gray-500 mt-1">Affects:</div>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($item->affects as $a)
                                        <span class="px-1.5 py-0.5 bg-indigo-50 text-indigo-700 rounded">
                                            {{ $a->stat }} {{ $a->modifier > 0 ? '+'.$a->modifier : $a->modifier }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <details class="mt-2">
                        <summary class="text-xs text-gray-500 cursor-pointer">Raw</summary>
                        <pre class="text-xs bg-gray-50 p-2 mt-1 whitespace-pre-wrap">{{ $item->raw_text }}</pre>
                    </details>
                </div>
            @empty
                <div class="bg-white shadow-sm rounded-lg p-8 text-center text-gray-500 text-sm">
                    No items parsed from this file.
                </div>
            @endforelse

        </div>
    </div>
</x-app-layout>
