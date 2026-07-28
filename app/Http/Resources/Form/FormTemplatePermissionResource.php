<?php

namespace App\Http\Resources\Form;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormTemplatePermissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'form_template_id' => $this->form_template_id,
            'action' => $this->action->value,
            'permissible_type' => $this->permissible_type, // morph alias: role|department|team
            'permissible_id' => $this->permissible_id,
            'created_at' => $this->created_at,
        ];
    }
}
