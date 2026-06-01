<?php

namespace Database\Seeders;

use App\Models\FormSubmission;
use App\Models\FormSubmissionVersion;
use App\Models\User;
use Illuminate\Database\Seeder;

class FormSubmissionVersionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->first();
        $submissions = FormSubmission::query()
            ->with('template')
            ->whereDoesntHave('versions')
            ->get();

        if ($submissions->isEmpty() || $user === null) {
            return;
        }

        foreach ($submissions as $submission) {
            $template = $submission->template;
            $schema = $template->schema;
            $properties = $schema['properties'] ?? [];

            $content = $this->contentFromSchema($properties);

            $version = FormSubmissionVersion::query()->create([
                'submission_id' => $submission->id,
                'user_id' => $user->id,
                'form_name' => $template->name,
                'content' => $content,
                'version_number' => 1,
            ]);

            $submission->update(['current_version_id' => $version->id]);
        }
    }

    /**
     * Build content object that conforms to the JSON Schema properties.
     *
     * @param  array<string, array<string, mixed>>  $properties
     * @return array<string, mixed>
     */
    private function contentFromSchema(array $properties): array
    {
        $content = [];

        foreach ($properties as $key => $config) {
            $type = $config['type'] ?? 'string';

            $content[$key] = match ($type) {
                'string' => $this->fakeString($config),
                'integer' => $this->fakeInteger($config),
                'number' => $this->fakeNumber($config),
                'boolean' => fake()->boolean(),
                'array' => $this->fakeArray($config),
                'object' => $this->fakeObject($config),
                default => fake()->word(),
            };
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function fakeString(array $config): string
    {
        if (isset($config['enum'])) {
            return fake()->randomElement($config['enum']);
        }

        return match ($config['format'] ?? null) {
            'email' => fake()->safeEmail(),
            'date-time' => now()->subDays(rand(1, 30))->toIso8601String(),
            'date' => fake()->date(),
            default => fake()->sentence(),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function fakeInteger(array $config): int
    {
        $min = $config['minimum'] ?? 0;
        $max = $config['maximum'] ?? 100;

        return fake()->numberBetween($min, $max);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function fakeNumber(array $config): float
    {
        $min = $config['minimum'] ?? 0;
        $max = $config['maximum'] ?? 100;

        return fake()->randomFloat(2, $min, $max);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, mixed>
     */
    private function fakeArray(array $config): array
    {
        $items = $config['items'] ?? ['type' => 'string'];
        $count = fake()->numberBetween(0, 3);

        $arr = [];
        for ($i = 0; $i < $count; $i++) {
            $arr[] = ($items['type'] ?? 'string') === 'string'
                ? fake()->word()
                : fake()->word();
        }

        return $arr;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function fakeObject(array $config): array
    {
        $props = $config['properties'] ?? [];

        return $this->contentFromSchema($props);
    }
}
