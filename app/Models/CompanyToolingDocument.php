<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_tooling_id',
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
class CompanyToolingDocument extends Model
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

    public const TYPE_TECHNICAL_SHEET = 'technical_sheet';

    public const TYPE_ACQUISITION_PROOF = 'acquisition_proof';

    public const TYPE_CONFORMITY_CERTIFICATE = 'conformity_certificate';

    public const TYPE_INSPECTION_CERTIFICATE = 'inspection_certificate';

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'validated_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function tooling(): BelongsTo
    {
        return $this->belongsTo(CompanyTooling::class, 'company_tooling_id');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CompanyToolingDocumentVersion::class)->orderBy('version');
    }

    /**
     * @return array<int, string>
     */
    public static function standardTypes(): array
    {
        return [
            self::TYPE_TECHNICAL_SHEET,
            self::TYPE_ACQUISITION_PROOF,
            self::TYPE_CONFORMITY_CERTIFICATE,
            self::TYPE_INSPECTION_CERTIFICATE,
        ];
    }

    public static function requiredTypes(): array
    {
        return [
            self::TYPE_INSPECTION_CERTIFICATE,
            self::TYPE_CONFORMITY_CERTIFICATE,
        ];
    }

    public static function labelForType(string $type): string
    {
        return match ($type) {
            self::TYPE_TECHNICAL_SHEET => 'Fiche technique',
            self::TYPE_ACQUISITION_PROOF => 'Facture ou justificatif d’acquisition',
            self::TYPE_CONFORMITY_CERTIFICATE => 'Certificat de conformité',
            self::TYPE_INSPECTION_CERTIFICATE => 'Certificat de contrôle',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
