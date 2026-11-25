<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'prestataire_id',
    'title',
    'reference_number',
    'status',
    'company_name',
    'description',
    'start_date',
    'end_date',
    'attachment_path',
    'custom_fields',
    'rejection_comment',
    'validated_by',
    'validated_at',
    'submitted_at',
])]
class Document extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'validated_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prestataire_id');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function safetyTeamMembers(): HasMany
    {
        return $this->hasMany(SafetyTeamMember::class);
    }
}
