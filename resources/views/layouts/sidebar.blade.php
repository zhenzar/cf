@php
    $activeId = $activeCharacter?->id;
    $navItems = [
        ['label' => 'Dashboard',  'route' => 'dashboard',        'match' => ['dashboard', 'characters.*']],
        ['label' => 'Log scans',  'route' => 'mudlogs.index',    'match' => ['mudlogs.index', 'mudlogs.show', 'mudlogs.toggle', 'mudlogs.scan', 'mudlogs.upload', 'mudlogs.destroy']],
        ['label' => 'Items',      'route' => 'mudlogs.items',    'match' => ['mudlogs.items', 'mudlogs.pending', 'mudlogs.pending.*']],
        ['label' => 'Areas',      'route' => 'areas.index',      'match' => ['areas.index', 'characters.areas']],
        ['label' => 'Mobs',       'route' => 'mobs.index',       'match' => ['mobs.index', 'mobs.create', 'mobs.edit', 'mobs.store', 'mobs.update', 'mobs.destroy']],
    ];
@endphp

<aside
    class="bg-gray-900 text-gray-100 w-64 flex-shrink-0 flex flex-col fixed md:static inset-y-0 left-0 z-40 transform md:transform-none transition-transform duration-200"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'">

    <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
        <a href="{{ route('dashboard') }}" class="font-semibold tracking-wide text-white">{{ config('app.name', 'CF') }}</a>
        <button @click="sidebarOpen = false" class="md:hidden text-gray-400 hover:text-white">&times;</button>
    </div>

    {{-- Active character selector --}}
    <div class="px-4 py-4 border-b border-gray-800 space-y-2">
        <label class="block text-[11px] uppercase tracking-wider text-gray-500">Active character</label>
        <form method="POST" action="{{ route('active-character.set') }}">
            @csrf
            <select name="character_id" onchange="this.form.submit()"
                    class="w-full bg-gray-800 border-gray-700 text-gray-100 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="0" @selected(! $activeId)>— none —</option>
                @foreach ($sidebarCharacters as $c)
                    <option value="{{ $c->id }}" @selected($activeId === $c->id)>
                        {{ $c->name }} (Lv {{ $c->level }})
                    </option>
                @endforeach
            </select>
        </form>
        @if ($activeCharacter)
            <div class="flex items-center justify-between text-xs text-gray-400">
                <a href="{{ route('characters.show', $activeCharacter) }}" class="hover:text-white">View</a>
                <form method="POST" action="{{ route('characters.destroy', $activeCharacter) }}"
                      onsubmit="return confirm('Delete {{ $activeCharacter->name }}?');">
                    @csrf
                    @method('DELETE')
                    <button class="text-red-400 hover:text-red-300">Delete</button>
                </form>
            </div>
        @endif
        <a href="{{ route('characters.create') }}"
           class="block text-center text-xs text-indigo-300 hover:text-indigo-200 py-1">+ New character</a>
    </div>

    {{-- Primary menu --}}
    <nav class="flex-1 overflow-y-auto px-2 py-3 space-y-0.5 text-sm">
        @foreach ($navItems as $item)
            @php
                $isActive = collect($item['match'])->contains(fn ($p) => request()->routeIs($p));
                $href = $item['route'] === 'areas.index'
                    ? route('areas.index')
                    : route($item['route']);
            @endphp
            <a href="{{ $href }}"
               class="flex items-center justify-between px-3 py-2 rounded-md
                      {{ $isActive ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                <span>{{ $item['label'] }}</span>
                @if ($item['label'] === 'Areas' && $activeCharacter)
                    <span class="text-[10px] text-gray-400">{{ $activeCharacter->name }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    {{-- Character list (quick switch) --}}
    @if ($sidebarCharacters->isNotEmpty())
        <div class="px-4 py-3 border-t border-gray-800">
            <div class="text-[11px] uppercase tracking-wider text-gray-500 mb-1">Characters</div>
            <ul class="space-y-0.5 text-xs">
                @foreach ($sidebarCharacters as $c)
                    <li class="flex items-center justify-between">
                        <form method="POST" action="{{ route('active-character.set') }}" class="flex-1 min-w-0">
                            @csrf
                            <input type="hidden" name="character_id" value="{{ $c->id }}">
                            <button type="submit"
                                    class="text-left truncate w-full py-1 {{ $activeId === $c->id ? 'text-white font-medium' : 'text-gray-400 hover:text-white' }}">
                                {{ $c->name }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('characters.destroy', $c) }}"
                              onsubmit="return confirm('Delete {{ $c->name }}?');" class="ml-2">
                            @csrf
                            @method('DELETE')
                            <button class="text-gray-600 hover:text-red-400" title="Delete">&times;</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- User --}}
    <div class="px-4 py-3 border-t border-gray-800 text-xs">
        <div class="text-gray-400 mb-1 truncate">{{ Auth::user()->name }}</div>
        <div class="flex items-center justify-between">
            <a href="{{ route('profile.edit') }}" class="text-gray-400 hover:text-white">Profile</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-gray-400 hover:text-white">Log out</button>
            </form>
        </div>
    </div>
</aside>

{{-- Mobile backdrop --}}
<div x-show="sidebarOpen" @click="sidebarOpen = false" x-cloak
     class="fixed inset-0 bg-black/40 z-30 md:hidden"></div>
