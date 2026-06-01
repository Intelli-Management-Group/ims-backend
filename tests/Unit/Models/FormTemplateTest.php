<?php

namespace Tests\Unit\Models;

use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_creator_relationship(): void
    {
        $user = User::factory()->create();
        $template = FormTemplate::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(User::class, $template->creator);
        $this->assertEquals($user->id, $template->creator->id);
    }

    public function test_it_casts_json_schema_to_array(): void
    {
        $jsonSchema = ['foo' => 'bar'];
        $template = FormTemplate::factory()->create(['json_schema' => $jsonSchema]);

        $this->assertIsArray($template->json_schema);
        $this->assertEquals($jsonSchema, $template->json_schema);
    }

    public function test_it_casts_ui_schema_to_array(): void
    {
        $uiSchema = ['ui:order' => ['field']];
        $template = FormTemplate::factory()->create(['ui_schema' => $uiSchema]);

        $this->assertIsArray($template->ui_schema);
        $this->assertEquals($uiSchema, $template->ui_schema);
    }

    public function test_it_casts_is_active_to_boolean(): void
    {
        $template = FormTemplate::factory()->create(['is_active' => 1]);

        $this->assertIsBool($template->is_active);
        $this->assertTrue($template->is_active);
    }
}
