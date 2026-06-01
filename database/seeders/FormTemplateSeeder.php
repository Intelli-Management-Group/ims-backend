<?php

namespace Database\Seeders;

use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class FormTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $createdBy = User::query()->first();

        $templates = [
            [
                'name' => 'Contact Request',
                'json_schema' => [
                    'type' => 'object',
                    'required' => ['firstName', 'lastName'],
                    'properties' => [
                        'firstName' => [
                            'type' => 'string',
                            'title' => 'First name',
                            'default' => 'Chuck',
                        ],
                        'lastName' => [
                            'type' => 'string',
                            'title' => 'Last name',
                        ],
                        'age' => [
                            'type' => 'integer',
                            'title' => 'Age',
                        ],
                        'bio' => [
                            'type' => 'string',
                            'title' => 'Bio',
                        ],
                        'password' => [
                            'type' => 'string',
                            'title' => 'Password',
                            'minLength' => 3,
                        ],
                        'telephone' => [
                            'type' => 'string',
                            'title' => 'Telephone',
                            'minLength' => 10,
                        ],
                    ],
                ],
                'ui_schema' => [
                    'firstName' => [
                        'ui:autofocus' => true,
                        'ui:emptyValue' => '',
                        'ui:placeholder' => 'ui:emptyValue causes this field to always be valid despite being required',
                        'ui:autocomplete' => 'family-name',
                    ],
                    'lastName' => [
                        'ui:autocomplete' => 'given-name',
                    ],
                    'age' => [
                        'ui:widget' => 'updown',
                        'ui:title' => 'Age of person',
                    ],
                    'bio' => [
                        'ui:widget' => 'textarea',
                    ],
                    'password' => [
                        'ui:widget' => 'password',
                    ],
                    'telephone' => [
                        'ui:options' => [
                            'inputType' => 'tel',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Incident Report',
                'json_schema' => [
                    'type' => 'object',
                    'required' => ['title', 'description', 'severity'],
                    'properties' => [
                        'title' => ['type' => 'string', 'title' => 'Incident Title'],
                        'description' => ['type' => 'string', 'title' => 'Description'],
                        'severity' => [
                            'type' => 'string',
                            'enum' => ['low', 'medium', 'high', 'critical'],
                            'title' => 'Severity',
                        ],
                        'reporter_name' => ['type' => 'string', 'title' => 'Reporter Name'],
                        'occurred_at' => ['type' => 'string', 'format' => 'date-time', 'title' => 'Occurred At'],
                    ],
                ],
                'ui_schema' => [
                    'description' => ['ui:widget' => 'textarea'],
                ],
            ],
            [
                'name' => 'Feedback Survey',
                'json_schema' => [
                    'type' => 'object',
                    'required' => ['rating', 'feedback_text'],
                    'properties' => [
                        'rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'title' => 'Rating'],
                        'feedback_text' => ['type' => 'string', 'title' => 'Feedback'],
                        'would_recommend' => ['type' => 'boolean', 'title' => 'Would recommend'],
                    ],
                ],
                'ui_schema' => [
                    'feedback_text' => ['ui:widget' => 'textarea'],
                ],
            ],
        ];

        foreach ($templates as $template) {
            FormTemplate::query()->updateOrCreate(
                ['name' => $template['name']],
                [
                    'json_schema' => $template['json_schema'],
                    'ui_schema' => $template['ui_schema'],
                    'is_active' => true,
                    'created_by' => $createdBy?->id,
                ]
            );
        }
    }
}
