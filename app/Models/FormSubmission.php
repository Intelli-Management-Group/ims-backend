<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_template_id',
        'form_template_version_id',
        'current_version_id',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }

    /** The specific template version that was active when this submission was created. */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(FormTemplateVersion::class, 'form_template_version_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(FormSubmissionVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FormSubmissionVersion::class, 'submission_id');
    }
}
