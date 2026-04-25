<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Pending Items <span class="text-gray-400 font-normal">({{ $pending->count() }})</span>
            </h2>
            <div class="flex gap-4 text-sm">
                <a href="{{ route('mudlogs.items') }}" class="text-gray-600 hover:text-gray-900">Items</a>
                <a href="{{ route('mudlogs.index') }}" class="text-gray-600 hover:text-gray-900">Log files</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded">
                    {{ session('status') }}
                </div>
            @endif

            @if ($pending->isEmpty())
                <div class="bg-white shadow-sm rounded-lg p-8 text-center text-gray-500 text-sm">
                    Nothing pending. New items with names that already exist in the database will appear here.
                </div>
            @endif

            @foreach ($pending as $item)
                <div class="bg-white shadow-sm rounded-lg p-4 space-y-3">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <div class="text-xs text-gray-500 uppercase tracking-wide">New duplicate</div>
                            <div class="text-lg font-semibold text-gray-900">{{ $item->name }}</div>
                            <div class="text-xs text-gray-500">
                                From <a href="{{ route('mudlogs.show', $item->log_file_id) }}" class="text-indigo-600 hover:text-indigo-900">{{ $item->logFile->filename }}</a>
                                · {{ $item->created_at->diffForHumans() }}
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('mudlogs.pending.confirm', $item) }}">
                                @csrf
                                <button class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">
                                    Add anyway
                                </button>
                            </form>
                            <form method="POST" action="{{ route('mudlogs.pending.ignore', $item) }}">
                                @csrf
                                <button class="px-3 py-1.5 bg-gray-200 text-gray-700 text-sm rounded-md hover:bg-gray-300">
                                    Ignore
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="border border-indigo-200 rounded p-3 bg-indigo-50/30">
                            <div class="text-xs font-semibold text-indigo-700 mb-2 uppercase">New</div>
                            @include('mudlogs._item_summary', ['item' => $item])
                        </div>
                        <div class="border border-gray-200 rounded p-3">
                            <div class="text-xs font-semibold text-gray-700 mb-2 uppercase">
                                Already in DB ({{ ($existing[$item->name] ?? collect())->count() }})
                            </div>
                            @forelse ($existing[$item->name] ?? [] as $match)
                                <div class="border-t first:border-0 py-2">
                                    @include('mudlogs._item_summary', ['item' => $match])
                                    <div class="text-xs text-gray-400 mt-1">
                                        @if ($match->log_file_id)
                                            From <a href="{{ route('mudlogs.show', $match->log_file_id) }}" class="text-indigo-600 hover:text-indigo-900">{{ $match->logFile->filename ?? 'Unknown' }}</a>
                                        @else
                                            <span class="text-gray-400">No source log</span>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-xs text-gray-400">No confirmed match (unexpected).</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach

        </div>
    </div>
</x-app-layout>
