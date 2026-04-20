<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Mud Logs</h2>
            <a href="{{ route('mudlogs.items') }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                Browse parsed items &rarr;
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded">
                    {{ session('status') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid md:grid-cols-2 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 mb-2">Scan a directory</h3>
                    <form method="POST" action="{{ route('mudlogs.scan') }}" class="flex gap-2">
                        @csrf
                        <input type="text" name="path" placeholder="/absolute/path/to/logs"
                               class="flex-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">
                            Scan
                        </button>
                    </form>
                    <p class="text-xs text-gray-500 mt-2">Recursively reads all <code>.txt</code> files.</p>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 mb-2">Upload log files</h3>
                    <form method="POST" action="{{ route('mudlogs.upload') }}" enctype="multipart/form-data" class="flex gap-2">
                        @csrf
                        <input type="file" name="files[]" multiple accept=".txt" class="flex-1 text-sm">
                        <button class="px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700">
                            Upload
                        </button>
                    </form>
                    <p class="text-xs text-gray-500 mt-2">Uploaded files are stored under <code>storage/app/mudlogs/uploads/</code>.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('mudlogs.index') }}" class="flex flex-wrap gap-2 items-center">
                <select name="filter" onchange="this.form.submit()"
                        class="border-gray-300 rounded-md shadow-sm text-sm">
                    <option value="all" @selected($filter==='all')>All ({{ $counts['all'] }})</option>
                    <option value="pending" @selected($filter==='pending')>Pending ({{ $counts['pending'] }})</option>
                    <option value="reviewed" @selected($filter==='reviewed')>Reviewed ({{ $counts['reviewed'] }})</option>
                </select>
                <input type="search" name="q" value="{{ $q }}" placeholder="Search filename..."
                       class="flex-1 min-w-[200px] border-gray-300 rounded-md shadow-sm text-sm">
                <button class="px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700">Search</button>
            </form>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            <th class="px-4 py-2 w-12">Done</th>
                            <th class="px-4 py-2">File</th>
                            <th class="px-4 py-2 w-24">Items</th>
                            <th class="px-4 py-2 w-28">Source</th>
                            <th class="px-4 py-2 w-40">Scanned</th>
                            <th class="px-4 py-2 w-20"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($files as $file)
                            <tr @class(['bg-gray-50 text-gray-400' => $file->reviewed])>
                                <td class="px-4 py-2">
                                    <form method="POST" action="{{ route('mudlogs.toggle', $file) }}">
                                        @csrf
                                        <input type="checkbox" onchange="this.form.submit()"
                                               @checked($file->reviewed)
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    </form>
                                </td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('mudlogs.show', $file) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                        {{ $file->filename }}
                                    </a>
                                    <div class="text-xs text-gray-500 truncate max-w-xl" title="{{ $file->path }}">
                                        {{ $file->path }}
                                    </div>
                                </td>
                                <td class="px-4 py-2">{{ $file->items_count }}</td>
                                <td class="px-4 py-2 text-xs uppercase">{{ $file->source }}</td>
                                <td class="px-4 py-2 text-xs">
                                    {{ $file->scanned_at?->diffForHumans() ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <form method="POST" action="{{ route('mudlogs.destroy', $file) }}"
                                          onsubmit="return confirm('Delete this log file and its items?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:text-red-800">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500 text-sm">
                                No log files yet. Scan a directory or upload files above.
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $files->links() }}</div>
        </div>
    </div>
</x-app-layout>
