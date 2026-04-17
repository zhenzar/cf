<?php

namespace Database\Seeders;

use App\Models\Race;
use Illuminate\Database\Seeder;

class RaceSeeder extends Seeder
{
    public function run(): void
    {
        $races = [
            ['Human',         0,   ['good', 'neutral', 'evil'], 'The most populous race. Average stats'],
            ['Elf',           400, ['good'],                    'Very smart & quick, but frail'],
            ['Half-Elf',      0,   ['good', 'neutral', 'evil'], 'Human/Elf mix. Intelligent, fast, and wise'],
            ['Half-Drow',     0,   ['good', 'neutral', 'evil'], 'Human/Drow mix. Intelligent, fast, and wise'],
            ['Dark-Elf',      400, ['evil'],                    'Evil elves, smart and quick'],
            ['Storm Giant',   500, ['good'],                    'Good giants, Strong, smartest of Giants'],
            ['Cloud Giant',   500, ['neutral'],                 'Neutral giants that fly, Strong and Stout'],
            ['Fire Giant',    500, ['evil'],                    'Evil giants. Very strong, stout, very dumb'],
            ['Arial',         300, ['good', 'neutral', 'evil'], 'Agile bird-like creatures that fly, smart'],
            ['Felar',         250, ['good', 'neutral', 'evil'], 'Modified cats, agile and tough but weakened'],
            ['Dwarf',         250, ['good', 'neutral'],         'Healthy little folk, but not too nimble'],
            ['Duergar',       250, ['evil'],                    'Evil Dwarves, more agile than their cousins'],
            ['Gnome',         300, ['neutral'],                 'Tough little creatures. Wisest race, smart'],
            ['Svirfnebli',    250, ['neutral'],                 'Deep gnomes, strong and very wise'],
            ['Wood-Elf',      400, ['neutral'],                 'Neutral elves, sturdier than their cousins'],
            ['Orc',           100, ['evil'],                    'Evil, destructive, strong, but cowardly'],
            ['Minotaur',      450, ['neutral', 'evil'],         'Very rare, strong and cunning, and fairly dumb'],
            ['Azure-Touched', 300, ['good'],                    'Humans with celestial/angelic ties'],
            ['Frost Giant',   500, ['evil'],                    'Evil giants, reclusive, heartless and strong'],
            ['Goblin',        100, ['neutral', 'evil'],         'Small craft cousins of orcs, raiders by trade'],
            ['Saurian',       300, ['good', 'evil'],            'Wise lizard-folk with bonds to life or death'],
            ['Centaur',       500, ['good', 'neutral'],         'Wilderness-loving and nomadic creatures'],
        ];

        foreach ($races as [$name, $cost, $alignments, $desc]) {
            Race::updateOrCreate(
                ['name' => $name],
                ['cost' => $cost, 'allowed_alignments' => $alignments, 'description' => $desc],
            );
        }
    }
}
