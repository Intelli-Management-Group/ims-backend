<?php

namespace App\Models;

use App\Enums\FormPermissionAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FormTemplatePermission extends Model
{
    protected $fillable = [
        'form_template_id',
        'action',
        'permissible_type',
        'permissible_id',
    ];

    protected function casts(): array
    {
        return [
            'action' => FormPermissionAction::class,
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }

    /** The role, department, or team this grant applies to. */
    public function permissible(): MorphTo
    {
        return $this->morphTo();
    }
}
