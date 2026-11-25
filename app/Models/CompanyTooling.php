<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'designation',
    'photo_path',
    'category',
    'internal_reference',
    'brand',
    'model',
    'serial_number',
    'quantity',
    'physical_condition',
    'acquired_at',
    'location',
    'description',
    'observations',
    'status',
    'rejection_reason',
    'validated_by',
    'validated_at',
    'submitted_at',
])]
class CompanyTooling extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_REJECTED = 'rejected';

    #[Scope]
    protected function visibleToManager(Builder $query): void
    {
        $query->where('status', '!=', self::STATUS_DRAFT);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'acquired_at' => 'date',
            'validated_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CompanyToolingDocument::class)->orderBy('label');
    }

    public function history(): HasMany
    {
        return $this->hasMany(CompanyToolingHistory::class)->orderByDesc('created_at');
    }
}
