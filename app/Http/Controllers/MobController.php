<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Item;
use App\Models\Mob;
use Illuminate\Http\Request;

class MobController extends Controller
{
    public function index(Request $request)
    {
        $query = Mob::query()->with(['area', 'equipment']);

        // Filter by area
        if ($request->filled('area_id')) {
            $query->where('area_id', $request->area_id);
        }

        // Sort by area
        if ($request->sort === 'area') {
            $query->orderByRaw('(SELECT name FROM areas WHERE areas.id = mobs.area_id) ASC');
        } else {
            $query->orderBy('name');
        }

        $mobs = $query->paginate(50)->withQueryString();
        $areas = Area::orderBy('name')->pluck('name', 'id');

        return view('mobs.index', compact('mobs', 'areas'));
    }

    public function create()
    {
        $areas = Area::orderBy('name')->pluck('name', 'id');
        $slots = ['mainhand', 'offhand', 'head', 'face', 'neck', 'body', 'arms', 'hands', 'waist', 'legs', 'feet', 'shield', 'hold', 'wield', 'floating'];

        return view('mobs.create', compact('areas', 'slots'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'area_id' => 'nullable|exists:areas,id',
            'notes' => 'nullable|string',
            'equipment' => 'nullable|array',
            'equipment.*.slot' => 'required_with:equipment|string',
            'equipment.*.item_name' => 'required_with:equipment|string',
        ]);

        $mob = Mob::create([
            'name' => $validated['name'],
            'area_id' => $validated['area_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if (! empty($validated['equipment'])) {
            foreach ($validated['equipment'] as $eq) {
                if (! empty($eq['item_name'])) {
                    // Try to find matching item
                    $item = Item::where('name', $eq['item_name'])->first();

                    $mob->equipment()->create([
                        'slot' => $eq['slot'],
                        'item_name' => $eq['item_name'],
                        'item_id' => $item?->id,
                    ]);
                }
            }
        }

        return redirect()->route('mobs.index')->with('status', 'Mob created.');
    }

    public function edit(Mob $mob)
    {
        $mob->load('equipment');
        $areas = Area::orderBy('name')->pluck('name', 'id');
        $slots = ['mainhand', 'offhand', 'head', 'face', 'neck', 'body', 'arms', 'hands', 'waist', 'legs', 'feet', 'shield', 'hold', 'wield', 'floating'];

        return view('mobs.edit', compact('mob', 'areas', 'slots'));
    }

    public function update(Request $request, Mob $mob)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'area_id' => 'nullable|exists:areas,id',
            'notes' => 'nullable|string',
            'equipment' => 'nullable|array',
            'equipment.*.slot' => 'required_with:equipment|string',
            'equipment.*.item_name' => 'required_with:equipment|string',
        ]);

        $mob->update([
            'name' => $validated['name'],
            'area_id' => $validated['area_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Delete existing equipment and recreate
        $mob->equipment()->delete();

        if (! empty($validated['equipment'])) {
            foreach ($validated['equipment'] as $eq) {
                if (! empty($eq['item_name'])) {
                    $item = Item::where('name', $eq['item_name'])->first();

                    $mob->equipment()->create([
                        'slot' => $eq['slot'],
                        'item_name' => $eq['item_name'],
                        'item_id' => $item?->id,
                    ]);
                }
            }
        }

        return redirect()->route('mobs.index')->with('status', 'Mob updated.');
    }

    public function destroy(Mob $mob)
    {
        $mob->delete();
        return redirect()->route('mobs.index')->with('status', 'Mob deleted.');
    }
}
