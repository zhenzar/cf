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
            ->withCount(['areas as areas_completed_count'])
            ->latest()
            ->get();

        $totalAreas = \App\Models\Area::count();

        return view('dashboard', compact('characters', 'totalAreas'));
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

    public function areas(Request $request, Character $character)
    {
        abort_unless($character->user_id === Auth::id(), 403);

        $filter = $request->query('filter', 'in-range');
        if (! in_array($filter, ['in-range', 'out-of-range', 'completed', 'all'], true)) {
            $filter = 'in-range';
        }
        $q = trim((string) $request->query('q', ''));
        // When searching, always search across all areas regardless of filter.
        if ($q !== '') {
            $filter = 'all';
        }

        $completed = $character->areas()->pluck('areas.id', 'areas.id');
        $level = $character->level;

        $query = Area::orderBy('realm')->orderBy('name');
        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }

        $all = $query->get()
            ->map(function ($area) use ($completed, $level) {
                $area->completed = $completed->has($area->id);
                $area->is_all = $area->min_level === 1 && $area->max_level === 51;
                $area->level_appropriate = $level >= $area->min_level && $area->max_level <= $level;
                $area->in_range = $area->level_appropriate || $area->is_all;
                return $area;
            });

        $counts = [
            'in-range' => $all->where('in_range', true)->where('completed', false)->count(),
            'out-of-range' => $all->where('in_range', false)->where('completed', false)->count(),
            'completed' => $all->where('completed', true)->count(),
            'all' => $all->count(),
        ];

        $areas = match ($filter) {
            'in-range' => $all->filter(fn ($a) => $a->in_range && ! $a->completed),
            'out-of-range' => $all->filter(fn ($a) => ! $a->in_range && ! $a->completed),
            'completed' => $all->filter(fn ($a) => $a->completed),
            'all' => $all,
        };

        $areas = $areas
            ->sortBy(function ($a) {
                if ($a->completed) return 2;
                if ($a->level_appropriate) return 0;
                if ($a->is_all) return 1;
                return 3; // out of range last within 'all'
            })
            ->values();

        return view('characters.areas', [
            'character' => $character,
            'areas' => $areas,
            'filter' => $filter,
            'counts' => $counts,
            'q' => $q,
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

        if ((int) session('active_character_id') === $character->id) {
            session()->forget('active_character_id');
        }

        return redirect()->route('characters.index')->with('status', 'Character deleted.');
    }

    /**
     * Set the active character stored in session (used by the sidebar selector).
     * Passing id=0 clears the selection.
     */
    public function setActive(Request $request)
    {
        $id = (int) $request->input('character_id', 0);
        if ($id === 0) {
            session()->forget('active_character_id');
        } else {
            $character = Character::where('user_id', Auth::id())->findOrFail($id);
            session(['active_character_id' => $character->id]);
        }
        return back();
    }

    /**
     * Areas overview.
     * - If a character is active in the session → show that character's tracked areas.
     * - Otherwise → plain list of all areas.
     */
    public function areasIndex(Request $request)
    {
        $activeId = session('active_character_id');
        if ($activeId) {
            $character = Character::where('user_id', Auth::id())->find($activeId);
            if ($character) {
                return $this->areas($request, $character);
            }
        }

        $q = trim((string) $request->query('q', ''));
        $query = Area::orderBy('realm')->orderBy('name');
        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }
        $areas = $query->get();

        return view('areas.index', compact('areas', 'q'));
    }
}
