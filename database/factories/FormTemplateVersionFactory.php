<?php

namespace Database\Factories;

use App\Models\FormTemplate;
use App\Models\FormTemplateVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormTemplateVersion>
 */
class FormTemplateVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_id' => FormTemplate::factory(),
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true).' Form',
            'json_schema' => [
                'type' => 'object',
                'properties' => [
                    'first_name' => ['type' => 'string'],
                    'last_name' => ['type' => 'string'],
                ],
            ],
            'ui_schema' => [],
            'is_active' => true,
            'version_number' => 1,
        ];
    }
}
