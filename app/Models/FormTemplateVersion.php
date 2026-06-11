<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormTemplateVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'user_id',
        'name',
        'json_schema',
        'ui_schema',
        'is_active',
        'version_number',
    ];

    protected function casts(): array
    {
        return [
            'json_schema' => 'array',
            'ui_schema' => 'array',
            'is_active' => 'boolean',
            'version_number' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
