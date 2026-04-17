<?php

namespace Database\Seeders;

use App\Models\CharacterClass;
use Illuminate\Database\Seeder;

class CharacterClassSeeder extends Seeder
{
    public function run(): void
    {
        $classes = [
            ['Warrior',      ['good', 'neutral', 'evil'], 'Weapons specialist'],
            ['Thief',        ['good', 'neutral', 'evil'], 'Stealthy rogues'],
            ['Shaman',       ['good', 'evil'],            'Offensive clerics'],
            ['Healer',       ['good', 'neutral', 'evil'], 'Defensive clerics'],
            ['Druid',        ['neutral'],                 'Nature cleric'],
            ['Transmuter',   ['good', 'neutral', 'evil'], 'Alteration mage'],
            ['Shapeshifter', ['good', 'neutral', 'evil'], 'Shapeshifting mage'],
            ['Necromancer',  ['evil'],                    'Undead-master mage'],
            ['Invoker',      ['good', 'neutral', 'evil'], 'Elemental mage'],
            ['Conjurer',     ['good', 'neutral', 'evil'], 'Extraplanar mage'],
            ['Ranger',       ['good', 'neutral', 'evil'], 'Wilderness warrior'],
            ['Bard',         ['good', 'neutral', 'evil'], 'Musician'],
            ['Assassin',     ['good', 'neutral', 'evil'], 'Thief/martial artist'],
            ['Anti-Paladin', ['evil'],                    'Unholy warrior/mage'],
            ['Paladin',      ['good'],                    'Holy warrior/cleric'],
            ['Berserker',    ['evil'],                    'The innate class played by all Orcs'],
            ['Raider',       ['neutral', 'evil'],         'Hybrid berserker-thief class played by all Goblins'],
        ];

        foreach ($classes as [$name, $alignments, $desc]) {
            CharacterClass::updateOrCreate(
                ['name' => $name],
                ['allowed_alignments' => $alignments, 'description' => $desc],
            );
        }
    }
}
