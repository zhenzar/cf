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
            'files.*' => ['file', 'mimes:txt', 'max:51200'], // 50MB each
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
            ->with(['logFile', 'protections', 'affects', 'flags'])
            ->orderBy('level')->orderBy('name')
            ->get();

        // Preferred slot order (non-weapons). Slot 'Finger' is shown as 'Rings'.
        $slotOrder = ['Finger', 'Neck', 'Body', 'Head', 'Face', 'Legs', 'Feet', 'Hands', 'Arms', 'Waist', 'Wrist'];
        $slotLabels = ['Finger' => 'Rings'];

        // Preferred weapon-class order.
        $weaponOrder = ['Axe', 'Sword', 'Mace', 'Whip', 'Flail', 'Dagger', 'Spear', 'Polearm', 'Staff', 'Club', 'Hammer', 'Bow', 'Crossbow'];

        $groups = [];
        foreach ($slotOrder as $s) {
            $groups[$slotLabels[$s] ?? $s] = collect();
        }
        foreach ($weaponOrder as $wc) {
            $groups[$wc] = collect();
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
            ->with(['logFile', 'protections', 'affects', 'flags'])
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
