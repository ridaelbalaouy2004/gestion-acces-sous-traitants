<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'company_employee_id',
    'type',
    'label',
    'status',
    'file_name',
    'file_path',
    'mime_type',
    'expires_at',
    'rejection_reason',
    'validated_by',
    'validated_at',
    'submitted_at',
])]
class CompanyEmployeeDocument extends Model
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

    public const TYPE_CIN = 'cin';

    public const TYPE_CNSS = 'cnss';

    public const TYPE_PHYSICAL_FITNESS = 'physical_fitness';

    public const TYPE_PERMIT = 'permit';

    public const TYPE_ANTHROPOMETRIC_SHEET = 'fiche_anthropometrique';

    public const TYPE_NOMINATIVE_AT = 'at_nominative';

    public const TYPE_RULES_COMMITMENT = 'engagement_reglement';

    public const TYPE_HABILITATIONS = 'habilitations';

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'validated_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(CompanyEmployee::class, 'company_employee_id');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CompanyEmployeeDocumentVersion::class)->orderBy('version');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(CompanyEmployeeDocumentVersion::class)
            ->where('is_current', true)
            ->orderByDesc('version');
    }

    public static function requiredTypes(): array
    {
        return [
            self::TYPE_CIN,
            self::TYPE_CNSS,
            self::TYPE_PHYSICAL_FITNESS,
            self::TYPE_ANTHROPOMETRIC_SHEET,
            self::TYPE_RULES_COMMITMENT,
        ];
    }

    public static function optionalTypes(): array
    {
        return [
            self::TYPE_PERMIT,
            self::TYPE_NOMINATIVE_AT,
            self::TYPE_HABILITATIONS,
        ];
    }

    public static function allTypes(): array
    {
        return [...self::requiredTypes(), ...self::optionalTypes()];
    }

    public static function labelForType(string $type): string
    {
        return match ($type) {
            self::TYPE_CIN => 'CIN',
            self::TYPE_CNSS => 'CNSS',
            self::TYPE_PHYSICAL_FITNESS => 'Aptitude physique',
            self::TYPE_PERMIT => 'Permis',
            self::TYPE_ANTHROPOMETRIC_SHEET => 'Fiche anthropométrique',
            self::TYPE_NOMINATIVE_AT => 'AT nominative',
            self::TYPE_RULES_COMMITMENT => 'Engagement au règlement',
            self::TYPE_HABILITATIONS => 'Habilitations',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public function displayLabel(): string
    {
        return in_array($this->type, self::allTypes(), true)
            ? self::labelForType($this->type)
            : ($this->label ?: self::labelForType($this->type));
    }
}
