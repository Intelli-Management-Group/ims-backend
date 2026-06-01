<?php

namespace App\Http\Resources\Form;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormSubmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // 'form_template_id' => $this->form_template_id,
            // 'current_version_id' => $this->current_version_id,
            'template' => new FormTemplateResource($this->whenLoaded('template')),
            'current_version' => new FormSubmissionVersionResource($this->whenLoaded('currentVersion')),
            'submitted_by' => $this->whenLoaded('currentVersion', fn () => $this->currentVersion->user?->name),
            // 'versions' => FormSubmissionVersionResource::collection($this->whenLoaded('versions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
