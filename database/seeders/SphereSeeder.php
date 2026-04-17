<?php

namespace Database\Seeders;

use App\Models\Sphere;
use Illuminate\Database\Seeder;

class SphereSeeder extends Seeder
{
    public function run(): void
    {
        $spheres = [
            'Earth', 'Air', 'Fire', 'Water',
            'Spirit', 'Para-elements', 'Storms', 'Seasons',
            'Death', 'Fertility', 'Magic', 'Reason',
            'Truth', 'Vivimancy', 'Revelation', 'Music',
            'Healing', 'Protection', 'Creation', 'Mercy',
            'Strength', 'Poetry', 'Deception', 'Necromancy',
            'Concealment', 'Courage', 'Victory', 'Destruction',
            'Rage', 'Combat', 'Order', 'Peace',
            'Chaos', 'War', 'Balance', 'Fate',
            'Honor', 'Dedication', 'Wisdom', 'Knowledge',
            'Purity', 'Greed', 'Justice', 'Judgment',
            'Love', 'Vanity', 'Beauty', 'Passion',
            'Anger', 'Covetousness', 'Envy', 'Gluttony',
            'Lust', 'Pride', 'Sloth', 'Honesty',
        ];

        foreach ($spheres as $name) {
            Sphere::updateOrCreate(['name' => $name]);
        }
    }
}
