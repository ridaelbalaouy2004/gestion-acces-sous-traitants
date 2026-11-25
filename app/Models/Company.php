<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'prestataire_id',
    'name',
    'activity',
    'assignment',
    'contract_start_date',
    'contract_end_date',
    'additional_info',
    'status',
    'rejection_reason',
    'validated_by',
    'validated_at',
    'submitted_at',
])]
class Company extends Model
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
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
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

    public function documents(): HasMany
    {
        return $this->hasMany(CompanyDocument::class)->orderBy('type');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(CompanyEmployee::class);
    }

    public function machinery(): HasMany
    {
        return $this->hasMany(CompanyMachinery::class);
    }

    public function toolings(): HasMany
    {
        return $this->hasMany(CompanyTooling::class);
    }

    public function reviewHistory(): HasMany
    {
        return $this->hasMany(CompanyReviewHistory::class)->orderByDesc('created_at');
    }

    public function isReadyForSubmission(): bool
    {
        if (blank($this->name) || blank($this->activity) || blank($this->assignment)) {
            return false;
        }

        $documentsByType = $this->documents->keyBy('type');

        foreach (CompanyDocument::requiredTypes() as $type) {
            $document = $documentsByType->get($type);

            if (! $document || blank($document->file_path) || $document->status === CompanyDocument::STATUS_REJECTED) {
                return false;
            }
        }

        if ($this->documents->contains('status', CompanyDocument::STATUS_REJECTED)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, array{label: string, reason: string}>
     */
    public function approvalBlockers(): array
    {
        $documentsByType = $this->documents->keyBy('type');
        $requiredTypes = CompanyDocument::requiredTypes();
        $blockers = [];

        foreach ($requiredTypes as $type) {
            $document = $documentsByType->get($type);

            if ($document === null) {
                $blockers[] = [
                    'label' => CompanyDocument::labelForType($type),
                    'reason' => 'document obligatoire manquant',
                ];

                continue;
            }

            $reasons = $this->documentApprovalReasons($document);

            if ($reasons !== []) {
                $blockers[] = [
                    'label' => CompanyDocument::labelForType($type),
                    'reason' => implode(', ', $reasons),
                ];
            }
        }

        foreach ($this->documents as $document) {
            if (
                in_array($document->type, $requiredTypes, true)
                || blank($document->file_path)
            ) {
                continue;
            }

            $reasons = $this->documentApprovalReasons($document);

            if ($reasons !== []) {
                $blockers[] = [
                    'label' => $document->label ?: CompanyDocument::labelForType($document->type),
                    'reason' => implode(', ', $reasons),
                ];
            }
        }

        return $blockers;
    }

    /**
     * @return array<int, string>
     */
    private function documentApprovalReasons(CompanyDocument $document): array
    {
        $reasons = [];

        if (blank($document->file_path)) {
            $reasons[] = 'fichier manquant';
        }

        $statusProblem = match ($document->status) {
            CompanyDocument::STATUS_VALIDATED => null,
            CompanyDocument::STATUS_PENDING => 'en attente de validation',
            CompanyDocument::STATUS_REJECTED => 'rejeté',
            CompanyDocument::STATUS_DRAFT => 'non soumis',
            default => 'statut invalide',
        };

        if ($statusProblem !== null) {
            $reasons[] = $statusProblem;
        }

        if (
            $document->type !== CompanyDocument::TYPE_LISTE_PERSONNEL
            && $document->expires_at !== null
            && $document->expires_at->lt(today())
        ) {
            $reasons[] = 'date d’expiration dépassée';
        }

        return $reasons;
    }
}
