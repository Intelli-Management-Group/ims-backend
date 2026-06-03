<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'json_schema',
        'ui_schema',
        'is_active',
        'created_by',
        'current_version_id',
    ];

    protected function casts(): array
    {
        return [
            'json_schema' => 'array',
            'ui_schema' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(FormTemplateVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FormTemplateVersion::class, 'template_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }
}
