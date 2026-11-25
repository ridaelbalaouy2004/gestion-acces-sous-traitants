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
    'first_name',
    'last_name',
    'profile_photo_path',
    'cin',
    'position',
    'locker_number',
    'status',
    'rejection_reason',
    'validated_by',
    'validated_at',
    'submitted_at',
])]
class CompanyEmployee extends Model
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
        return $this->hasMany(CompanyEmployeeDocument::class)->orderBy('type');
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(CompanyEmployeeTraining::class)->orderBy('training_date')->orderBy('id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(CompanyEmployeeHistory::class)->orderByDesc('created_at');
    }

    public function isReadyForSubmission(): bool
    {
        if (blank($this->first_name) || blank($this->last_name) || blank($this->cin) || blank($this->position)) {
            return false;
        }

        $documentsByType = $this->documents->keyBy('type');

        foreach (CompanyEmployeeDocument::requiredTypes() as $type) {
            $document = $documentsByType->get($type);

            if (
                $document === null
                || blank($document->file_path)
                || $document->status === CompanyEmployeeDocument::STATUS_REJECTED
            ) {
                return false;
            }
        }

        if ($this->documents->contains('status', CompanyEmployeeDocument::STATUS_REJECTED)) {
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
        $requiredTypes = CompanyEmployeeDocument::requiredTypes();
        $blockers = [];

        foreach ($requiredTypes as $type) {
            $document = $documentsByType->get($type);

            if ($document === null) {
                $blockers[] = [
                    'label' => CompanyEmployeeDocument::labelForType($type),
                    'reason' => 'document obligatoire manquant',
                ];

                continue;
            }

            $reasons = $this->documentApprovalReasons($document);

            if ($reasons !== []) {
                $blockers[] = [
                    'label' => CompanyEmployeeDocument::labelForType($type),
                    'reason' => implode(', ', $reasons),
                ];
            }
        }

        foreach ($this->documents as $document) {
            if (in_array($document->type, $requiredTypes, true) || blank($document->file_path)) {
                continue;
            }

            $reasons = $this->documentApprovalReasons($document);

            if ($reasons !== []) {
                $blockers[] = [
                    'label' => $document->label ?: CompanyEmployeeDocument::labelForType($document->type),
                    'reason' => implode(', ', $reasons),
                ];
            }
        }

        return $blockers;
    }

    /**
     * @return array<int, string>
     */
    private function documentApprovalReasons(CompanyEmployeeDocument $document): array
    {
        $reasons = [];

        if (blank($document->file_path)) {
            $reasons[] = 'fichier manquant';
        }

        $statusProblem = match ($document->status) {
            CompanyEmployeeDocument::STATUS_VALIDATED => null,
            CompanyEmployeeDocument::STATUS_PENDING => 'en attente de validation',
            CompanyEmployeeDocument::STATUS_REJECTED => 'rejeté',
            CompanyEmployeeDocument::STATUS_DRAFT => 'non soumis',
            default => 'statut invalide',
        };

        if ($statusProblem !== null) {
            $reasons[] = $statusProblem;
        }

        if ($document->expires_at !== null && $document->expires_at->lt(today())) {
            $reasons[] = 'date d’expiration dépassée';
        }

        return $reasons;
    }
}
