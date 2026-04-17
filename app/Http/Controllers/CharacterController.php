<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\Race;
use App\Models\Sphere;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CharacterController extends Controller
{
    public function index()
    {
        $characters = Auth::user()->characters()
            ->with(['race', 'characterClass', 'sphere'])
            ->latest()
            ->get();

        return view('characters.index', compact('characters'));
    }

    public function create()
    {
        $races = Race::orderBy('name')->get();
        $classes = CharacterClass::orderBy('name')->get();
        $spheres = Sphere::orderBy('name')->get();

        return view('characters.create', compact('races', 'classes', 'spheres'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'race_id' => ['required', Rule::exists('races', 'id')],
            'character_class_id' => ['required', Rule::exists('character_classes', 'id')],
            'sphere_id' => ['nullable', Rule::exists('spheres', 'id')],
            'alignment' => ['required', Rule::in(['good', 'neutral', 'evil'])],
        ]);

        $race = Race::findOrFail($validated['race_id']);
        $class = CharacterClass::findOrFail($validated['character_class_id']);

        if ($class->exclusive_race_name && $class->exclusive_race_name !== $race->name) {
            return back()->withInput()->withErrors([
                'character_class_id' => "The {$class->name} class is exclusive to {$class->exclusive_race_name}s.",
            ]);
        }

        $forcedClass = CharacterClass::where('exclusive_race_name', $race->name)->first();
        if ($forcedClass && $forcedClass->id !== $class->id) {
            return back()->withInput()->withErrors([
                'character_class_id' => "{$race->name}s must be {$forcedClass->name}s.",
            ]);
        }

        $allowed = array_intersect($race->allowed_alignments, $class->allowed_alignments);

        if (! in_array($validated['alignment'], $allowed, true)) {
            return back()
                ->withInput()
                ->withErrors(['alignment' => 'Chosen alignment is not permitted for this race/class combination.']);
        }

        $character = Auth::user()->characters()->create($validated);

        return redirect()
            ->route('characters.show', $character)
            ->with('status', 'Character created!');
    }

    public function show(Character $character)
    {
        abort_unless($character->user_id === Auth::id(), 403);

        $character->load(['race', 'characterClass', 'sphere']);

        return view('characters.show', compact('character'));
    }

    public function edit(Character $character)
    {
        abort_unless($character->user_id === Auth::id(), 403);

        return view('characters.edit', compact('character'));
    }

    public function update(Request $request, Character $character)
    {
        abort_unless($character->user_id === Auth::id(), 403);

        $validated = $request->validate([
            'level' => ['required', 'integer', 'min:1', 'max:51'],
        ]);

        $character->update($validated);

        return redirect()
            ->route('characters.show', $character)
            ->with('status', 'Character updated.');
    }

    public function areas(Character $character)
    {
        abort_unless($character->user_id === Auth::id(), 403);

        $completed = $character->areas()->pluck('areas.id', 'areas.id');

        $areas = Area::orderBy('realm')->orderBy('name')->get()->map(function ($area) use ($character, $completed) {
            $area->completed = $completed->has($area->id);
            $area->in_range = $character->level >= $area->min_level && $character->level <= $area->max_level;
            return $area;
        });

        // Sort: unexplored in-range first, then unexplored out-of-range, then completed
        $sorted = $areas->sortBy(function ($a) {
            if ($a->completed) return 2;
            return $a->in_range ? 0 : 1;
        })->values();

        return view('characters.areas', [
            'character' => $character,
            'areas' => $sorted,
        ]);
    }

    public function toggleArea(Request $request, Character $character)
    {
        abort_unless($character->user_id === Auth::id(), 403);

        $validated = $request->validate([
            'area_id' => ['required', Rule::exists('areas', 'id')],
        ]);

        $existing = $character->areas()->where('areas.id', $validated['area_id'])->exists();

        if ($existing) {
            $character->areas()->detach($validated['area_id']);
        } else {
            $character->areas()->attach($validated['area_id'], ['completed_at' => now()]);
        }

        return back();
    }

    public function destroy(Character $character)
    {
        abort_unless($character->user_id === Auth::id(), 403);

        $character->delete();

        return redirect()->route('characters.index')->with('status', 'Character deleted.');
    }
}
