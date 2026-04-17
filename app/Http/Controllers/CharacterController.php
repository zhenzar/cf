<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\Race;
use App\Models\Sphere;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function destroy(Character $character)
    {
        abort_unless($character->user_id === Auth::id(), 403);

        $character->delete();

        return redirect()->route('characters.index')->with('status', 'Character deleted.');
    }
}
