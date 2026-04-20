<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\LogFile;
use App\Services\LogScanner;
use Illuminate\Http\Request;

class MudLogController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $filter = $request->query('filter', 'all'); // all | reviewed | pending

        $query = LogFile::query()->withCount('items')->orderByDesc('created_at');

        if ($q !== '') {
            $query->where('filename', 'like', "%{$q}%");
        }
        if ($filter === 'reviewed') {
            $query->where('reviewed', true);
        } elseif ($filter === 'pending') {
            $query->where('reviewed', false);
        }

        $files = $query->paginate(50)->withQueryString();

        $counts = [
            'all' => LogFile::count(),
            'reviewed' => LogFile::where('reviewed', true)->count(),
            'pending' => LogFile::where('reviewed', false)->count(),
        ];

        return view('mudlogs.index', compact('files', 'q', 'filter', 'counts'));
    }

    public function show(LogFile $mudlog)
    {
        $mudlog->load(['items.protections', 'items.affects', 'items.flags']);
        return view('mudlogs.show', ['file' => $mudlog]);
    }

    public function toggleReviewed(LogFile $mudlog)
    {
        $mudlog->update(['reviewed' => ! $mudlog->reviewed]);
        return back();
    }

    public function destroy(LogFile $mudlog)
    {
        $mudlog->delete();
        return redirect()->route('mudlogs.index')->with('status', 'Log file deleted.');
    }

    public function scan(Request $request, LogScanner $scanner)
    {
        $data = $request->validate([
            'path' => ['required', 'string'],
        ]);
        try {
            $summary = $scanner->scanDirectory($data['path']);
        } catch (\Throwable $e) {
            return back()->withErrors(['path' => $e->getMessage()]);
        }
        return back()->with('status', sprintf(
            'Scan done. Files seen: %d, new/updated: %d, items ingested: %d',
            $summary['filesSeen'], $summary['filesNew'], $summary['itemsNew']
        ));
    }

    public function upload(Request $request, LogScanner $scanner)
    {
        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'mimes:txt', 'max:51200'], // 50MB each
        ]);

        $storeDir = storage_path('app/mudlogs/uploads');
        if (! is_dir($storeDir)) {
            @mkdir($storeDir, 0775, true);
        }

        $total = 0;
        $items = 0;
        foreach ($request->file('files') as $file) {
            $dest = $storeDir . DIRECTORY_SEPARATOR . date('YmdHis') . '_' . $file->getClientOriginalName();
            $file->move($storeDir, basename($dest));
            $res = $scanner->ingestFile($dest, basename($dest), 'upload');
            $total++;
            $items += $res['items_new'];
        }

        return back()->with('status', "Uploaded {$total} file(s), ingested {$items} item(s).");
    }

    public function items(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $type = $request->query('type');
        $slot = $request->query('slot');

        $query = Item::query()->with(['logFile', 'protections', 'affects', 'flags'])
            ->orderByDesc('created_at');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('keyword', 'like', "%{$q}%")
                  ->orWhere('material', 'like', "%{$q}%");
            });
        }
        if ($type) $query->where('item_type', $type);
        if ($slot) $query->where('slot', $slot);

        $items = $query->paginate(50)->withQueryString();

        $types = Item::whereNotNull('item_type')->distinct()->orderBy('item_type')->pluck('item_type');
        $slots = Item::whereNotNull('slot')->distinct()->orderBy('slot')->pluck('slot');

        return view('mudlogs.items', compact('items', 'q', 'type', 'slot', 'types', 'slots'));
    }
}
