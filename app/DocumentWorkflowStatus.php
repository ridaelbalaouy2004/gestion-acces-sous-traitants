<?php

namespace App;

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\CompanyMachinery;
use App\Models\CompanyTooling;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class DocumentWorkflowStatus
{
    public static function lockDocuments(
        Company|CompanyEmployee|CompanyMachinery|CompanyTooling $dossier,
    ): Collection {
        $documents = $dossier->documents()->lockForUpdate()->get();
        $dossier->setRelation('documents', $documents);

        return $documents;
    }

    public static function hasRejectedDocument(
        Company|CompanyEmployee|CompanyMachinery|CompanyTooling $dossier,
    ): bool {
        return self::lockDocuments($dossier)->contains(
            fn (Model $document): bool => $document->status === 'rejected',
        );
    }

    public static function statusBeforeDocumentRejection(
        Company|CompanyEmployee|CompanyMachinery|CompanyTooling $dossier,
    ): string {
        if ($dossier->status !== 'rejected' || filled($dossier->rejection_reason)) {
            return $dossier->status;
        }

        $history = $dossier instanceof Company
            ? $dossier->reviewHistory()
            : $dossier->history();
        $record = $history
            ->where('action', 'document_rejected')
            ->latest('id')
            ->get(['metadata'])
            ->first(fn (Model $entry): bool => is_array($entry->metadata)
                && array_key_exists('dossier_status_before_document_rejection', $entry->metadata));
        $status = $record?->metadata['dossier_status_before_document_rejection'] ?? null;

        return is_string($status) && in_array($status, ['draft', 'pending', 'validated'], true)
            ? $status
            : $dossier->status;
    }

    public static function synchronize(
        Company|CompanyEmployee|CompanyMachinery|CompanyTooling $dossier,
    ): string {
        $documents = self::lockDocuments($dossier);
        $hasRejectedDocument = $documents->contains(
            fn (Model $document): bool => $document->status === 'rejected',
        );

        if ($hasRejectedDocument) {
            $status = 'rejected';
        } elseif ($dossier->status === 'rejected' && blank($dossier->rejection_reason)) {
            $hasUnresolvedDocuments = self::hasUnresolvedDocuments($dossier, $documents);
            $statusBeforeRejection = self::statusBeforeDocumentRejection($dossier);

            $status = match ($statusBeforeRejection) {
                'draft' => 'draft',
                'validated' => $hasUnresolvedDocuments ? 'pending' : 'validated',
                default => 'pending',
            };
        } elseif ($dossier->status === 'pending') {
            $hasUnresolvedDocuments = self::hasUnresolvedDocuments($dossier, $documents);
            $hasPreviousApproval = $dossier->validated_by !== null && $dossier->validated_at !== null;

            if (! $hasPreviousApproval || $hasUnresolvedDocuments) {
                return $dossier->status;
            }

            $status = 'validated';
        } else {
            return $dossier->status;
        }

        if ($dossier->status !== $status) {
            $dossier->update(['status' => $status]);
        }

        return $status;
    }

    /**
     * @param  Collection<int, Model>  $documents
     */
    private static function hasUnresolvedDocuments(
        Company|CompanyEmployee|CompanyMachinery|CompanyTooling $dossier,
        Collection $documents,
    ): bool {
        if ($dossier instanceof Company || $dossier instanceof CompanyEmployee) {
            return $dossier->approvalBlockers() !== [];
        }

        return $documents->contains(
            fn (Model $document): bool => filled($document->file_path)
                && $document->status !== 'validated',
        );
    }
}
