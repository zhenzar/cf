<?php

namespace Database\Seeders;

use App\Models\CharacterClass;
use Illuminate\Database\Seeder;

class CharacterClassSeeder extends Seeder
{
    public function run(): void
    {
        $classes = [
            ['Warrior',      ['good', 'neutral', 'evil'], 'Weapons specialist',             null],
            ['Thief',        ['good', 'neutral', 'evil'], 'Stealthy rogues',                null],
            ['Shaman',       ['good', 'evil'],            'Offensive clerics',              null],
            ['Healer',       ['good', 'neutral', 'evil'], 'Defensive clerics',              null],
            ['Druid',        ['neutral'],                 'Nature cleric',                  null],
            ['Transmuter',   ['good', 'neutral', 'evil'], 'Alteration mage',                null],
            ['Shapeshifter', ['good', 'neutral', 'evil'], 'Shapeshifting mage',             null],
            ['Necromancer',  ['evil'],                    'Undead-master mage',             null],
            ['Invoker',      ['good', 'neutral', 'evil'], 'Elemental mage',                 null],
            ['Conjurer',     ['good', 'neutral', 'evil'], 'Extraplanar mage',               null],
            ['Ranger',       ['good', 'neutral', 'evil'], 'Wilderness warrior',             null],
            ['Bard',         ['good', 'neutral', 'evil'], 'Musician',                       null],
            ['Assassin',     ['good', 'neutral', 'evil'], 'Thief/martial artist',           null],
            ['Anti-Paladin', ['evil'],                    'Unholy warrior/mage',            null],
            ['Paladin',      ['good'],                    'Holy warrior/cleric',            null],
            ['Berserker',    ['evil'],                    'The innate class played by all Orcs',             'Orc'],
            ['Raider',       ['neutral', 'evil'],         'Hybrid berserker-thief class played by all Goblins', 'Goblin'],
        ];

        foreach ($classes as [$name, $alignments, $desc, $exclusive]) {
            CharacterClass::updateOrCreate(
                ['name' => $name],
                [
                    'allowed_alignments' => $alignments,
                    'description' => $desc,
                    'exclusive_race_name' => $exclusive,
                ],
            );
        }
    }
}
