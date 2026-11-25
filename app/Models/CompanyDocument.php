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
    'company_id',
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
class CompanyDocument extends Model
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

    public const TYPE_ASSURANCE_AT = 'assurance_at';

    public const TYPE_ASSURANCE_RC = 'assurance_rc';

    public const TYPE_LISTE_PERSONNEL = 'liste_personnel';

    public const TYPE_LISTE_MATERIEL = 'liste_materiel';

    public const TYPE_LISTE_OUTILLAGE = 'liste_outillage';

    public const TYPE_CONTRACTED_CLINICS = 'cliniques_conventionnees';

    public const TYPE_EMERGENCY_PLAN = 'plan_urgence';

    public const TYPE_COVERAGE = 'prise_en_charge';

    public const TYPE_PREVENTION_PLAN = 'plan_prevention';

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
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

    public function versions(): HasMany
    {
        return $this->hasMany(CompanyDocumentVersion::class)->orderBy('version');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(CompanyDocumentVersion::class, 'company_document_id')
            ->where('is_current', true)
            ->orderByDesc('version');
    }

    public function currentVersionData(): ?CompanyDocumentVersion
    {
        return $this->versions()->where('is_current', true)->first();
    }

    public function reviewHistory(): HasMany
    {
        return $this->hasMany(CompanyReviewHistory::class)->orderByDesc('created_at');
    }

    public static function requiredTypes(): array
    {
        return [
            self::TYPE_ASSURANCE_AT,
            self::TYPE_ASSURANCE_RC,
            self::TYPE_LISTE_PERSONNEL,
            self::TYPE_LISTE_MATERIEL,
            self::TYPE_LISTE_OUTILLAGE,
            self::TYPE_CONTRACTED_CLINICS,
            self::TYPE_EMERGENCY_PLAN,
            self::TYPE_COVERAGE,
            self::TYPE_PREVENTION_PLAN,
        ];
    }

    public static function allTypes(): array
    {
        return self::requiredTypes();
    }

    public static function hseTypes(): array
    {
        return [
            self::TYPE_CONTRACTED_CLINICS,
            self::TYPE_EMERGENCY_PLAN,
            self::TYPE_COVERAGE,
            self::TYPE_PREVENTION_PLAN,
        ];
    }

    public static function labelForType(string $type): string
    {
        return match ($type) {
            self::TYPE_ASSURANCE_AT => 'Assurance AT',
            self::TYPE_ASSURANCE_RC => 'Assurance RC',
            self::TYPE_LISTE_PERSONNEL => 'Liste exhaustive des effectifs sur site (avec numéro de série de cadenas de consignation)',
            self::TYPE_LISTE_MATERIEL => 'Liste matériel',
            self::TYPE_LISTE_OUTILLAGE => 'Liste outillage',
            self::TYPE_CONTRACTED_CLINICS => 'Cliniques conventionnées',
            self::TYPE_EMERGENCY_PLAN => 'Plan d’urgence',
            self::TYPE_COVERAGE => 'Prise en charge',
            self::TYPE_PREVENTION_PLAN => 'Plan de prévention',
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
