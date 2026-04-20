<?php

namespace App\Http\Controllers;

use App\Jobs\IngestLogFile;
use App\Jobs\ScanDirectory;
use App\Models\Item;
use App\Models\LogFile;
use App\Services\LogScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $mudlog->load(['items' => fn ($q) => $q->orderBy('name'),
            'items.protections', 'items.affects', 'items.flags', 'items.spells']);
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

    /**
     * Bulk actions on a selection of log files (rescan / delete / toggle reviewed).
     */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', 'in:rescan,delete,mark_reviewed,mark_unreviewed'],
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer'],
        ]);

        $files = LogFile::whereIn('id', $data['ids'])->get();
        if ($files->isEmpty()) {
            return back()->withErrors(['ids' => 'No files selected.']);
        }

        switch ($data['action']) {
            case 'rescan':
                $queued = 0;
                foreach ($files as $mudlog) {
                    if (! is_file($mudlog->path)) {
                        continue;
                    }
                    DB::transaction(function () use ($mudlog) {
                        $mudlog->items()->detach();
                        $orphanIds = Item::where('log_file_id', $mudlog->id)
                            ->whereDoesntHave('logFiles')->pluck('id');
                        if ($orphanIds->isNotEmpty()) {
                            Item::whereIn('id', $orphanIds)->delete();
                        }
                        $mudlog->update(['scanned_at' => null, 'items_count' => 0]);
                    });
                    IngestLogFile::dispatch($mudlog->path, $mudlog->filename, $mudlog->source ?? 'scan');
                    $queued++;
                }
                return back()->with('status', "Queued rescan for {$queued} file(s).");

            case 'delete':
                $count = $files->count();
                foreach ($files as $f) { $f->delete(); }
                return back()->with('status', "Deleted {$count} file(s).");

            case 'mark_reviewed':
                LogFile::whereIn('id', $files->pluck('id'))->update(['reviewed' => true]);
                return back()->with('status', "Marked {$files->count()} file(s) as reviewed.");

            case 'mark_unreviewed':
                LogFile::whereIn('id', $files->pluck('id'))->update(['reviewed' => false]);
                return back()->with('status', "Marked {$files->count()} file(s) as unreviewed.");
        }

        return back();
    }

    /**
     * Re-parse a single log file. Deletes its existing items first, then
     * queues a fresh ingest so the updated parser rules apply.
     */
    public function rescan(LogFile $mudlog)
    {
        if (! is_file($mudlog->path)) {
            return back()->withErrors(['path' => "File no longer exists: {$mudlog->path}"]);
        }

        // Detach this file from all items and delete items that were originally
        // seen here AND aren't attached to any other log file. Items shared with
        // other logs are kept; they will simply be re-attached by the ingest job.
        DB::transaction(function () use ($mudlog) {
            $mudlog->items()->detach();

            // Delete original items only if they aren't referenced by another log.
            $orphanIds = Item::where('log_file_id', $mudlog->id)
                ->whereDoesntHave('logFiles')
                ->pluck('id');
            if ($orphanIds->isNotEmpty()) {
                Item::whereIn('id', $orphanIds)->delete();
            }

            $mudlog->update(['scanned_at' => null, 'items_count' => 0]);
        });

        IngestLogFile::dispatch($mudlog->path, $mudlog->filename, $mudlog->source ?? 'scan');

        return back()->with('status', "Rescan queued for {$mudlog->filename}.");
    }

    public function scan(Request $request)
    {
        $data = $request->validate([
            'path' => ['required', 'string'],
        ]);
        if (! is_dir($data['path'])) {
            return back()->withErrors(['path' => "Not a directory: {$data['path']}"]);
        }

        ScanDirectory::dispatch($data['path']);

        return back()->with('status', "Scan queued. Items will appear as the worker processes files (run: php artisan queue:work).");
    }

    public function upload(Request $request)
    {
        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'mimes:txt', 'max:10240'], // .txt only, max 10MB each
        ]);

        $storeDir = storage_path('app/mudlogs/uploads');
        if (! is_dir($storeDir)) {
            @mkdir($storeDir, 0775, true);
        }

        $total = 0;
        foreach ($request->file('files') as $file) {
            $dest = $storeDir . DIRECTORY_SEPARATOR . date('YmdHis') . '_' . $file->getClientOriginalName();
            $file->move($storeDir, basename($dest));
            IngestLogFile::dispatch($dest, basename($dest), 'upload');
            $total++;
        }

        return back()->with('status', "Queued {$total} file(s) for processing.");
    }

    public function items(Request $request)
    {
        $all = Item::query()
            ->where('status', 'confirmed')
            ->with(['logFile', 'protections', 'affects', 'flags', 'spells'])
            ->orderBy('level')->orderBy('name')
            ->get();

        // Preferred slot order (non-weapons). Slot 'Finger' is shown as 'Rings'.
        $slotOrder = ['Finger', 'Neck', 'Body', 'Head', 'Face', 'Legs', 'Feet', 'Hands', 'Arms', 'Waist', 'Wrist'];
        $slotLabels = ['Finger' => 'Rings'];

        // Preferred weapon-class order.
        $weaponOrder = ['Axe', 'Sword', 'Mace', 'Whip', 'Flail', 'Dagger', 'Spear', 'Polearm', 'Staff', 'Club', 'Hammer', 'Bow', 'Crossbow'];

        // Item types that should get their own top-level group (not tied to slot/weapon_class).
        $typeGroups = ['Shield', 'Potion', 'Scroll', 'Wand', 'Lockpicks'];

        $groups = [];
        foreach ($slotOrder as $s) {
            $groups[$slotLabels[$s] ?? $s] = collect();
        }
        foreach ($weaponOrder as $wc) {
            $groups[$wc] = collect();
        }
        foreach ($typeGroups as $t) {
            $groups[$t] = collect();
        }

        $otherNonWeapon = collect();
        $otherWeapon = collect();

        foreach ($all as $item) {
            if ($item->weapon_class) {
                $key = ucfirst(strtolower($item->weapon_class));
                if (array_key_exists($key, $groups)) {
                    $groups[$key]->push($item);
                } else {
                    $otherWeapon->push($item);
                }
                continue;
            }
            // Group by item_type for non-slot items (shields, potions, scrolls, ...).
            $type = $item->item_type ? ucfirst(strtolower($item->item_type)) : null;
            if ($type && in_array($type, $typeGroups, true)) {
                $groups[$type]->push($item);
                continue;
            }
            $slot = $item->slot;
            if ($slot && in_array($slot, $slotOrder, true)) {
                $label = $slotLabels[$slot] ?? $slot;
                $groups[$label]->push($item);
            } else {
                $otherNonWeapon->push($item);
            }
        }

        // Append dynamic "Other" buckets for unknown weapon classes / unmapped slots.
        if ($otherWeapon->isNotEmpty()) {
            foreach ($otherWeapon->groupBy(fn ($i) => ucfirst(strtolower($i->weapon_class))) as $k => $v) {
                $groups[$k] = $v->values();
            }
        }
        if ($otherNonWeapon->isNotEmpty()) {
            $groups['Other'] = $otherNonWeapon->values();
        }

        // Drop empty groups.
        $groups = collect($groups)->filter(fn ($g) => $g->isNotEmpty())->all();

        $pendingCount = Item::where('status', 'pending')->count();
        $totalCount = $all->count();

        return view('mudlogs.items', compact('groups', 'pendingCount', 'totalCount'));
    }

    public function pending()
    {
        $pending = Item::where('status', 'pending')
            ->with(['logFile', 'protections', 'affects', 'flags', 'spells'])
            ->orderBy('name')->orderBy('created_at')
            ->get();

        // Preload existing confirmed items per name to show side-by-side.
        $names = $pending->pluck('name')->unique()->values();
        $existing = Item::where('status', 'confirmed')
            ->whereIn('name', $names)
            ->with(['protections', 'affects', 'flags', 'logFile'])
            ->get()
            ->groupBy('name');

        return view('mudlogs.pending', compact('pending', 'existing'));
    }

    public function confirmPending(Item $item)
    {
        abort_unless($item->status === 'pending', 404);
        $item->update(['status' => 'confirmed']);
        return back()->with('status', 'Item added.');
    }

    public function ignorePending(Item $item)
    {
        abort_unless($item->status === 'pending', 404);
        $item->delete();
        return back()->with('status', 'Item ignored.');
    }
}
