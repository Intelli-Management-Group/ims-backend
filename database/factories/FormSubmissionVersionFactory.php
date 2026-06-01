<?php

namespace Database\Factories;

use App\Models\FormSubmission;
use App\Models\FormSubmissionVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSubmissionVersion>
 */
class FormSubmissionVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => FormSubmission::factory(),
            'user_id' => User::factory(),
            'form_name' => fake()->words(3, true).' Form',
            'content' => [
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
            ],
            'version_number' => 1,
        ];
    }
}
