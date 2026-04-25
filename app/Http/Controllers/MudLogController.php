<?php

namespace App\Http\Controllers;

use App\Jobs\IngestLogFile;
use App\Jobs\RescanLogFile;
use App\Jobs\ScanDirectory;
use App\Models\Area;
use App\Models\Item;
use App\Models\LogFile;
use App\Services\LogScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MudLogController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isZhenzar = $user->email === 'zhenzar@gmail.com';

        $q = trim((string) $request->query('q', ''));
        $filter = $request->query('filter', 'all'); // all | reviewed | pending

        $query = LogFile::query()->withCount('items')->orderByDesc('created_at');

        // Non-zhenzar users only see their own files
        if (! $isZhenzar) {
            $query->where('user_id', $user->id);
        }

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

        $failedJobs = DB::table('failed_jobs')->orderByDesc('id')->limit(50)->get()
            ->map(function ($row) {
                $payload = json_decode($row->payload, true);
                $command = $payload['data']['command'] ?? '';
                $path = null;
                $filename = null;
                if ($command && preg_match('/"path";s:\d+:"([^"]*)"/', $command, $m)) {
                    $path = $m[1];
                }
                if ($command && preg_match('/"filename";s:\d+:"([^"]*)"/', $command, $m)) {
                    $filename = $m[1];
                }
                $exception = $row->exception ?? '';
                $firstLine = strtok($exception, "\n");
                return (object) [
                    'id' => $row->id,
                    'uuid' => $row->uuid,
                    'job_name' => $payload['displayName'] ?? 'Unknown job',
                    'path' => $path,
                    'filename' => $filename ?: ($path ? basename($path) : null),
                    'message' => $firstLine,
                    'exception' => $exception,
                    'failed_at' => $row->failed_at,
                ];
            });

        return view('mudlogs.index', compact('files', 'q', 'filter', 'counts', 'failedJobs'));
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
     * Retry a single failed job by uuid.
     */
    public function retryFailedJob(string $uuid)
    {
        \Artisan::call('queue:retry', ['id' => [$uuid]]);
        return back()->with('status', 'Failed job queued for retry.');
    }

    /**
     * Forget a single failed job by uuid.
     */
    public function forgetFailedJob(string $uuid)
    {
        DB::table('failed_jobs')->where('uuid', $uuid)->delete();
        return back()->with('status', 'Failed job removed.');
    }

    /**
     * Remove all failed jobs.
     */
    public function flushFailedJobs()
    {
        DB::table('failed_jobs')->delete();
        return back()->with('status', 'All failed jobs removed.');
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
                    RescanLogFile::dispatch($mudlog->id);
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
     * Re-parse a single log file. Queues a RescanLogFile job.
     */
    public function rescan(LogFile $mudlog)
    {
        if (! is_file($mudlog->path)) {
            return back()->withErrors(['path' => "File no longer exists: {$mudlog->path}"]);
        }

        RescanLogFile::dispatch($mudlog->id);

        return back()->with('status', "Rescan queued for {$mudlog->filename}.");
    }

    /**
     * Re-parse all existing log files. Queues RescanLogFile jobs (one per file).
     */
    public function rescanAll()
    {
        $queued = 0;

        LogFile::select('id', 'path', 'filename', 'source')
            ->chunkById(100, function ($files) use (&$queued) {
                foreach ($files as $mudlog) {
                    if (! is_file($mudlog->path)) {
                        continue;
                    }
                    RescanLogFile::dispatch($mudlog->id);
                    $queued++;
                }
            });

        return back()->with('status', "Queued rescan for {$queued} file(s).");
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

        $user = Auth::user();
        $isZhenzar = $user->email === 'zhenzar@gmail.com';

        // Use user-specific directory for non-zhenzar users
        if ($isZhenzar) {
            $storeDir = storage_path('app/mudlogs/uploads');
        } else {
            $storeDir = storage_path('app/mudlogs/users/' . $user->id);
        }

        if (! is_dir($storeDir)) {
            @mkdir($storeDir, 0775, true);
        }

        $processed = 0;
        $skipped = 0;

        foreach ($request->file('files') as $file) {
            $content = file_get_contents($file->getPathname());
            $contentHash = hash('sha256', $content);

            // Check if this exact content already exists for this user
            $existing = LogFile::where('content_hash', $contentHash)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $skipped++;
                continue; // Silently skip duplicate
            }

            $dest = $storeDir . DIRECTORY_SEPARATOR . date('YmdHis') . '_' . $file->getClientOriginalName();
            $file->move($storeDir, basename($dest));
            IngestLogFile::dispatch($dest, basename($dest), 'upload', null, $user->id);
            $processed++;
        }

        // Only show status if files were actually processed
        if ($processed > 0) {
            return back()->with('status', "Processed {$processed} file(s).");
        }

        // Silently return for skipped duplicates
        return back();
    }

    public function items(Request $request)
    {
        // Build query with filters
        $query = Item::query()
            ->where('status', 'confirmed')
            ->select('id', 'log_file_id', 'name', 'keyword', 'level', 'item_type', 'slot', 'slot_override', 'material',
                     'weapon_class', 'weapon_qualifier', 'damage_type', 'attack_type', 'damage_dice',
                     'av_damage', 'worth_copper', 'weight_pounds', 'weight_ounces', 'alignment', 'status', 'area_id')
            ->with(['logFile:id,filename', 'protections', 'affects', 'flags', 'spells', 'area']);
        
        // Apply type filter
        if ($request->filled('type')) {
            $query->where('item_type', $request->input('type'));
        }
        
        // Apply material filter
        if ($request->filled('material')) {
            $query->where('material', $request->input('material'));
        }
        
        // Apply flag filter
        if ($request->filled('flag')) {
            $flag = $request->input('flag');
            $query->whereHas('flags', function ($q) use ($flag) {
                $q->where('flag', $flag);
            });
        }
        
        // Apply attack_type filter
        if ($request->filled('attack_type')) {
            $query->where('attack_type', $request->input('attack_type'));
        }
        
        // Apply sorting
        $sortBy = $request->input('sort_by', 'level');
        $sortOrder = $request->input('sort_order', 'asc');
        
        $allowedSorts = ['level', 'av_damage', 'weight_pounds', 'weight_ounces', 'name'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('level', 'asc');
        }
        
        $query->orderBy('name'); // Secondary sort always by name
        
        $all = $query->get();

        // Preferred slot order (non-weapons). Slot 'Finger' is shown as 'Rings'.
        $slotOrder = ['Finger', 'Neck', 'Body', 'About', 'Head', 'Face', 'Legs', 'Feet', 'Hands', 'Arms', 'Waist', 'Wrist', 'Ears', 'Back'];
        $slotLabels = ['Finger' => 'Rings'];

        // Exotic form slots (shapeshifter equipment) get their own groups.
        $exoticSlots = ['Hooves', 'Wings', 'Tail', 'Claws', 'Forepaws', 'Hindpaws', 'Horns', 'Foreclaws'];
        
        // Special slots that have type-based grouping priority (check type before slot)
        $specialSlotTypes = ['Shield' => 'Shield', 'Hold' => 'Held']; // slot => group name
        
        // Item types that override slot-based grouping (e.g., instruments held in hand)
        $typeOverridesSlot = ['Instrument'];

        // Preferred weapon-class order.
        $weaponOrder = ['Axe', 'Sword', 'Mace', 'Whip', 'Flail', 'Dagger', 'Spear', 'Polearm', 'Staff', 'Club', 'Hammer', 'Bow', 'Crossbow', 'Exotic'];

        // Item types that should get their own top-level group (not tied to slot/weapon_class).
        $typeGroups = ['Shield', 'Potion', 'Scroll', 'Wand', 'Talisman', 'Lockpicks', 'Food', 'Treasure', 'Miscellaneous', 'Drink Container', 'Key', 'Container', 'Instrument', 'Light', 'Ingredient', 'Artifact', 'Pill', 'Boat', 'Pen'];

        $groups = [];
        foreach ($slotOrder as $s) {
            $groups[$slotLabels[$s] ?? $s] = collect();
        }
        foreach ($exoticSlots as $s) {
            $groups[$s] = collect();
        }
        foreach ($weaponOrder as $wc) {
            $groups[$wc] = collect();
        }
        foreach ($typeGroups as $t) {
            $groups[$t] = collect();
        }
        
        // Initialize special slot groups
        foreach ($specialSlotTypes as $groupName) {
            if (!isset($groups[$groupName])) {
                $groups[$groupName] = collect();
            }
        }

        $otherNonWeapon = collect();
        $otherWeapon = collect();

        foreach ($all as $item) {
            // Check for weapons (by weapon_class or wield slot)
            if ($item->weapon_class) {
                $key = ucfirst(strtolower($item->weapon_class));
                if (array_key_exists($key, $groups)) {
                    $groups[$key]->push($item);
                } else {
                    $otherWeapon->push($item);
                }
                continue;
            }
            
            // Items with Wield slot but no weapon_class are still weapons
            if ($item->slot === 'Wield') {
                $otherWeapon->push($item);
                continue;
            }
            
            // Check type overrides first (instruments, etc. that should have their own category)
            $type = $item->item_type ? ucfirst(strtolower($item->item_type)) : null;
            if ($type && in_array($type, $typeOverridesSlot, true)) {
                $groups[$type]->push($item);
                continue;
            }
            
            // Check special slot+type combinations (e.g., Shields)
            if ($item->slot && isset($specialSlotTypes[$item->slot])) {
                $groups[$specialSlotTypes[$item->slot]]->push($item);
                continue;
            }

            // Check slot-based grouping (for wearable equipment like rings, neck, etc.)
            $slot = $item->slot;
            if ($slot && in_array($slot, $slotOrder, true)) {
                $label = $slotLabels[$slot] ?? $slot;
                $groups[$label]->push($item);
                continue;
            }
            if ($slot && in_array($slot, $exoticSlots, true)) {
                $groups[$slot]->push($item);
                continue;
            }

            // Group by item_type for non-slot items (potions, scrolls, ...).
            if ($type && in_array($type, $typeGroups, true)) {
                $groups[$type]->push($item);
                continue;
            }

            $otherNonWeapon->push($item);
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
        
        // Organize items into type-based categories for separate tables
        $tableGroups = [];
        $typeOrder = ['Weapon', 'Armor', 'Clothing', 'Potion', 'Scroll', 'Wand', 'Staff', 'Talisman', 'Shield', 'Container', 'Instrument', 'Food', 'Treasure', 'Boat', 'Key', 'Light', 'Ingredient', 'Pill', 'Miscellaneous'];
        
        // Initialize type groups
        foreach ($typeOrder as $type) {
            $tableGroups[$type] = collect();
        }
        $tableGroups['Other'] = collect();
        
        // Categorize all items by type
        foreach ($all as $item) {
            $type = $item->item_type ? ucfirst(strtolower($item->item_type)) : null;
            
            if ($type && isset($tableGroups[$type])) {
                $tableGroups[$type]->push($item);
            } elseif ($item->weapon_class || $item->slot === 'Wield') {
                $tableGroups['Weapon']->push($item);
            } else {
                $tableGroups['Other']->push($item);
            }
        }
        
        // Remove empty groups
        $tableGroups = collect($tableGroups)->filter(fn ($g) => $g->isNotEmpty())->all();

        $pendingCount = Item::where('status', 'pending')->count();
        $totalCount = $all->count();
        
        // Get filter options
        $types = Item::where('status', 'confirmed')->whereNotNull('item_type')->distinct()->pluck('item_type')->sort()->values();
        $materials = Item::where('status', 'confirmed')->whereNotNull('material')->distinct()->pluck('material')->sort()->values();
        $attackTypes = Item::where('status', 'confirmed')->whereNotNull('attack_type')->distinct()->pluck('attack_type')->sort()->values();
        $flags = \App\Models\ItemFlag::whereHas('item', function ($q) {
            $q->where('status', 'confirmed');
        })->distinct()->pluck('flag')->sort()->values();
        
        // Current filter values
        $currentType = $request->input('type');
        $currentMaterial = $request->input('material');
        $currentAttackType = $request->input('attack_type');
        $currentFlag = $request->input('flag');
        $currentSortBy = $sortBy;
        $currentSortOrder = $sortOrder;

        return view('mudlogs.items', compact(
            'groups', 'tableGroups', 'pendingCount', 'totalCount',
            'types', 'materials', 'attackTypes', 'flags',
            'currentType', 'currentMaterial', 'currentAttackType', 'currentFlag',
            'currentSortBy', 'currentSortOrder'
        ));
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

    /**
     * Show edit form for an item.
     */
    public function editItem(Item $item)
    {
        $slots = ['Finger', 'Neck', 'Body', 'Head', 'Face', 'Legs', 'Feet', 'Hands', 'Arms', 'Waist', 'Wrist', 'Shield', 'Hold', 'Wield'];
        $areas = Area::orderBy('name')->pluck('name', 'id');
        return view('mudlogs.items-edit', compact('item', 'slots', 'areas'));
    }

    /**
     * Update an item's editable fields.
     */
    public function updateItem(Request $request, Item $item)
    {
        $data = $request->validate([
            'slot_override' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:5000',
            'area_id' => 'nullable|exists:areas,id',
        ]);

        // Only allow slot_override for treasure items
        if ($item->item_type !== 'Treasure') {
            unset($data['slot_override']);
        }

        $item->update($data);

        return redirect()->route('mudlogs.items')->with('status', "Item '{$item->name}' updated.");
    }

    /**
     * Clear all items from database and reset log file counts.
     */
    public function clearDatabase()
    {
        // Delete all item relations first via truncate
        DB::statement('DELETE FROM item_protections');
        DB::statement('DELETE FROM item_affects');
        DB::statement('DELETE FROM item_flags');
        DB::statement('DELETE FROM item_spells');
        DB::statement('DELETE FROM item_log_file');
        DB::statement('DELETE FROM items');

        // Reset log file counts
        LogFile::query()->update(['items_count' => 0, 'scanned_at' => null]);

        return back()->with('status', 'Item database cleared. ' . LogFile::count() . ' log files preserved.');
    }
}
