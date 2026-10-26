<?php

namespace Database\Factories;

use App\Models\ToothCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ToothCondition> */
class ToothConditionFactory extends Factory
{
    protected $model = ToothCondition::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'label' => fake()->word(),
            'color' => fake()->hexColor(),
            'target' => fake()->randomElement(['face', 'tooth', 'both']),
            'category' => fake()->randomElement(['patologia', 'restauracion', 'anomalia']),
            'allowed_states' => fake()->randomElement(['bueno', 'malo', 'ambos']),
            'abbreviations' => null,
            'glyph' => null,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
