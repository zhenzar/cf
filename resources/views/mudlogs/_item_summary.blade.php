@php($i = $item)
<div class="text-sm space-y-1">
    <div class="flex flex-wrap gap-2 text-xs text-gray-600">
        @if ($i->item_type)<span><strong>{{ $i->item_type }}</strong>@if($i->slot) · {{ $i->slot }}@endif</span>@endif
        @if ($i->level !== null)<span>Lvl {{ $i->level }}</span>@endif
        @if ($i->worth_copper !== null)<span>{{ number_format($i->worth_copper) }} cp</span>@endif
        @if ($i->material)<span>{{ $i->material }}</span>@endif
        @if ($i->weight_pounds !== null)<span>{{ $i->weight_pounds }}lb {{ $i->weight_ounces ?? 0 }}oz</span>@endif
        @if ($i->keyword)<span class="text-gray-400">'{{ $i->keyword }}'</span>@endif
    </div>
    @if ($i->weapon_class)
        <div class="text-xs text-gray-600">Weapon: {{ $i->weapon_class }} ({{ $i->damage_type }})@if($i->av_damage) · av {{ $i->av_damage }}@endif</div>
    @endif
    @if ($i->protections->isNotEmpty())
        <div class="flex flex-wrap gap-1">
            @foreach($i->protections as $p)
                <span class="text-xs px-1.5 py-0.5 bg-blue-50 text-blue-700 rounded">{{ $p->type }} {{ $p->value }}</span>
            @endforeach
        </div>
    @endif
    @if ($i->affects->isNotEmpty())
        <div class="flex flex-wrap gap-1">
            @foreach($i->affects as $a)
                <span class="text-xs px-1.5 py-0.5 bg-indigo-50 text-indigo-700 rounded">
                    {{ $a->stat }} {{ $a->modifier > 0 ? '+'.$a->modifier : $a->modifier }}
                </span>
            @endforeach
        </div>
    @endif
    @if ($i->flags->isNotEmpty())
        <div class="flex flex-wrap gap-1">
            @foreach($i->flags as $fl)
                <span class="text-xs px-1.5 py-0.5 bg-gray-100 rounded">{{ $fl->flag }}</span>
            @endforeach
        </div>
    @endif
</div>
