<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Mud Logs</h2>
            <div class="flex gap-4 text-sm">
                @php($pc = \App\Models\Item::where('status','pending')->count())
                @if ($pc > 0)
                    <a href="{{ route('mudlogs.pending') }}" class="text-amber-700 hover:text-amber-900 font-medium">
                        Pending ({{ $pc }})
                    </a>
                @endif
                <a href="{{ route('mudlogs.items') }}" class="text-indigo-600 hover:text-indigo-900">
                    Item database &rarr;
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded">
                    {{ session('status') }}
                </div>
            @endif

            @php($queued = \Illuminate\Support\Facades\DB::table('jobs')->count())
            @if ($queued > 0)
                <div class="p-3 bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded flex items-center justify-between" x-data x-init="setTimeout(() => location.reload(), 5000)">
                    <span>
                        <strong>{{ $queued }}</strong> job(s) in queue. Run <code class="bg-white px-1 rounded">php artisan queue:work</code> to process.
                    </span>
                    <span class="text-xs text-blue-500">auto-refresh 5s</span>
                </div>
            @endif

            @if ($failedJobs->isNotEmpty())
                <div class="bg-red-50 border border-red-200 rounded" x-data="{ open: true, details: {} }">
                    <div class="flex items-center justify-between px-3 py-2 text-sm">
                        <button type="button" @click="open = !open" class="font-semibold text-red-800 hover:text-red-900 flex items-center gap-2">
                            <span x-text="open ? '▼' : '▶'"></span>
                            {{ $failedJobs->count() }} failed job(s)
                        </button>
                        <form method="POST" action="{{ route('mudlogs.failed.flush') }}"
                              onsubmit="return confirm('Remove all failed jobs?');">
                            @csrf
                            <button class="text-xs text-red-700 hover:text-red-900 underline">Clear all</button>
                        </form>
                    </div>
                    <div x-show="open" x-cloak class="border-t border-red-200 divide-y divide-red-100 text-sm">
                        @foreach ($failedJobs as $fj)
                            <div class="px-3 py-2">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-red-900 truncate" title="{{ $fj->path }}">
                                            {{ $fj->filename ?? $fj->job_name }}
                                        </div>
                                        @if ($fj->path)
                                            <div class="text-[11px] text-red-700/70 truncate">{{ $fj->path }}</div>
                                        @endif
                                        <div class="text-xs text-red-800 mt-1">{{ $fj->message }}</div>
                                        <div class="text-[10px] text-red-500 mt-0.5">{{ $fj->failed_at }}</div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <button type="button"
                                                @click="details['{{ $fj->uuid }}'] = !details['{{ $fj->uuid }}']"
                                                class="text-xs text-red-700 hover:text-red-900">Details</button>
                                        <form method="POST" action="{{ route('mudlogs.failed.retry', $fj->uuid) }}">
                                            @csrf
                                            <button class="text-xs text-indigo-700 hover:text-indigo-900">Retry</button>
                                        </form>
                                        <form method="POST" action="{{ route('mudlogs.failed.forget', $fj->uuid) }}">
                                            @csrf
                                            <button class="text-xs text-gray-600 hover:text-gray-900">Forget</button>
                                        </form>
                                    </div>
                                </div>
                                <pre x-show="details['{{ $fj->uuid }}']" x-cloak
                                     class="mt-2 p-2 bg-red-100 text-[10px] text-red-900 overflow-auto max-h-48 rounded whitespace-pre-wrap break-all">{{ $fj->exception }}</pre>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            @if ($errors->any())
                <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid md:grid-cols-2 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-4" x-data="fileUploader()">
                    <h3 class="font-semibold text-gray-800 mb-2">Upload Log Files</h3>
                    <form @submit.prevent="uploadFiles" class="space-y-2">
                        @csrf
                        <input type="file" x-ref="fileInput" @change="handleFileSelect" multiple accept=".txt"
                               class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <button :disabled="uploading || files.length === 0"
                                :class="uploading || files.length === 0 ? 'bg-gray-400 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700'"
                                class="w-full px-4 py-2 text-white text-sm rounded-md transition-colors">
                            <span x-show="!uploading">Upload & Process</span>
                            <span x-show="uploading">Uploading... <span x-text="currentFile"></span> / <span x-text="totalFiles"></span></span>
                        </button>
                    </form>

                    <!-- Overall Progress -->
                    <div x-show="uploading" class="mt-3">
                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                            <span>Overall Progress</span>
                            <span x-text="Math.round(overallProgress) + '%'"></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-indigo-600 h-2 rounded-full transition-all duration-300"
                                 :style="'width: ' + overallProgress + '%'"></div>
                        </div>
                    </div>

                    <!-- Current File Progress -->
                    <div x-show="uploading && currentProgress > 0" class="mt-2">
                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                            <span x-text="currentFileName"></span>
                            <span x-text="Math.round(currentProgress) + '%'"></span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-1.5">
                            <div class="bg-green-500 h-1.5 rounded-full transition-all duration-200"
                                 :style="'width: ' + currentProgress + '%'"></div>
                        </div>
                    </div>

                    <!-- Status Messages -->
                    <div x-show="statusMessage" x-text="statusMessage"
                         :class="statusType === 'error' ? 'text-red-600' : (statusType === 'success' ? 'text-green-600' : 'text-blue-600')"
                         class="mt-2 text-sm"></div>

                    <!-- Results Summary -->
                    <div x-show="results.length > 0" class="mt-3 p-2 bg-gray-50 rounded text-xs space-y-1">
                        <div class="font-medium text-gray-700">Results:</div>
                        <template x-for="result in results" :key="result.file">
                            <div class="flex justify-between">
                                <span x-text="result.file" class="truncate max-w-[200px]"></span>
                                <span :class="result.status === 'success' ? 'text-green-600' : 'text-red-600'"
                                      x-text="result.message"></span>
                            </div>
                        </template>
                    </div>

                    <p class="mt-2 text-xs text-gray-500">
                        Upload .txt log files from Mudlet (typically in ~/.config/mudlet/profiles/YourProfile/logs/)<br>
                        Files are processed one at a time so you can track progress.
                    </p>

                    <script>
                        function fileUploader() {
                            return {
                                files: [],
                                uploading: false,
                                currentFile: 0,
                                totalFiles: 0,
                                currentFileName: '',
                                currentProgress: 0,
                                overallProgress: 0,
                                statusMessage: '',
                                statusType: 'info',
                                results: [],

                                handleFileSelect(e) {
                                    this.files = Array.from(e.target.files).filter(f => f.name.endsWith('.txt'));
                                    this.results = [];
                                    this.statusMessage = this.files.length > 0 ? `${this.files.length} file(s) selected` : '';
                                },

                                async uploadFiles() {
                                    if (this.files.length === 0) return;

                                    this.uploading = true;
                                    this.totalFiles = this.files.length;
                                    this.currentFile = 0;
                                    this.results = [];
                                    this.statusMessage = 'Starting upload...';
                                    this.statusType = 'info';

                                    for (let i = 0; i < this.files.length; i++) {
                                        const file = this.files[i];
                                        this.currentFile = i + 1;
                                        this.currentFileName = file.name;
                                        this.currentProgress = 0;

                                        try {
                                            await this.uploadSingleFile(file);
                                            this.results.push({
                                                file: file.name,
                                                status: 'success',
                                                message: '✓ Done'
                                            });
                                        } catch (error) {
                                            this.results.push({
                                                file: file.name,
                                                status: 'error',
                                                message: '✗ Failed'
                                            });
                                        }

                                        this.overallProgress = ((i + 1) / this.totalFiles) * 100;
                                    }

                                    this.uploading = false;
                                    this.currentProgress = 100;
                                    this.statusMessage = `Completed: ${this.results.filter(r => r.status === 'success').length}/${this.totalFiles} files uploaded`;
                                    this.statusType = 'success';

                                    // Refresh page after 2 seconds to show new files
                                    setTimeout(() => window.location.reload(), 2000);
                                },

                                uploadSingleFile(file) {
                                    return new Promise((resolve, reject) => {
                                        const formData = new FormData();
                                        formData.append('files[]', file);
                                        formData.append('_token', document.querySelector('input[name="_token"]')?.value || '{{ csrf_token() }}');

                                        const xhr = new XMLHttpRequest();

                                        xhr.upload.addEventListener('progress', (e) => {
                                            if (e.lengthComputable) {
                                                this.currentProgress = (e.loaded / e.total) * 100;
                                            }
                                        });

                                        xhr.addEventListener('load', () => {
                                            if (xhr.status >= 200 && xhr.status < 300) {
                                                resolve(xhr.response);
                                            } else {
                                                reject(new Error(`HTTP ${xhr.status}`));
                                            }
                                        });

                                        xhr.addEventListener('error', () => reject(new Error('Network error')));
                                        xhr.addEventListener('abort', () => reject(new Error('Upload aborted')));

                                        xhr.open('POST', '{{ route('mudlogs.upload') }}');
                                        xhr.send(formData);
                                    });
                                }
                            }
                        }
                    </script>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 items-center">
                <form method="GET" action="{{ route('mudlogs.index') }}" class="flex flex-wrap gap-2 items-center flex-1">
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
                <form method="POST" action="{{ route('mudlogs.rescan-all') }}"
                      onsubmit="return confirm('Rescan ALL files? This will clear items and re-queue everything.');">
                    @csrf
                    <button class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">Rescan All</button>
                </form>
                <form method="POST" action="{{ route('mudlogs.clear-database') }}"
                      onsubmit="return confirm('WARNING: Delete ALL items? This cannot be undone. Log files will be preserved.');">
                    @csrf
                    <button class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-md hover:bg-red-700 border border-red-800 shadow-sm">Clear Items DB</button>
                </form>
            </div>

            <form method="POST" action="{{ route('mudlogs.bulk') }}"
                  x-data="{ selected: [], all: false, toggleAll() { this.all = !this.all; this.selected = this.all ? [...document.querySelectorAll('[data-row-id]')].map(el => el.dataset.rowId) : []; } }">
                @csrf
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="flex items-center gap-2 px-4 py-2 bg-gray-50 border-b border-gray-200 text-sm"
                         x-show="selected.length > 0" x-cloak>
                        <span class="text-gray-600"><strong x-text="selected.length"></strong> selected</span>
                        <span class="text-gray-300">|</span>
                        <button type="submit" name="action" value="rescan"
                                class="px-3 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-xs">Rescan</button>
                        <button type="submit" name="action" value="mark_reviewed"
                                class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-xs">Mark reviewed</button>
                        <button type="submit" name="action" value="mark_unreviewed"
                                class="px-3 py-1 bg-gray-500 text-white rounded hover:bg-gray-600 text-xs">Mark unreviewed</button>
                        <button type="submit" name="action" value="delete"
                                @click.prevent="if (confirm(`Delete ${selected.length} file(s) and their items?`)) $event.target.closest('form').submit()"
                                class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 text-xs ml-auto">Delete</button>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-2 w-8">
                                    <input type="checkbox" @click="toggleAll()" :checked="all"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                </th>
                                <th class="px-4 py-2">File</th>
                                <th class="px-4 py-2 w-24">Items</th>
                                <th class="px-4 py-2 w-28">Source</th>
                                <th class="px-4 py-2 w-40">Scanned</th>
                                <th class="px-4 py-2 w-20"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($files as $file)
                                <tr data-row-id="{{ $file->id }}" @class(['bg-gray-50 text-gray-400' => $file->reviewed])>
                                    <td class="px-4 py-2">
                                        <input type="checkbox" name="ids[]" value="{{ $file->id }}"
                                               x-model="selected"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    </td>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('mudlogs.show', $file) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                            {{ $file->filename }}
                                        </a>
                                        @if ($file->reviewed)
                                            <span class="ml-1 text-[10px] px-1.5 py-0.5 bg-green-100 text-green-700 rounded">reviewed</span>
                                        @endif
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
                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button"
                                                    data-rescan-url="{{ route('mudlogs.rescan', $file) }}"
                                                    data-filename="{{ $file->filename }}"
                                                    @click="if (confirm('Rescan ' + $el.dataset.filename + '?')) { const f = document.getElementById('row-rescan-form'); f.action = $el.dataset.rescanUrl; f.submit(); }"
                                                    class="text-xs text-indigo-600 hover:text-indigo-900" title="Delete items and re-parse this file">Rescan</button>
                                            <button type="button"
                                                    data-delete-url="{{ route('mudlogs.destroy', $file) }}"
                                                    @click="if (confirm('Delete this log file and its items?')) { const f = document.getElementById('row-delete-form'); f.action = $el.dataset.deleteUrl; f.submit(); }"
                                                    class="text-xs text-red-600 hover:text-red-800">Delete</button>
                                        </div>
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
            </form>

            {{-- Hidden helper forms used by per-row Rescan/Delete buttons --}}
            <form id="row-rescan-form" method="POST" class="hidden">@csrf</form>
            <form id="row-delete-form" method="POST" class="hidden">@csrf @method('DELETE')</form>

            <div>{{ $files->links() }}</div>
        </div>
    </div>
</x-app-layout>
