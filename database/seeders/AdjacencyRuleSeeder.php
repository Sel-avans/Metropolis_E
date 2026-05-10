<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdjacencyRule;
use App\Models\CityFunction;

class AdjacencyRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            ['a' => 'Park', 'b' => 'Sportpark', 'type' => 'bonus', 'value' => 2],

            ['a' => 'School', 'b' => 'Weg', 'type' => 'penalty', 'value' => -2],

            ['a' => 'Ziekenhuis', 'b' => 'Tankstation', 'type' => 'forbidden', 'value' => 0],
        ];

        foreach ($rules as $r) {
            $funcA = CityFunction::where('name', $r['a'])->first();
            $funcB = CityFunction::where('name', $r['b'])->first();

            if (!$funcA || !$funcB) {
                dump("Skipping rule: {$r['a']} - {$r['b']} (function not found)");
                continue;
            }

            AdjacencyRule::create([
                'function_a' => $funcA->id,
                'function_b' => $funcB->id,
                'type'       => $r['type'],
                'value'      => $r['value'],
            ]);
        }
    }
}
