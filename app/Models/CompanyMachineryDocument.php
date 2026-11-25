<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_machinery_id',
    'type',
    'label',
    'status',
    'file_name',
    'file_path',
    'mime_type',
    'issued_at',
    'expires_at',
    'rejection_reason',
    'validated_by',
    'validated_at',
    'submitted_at',
])]
class CompanyMachineryDocument extends Model
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

    public const TYPE_REGISTRATION_CARD = 'registration_card';

    public const TYPE_INSURANCE = 'insurance';

    public const TYPE_REGULATORY_CERTIFICATE = 'regulatory_certificate';

    public const TYPE_TECHNICAL_INSPECTION = 'technical_inspection';

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'validated_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function machinery(): BelongsTo
    {
        return $this->belongsTo(CompanyMachinery::class, 'company_machinery_id');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CompanyMachineryDocumentVersion::class)->orderBy('version');
    }

    public static function allTypes(): array
    {
        return [
            self::TYPE_REGISTRATION_CARD,
            self::TYPE_INSURANCE,
            self::TYPE_REGULATORY_CERTIFICATE,
            self::TYPE_TECHNICAL_INSPECTION,
        ];
    }

    public static function requiredTypes(): array
    {
        return [
            self::TYPE_INSURANCE,
            self::TYPE_REGISTRATION_CARD,
            self::TYPE_TECHNICAL_INSPECTION,
            self::TYPE_REGULATORY_CERTIFICATE,
        ];
    }

    public static function labelForType(string $type): string
    {
        return match ($type) {
            self::TYPE_REGISTRATION_CARD => 'Carte grise',
            self::TYPE_INSURANCE => 'Assurance',
            self::TYPE_REGULATORY_CERTIFICATE => 'Certificat réglementaire',
            self::TYPE_TECHNICAL_INSPECTION => 'Visite technique',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
