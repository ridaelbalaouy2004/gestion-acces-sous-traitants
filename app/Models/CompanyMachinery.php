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
    'assignment',
    'brand',
    'model',
    'serial_number',
    'registration_number',
    'commissioned_at',
    'description',
    'status',
    'rejection_reason',
    'validated_by',
    'validated_at',
    'submitted_at',
])]
class CompanyMachinery extends Model
{
    protected $table = 'company_machinery';

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
            'commissioned_at' => 'date',
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
        return $this->hasMany(CompanyMachineryDocument::class)->orderBy('type');
    }

    public function history(): HasMany
    {
        return $this->hasMany(CompanyMachineryHistory::class)->orderByDesc('created_at');
    }
}
