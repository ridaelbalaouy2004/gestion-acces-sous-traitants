<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_employee_id',
    'title',
    'provider',
    'training_date',
    'expires_at',
    'description',
    'file_name',
    'file_path',
    'mime_type',
])]
class CompanyEmployeeTraining extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'training_date' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(CompanyEmployee::class, 'company_employee_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CompanyEmployeeTrainingVersion::class)->orderBy('version');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(CompanyEmployeeTrainingVersion::class)
            ->where('is_current', true)
            ->orderByDesc('version');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(today());
    }
}
