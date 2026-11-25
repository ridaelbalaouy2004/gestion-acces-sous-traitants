<?php

namespace App\Models;

use App\Notifications\CompanyEmployeeWorkflowNotification;
use App\Notifications\CompanyMachineryWorkflowNotification;
use App\Notifications\CompanyToolingWorkflowNotification;
use App\Notifications\DocumentWorkflowNotification;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_active', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'is_active' => true,
        'must_change_password' => false,
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function assignedPrestataires(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'manager_prestataire_assignments',
            'manager_id',
            'prestataire_id',
        )->withPivot('assigned_by')->withTimestamps();
    }

    public function assignedManagers(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'manager_prestataire_assignments',
            'prestataire_id',
            'manager_id',
        )->withPivot('assigned_by')->withTimestamps();
    }

    public function isPrimaryManager(): bool
    {
        $principalId = ManagerSettings::query()
            ->whereKey(ManagerSettings::SINGLETON_ID)
            ->value('principal_user_id');

        return $principalId !== null && (int) $principalId === (int) $this->getKey();
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->roles()->where('name', 'gestionnaire')->exists()) {
            return false;
        }

        return $this->isPrimaryManager()
            || $this->permissions()->where('name', $permission)->exists();
    }

    public function visibleNotifications(): MorphMany
    {
        $query = $this->notifications();

        if ($this->roles()->where('name', 'responsable_securite')->exists()) {
            return $query
                ->where('type', DocumentWorkflowNotification::class)
                ->where('data->event', DocumentWorkflowNotification::EVENT_VALIDATED)
                ->whereIn('data->document_id', Document::query()
                    ->where('status', Document::STATUS_VALIDATED)
                    ->select('documents.id'));
        }

        if ($this->roles()->where('name', 'prestataire')->exists()) {
            $employeeIds = CompanyEmployee::query()
                ->whereHas('company', fn (Builder $companyQuery) => $companyQuery
                    ->where('prestataire_id', $this->getKey()))
                ->select('company_employees.id');
            $machineryIds = CompanyMachinery::query()
                ->whereHas('company', fn (Builder $companyQuery) => $companyQuery
                    ->where('prestataire_id', $this->getKey()))
                ->select('company_machinery.id');
            $toolingIds = CompanyTooling::query()
                ->whereHas('company', fn (Builder $companyQuery) => $companyQuery
                    ->where('prestataire_id', $this->getKey()))
                ->select('company_toolings.id');

            return $query
                ->where(function (Builder $visibleQuery) use ($employeeIds, $machineryIds, $toolingIds): void {
                    $visibleQuery
                        ->where(function (Builder $employeeQuery) use ($employeeIds): void {
                            $employeeQuery
                                ->where('type', CompanyEmployeeWorkflowNotification::class)
                                ->whereIn('data->employee_id', $employeeIds);
                        })
                        ->orWhere(function (Builder $machineryQuery) use ($machineryIds): void {
                            $machineryQuery
                                ->where('type', CompanyMachineryWorkflowNotification::class)
                                ->whereIn('data->company_machinery_id', $machineryIds);
                        })
                        ->orWhere(function (Builder $toolingQuery) use ($toolingIds): void {
                            $toolingQuery
                                ->where('type', CompanyToolingWorkflowNotification::class)
                                ->whereIn('data->company_tooling_id', $toolingIds);
                        });
                });
        }

        if (! $this->roles()->where('name', 'gestionnaire')->exists()) {
            return $query->whereRaw('1 = 0');
        }

        $accessibleEmployeeIds = CompanyEmployee::query()
            ->visibleToManager()
            ->whereHas('company', fn (Builder $companyQuery) => $companyQuery
                ->whereIn('prestataire_id', $this->accessiblePrestataires()->select('users.id')))
            ->select('company_employees.id');
        $accessibleMachineryIds = CompanyMachinery::query()
            ->visibleToManager()
            ->whereHas('company', fn (Builder $companyQuery) => $companyQuery
                ->whereIn('prestataire_id', $this->accessiblePrestataires()->select('users.id')))
            ->select('company_machinery.id');
        $accessibleToolingIds = CompanyTooling::query()
            ->visibleToManager()
            ->whereHas('company', fn (Builder $companyQuery) => $companyQuery
                ->whereIn('prestataire_id', $this->accessiblePrestataires()->select('users.id')))
            ->select('company_toolings.id');
        $accessibleEmployeeDocumentIds = CompanyEmployeeDocument::query()
            ->visibleToManager()
            ->whereHas('employee', fn (Builder $employeeQuery) => $employeeQuery
                ->whereIn('company_employees.id', $accessibleEmployeeIds))
            ->select('company_employee_documents.id');
        $accessibleMachineryDocumentIds = CompanyMachineryDocument::query()
            ->visibleToManager()
            ->whereHas('machinery', fn (Builder $machineryQuery) => $machineryQuery
                ->whereIn('company_machinery.id', $accessibleMachineryIds))
            ->select('company_machinery_documents.id');
        $accessibleToolingDocumentIds = CompanyToolingDocument::query()
            ->visibleToManager()
            ->whereHas('tooling', fn (Builder $toolingQuery) => $toolingQuery
                ->whereIn('company_toolings.id', $accessibleToolingIds))
            ->select('company_tooling_documents.id');
        $employeeDocumentEvents = [
            CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
            CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_REPLACED,
            CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
            CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_REJECTED,
        ];
        $machineryDocumentEvents = [
            CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
            CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_REPLACED,
            CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
            CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_REJECTED,
        ];
        $toolingDocumentEvents = [
            CompanyToolingWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
            CompanyToolingWorkflowNotification::EVENT_DOCUMENT_REPLACED,
            CompanyToolingWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
            CompanyToolingWorkflowNotification::EVENT_DOCUMENT_REJECTED,
        ];
        $employeeEvents = $this->hasPermission('employees.view')
            ? [
                CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_SUBMITTED,
                CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_RESUBMITTED,
                CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_VALIDATED,
                CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_REJECTED,
                CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
                CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_REPLACED,
                CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
                CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_REJECTED,
            ]
            : [];

        if ($employeeEvents === []) {
            if (! $this->hasPermission('machinery.view') && ! $this->hasPermission('toolings.view')) {
                return $query->whereRaw('1 = 0');
            }
        }

        return $query
            ->where(function (Builder $visibleQuery) use (
                $employeeEvents,
                $accessibleEmployeeIds,
                $accessibleEmployeeDocumentIds,
                $employeeDocumentEvents,
                $accessibleMachineryIds,
                $accessibleMachineryDocumentIds,
                $machineryDocumentEvents,
                $accessibleToolingIds,
                $accessibleToolingDocumentIds,
                $toolingDocumentEvents,
            ): void {
                if ($employeeEvents !== []) {
                    $visibleQuery->where(function (Builder $employeeQuery) use (
                        $employeeEvents,
                        $accessibleEmployeeIds,
                        $accessibleEmployeeDocumentIds,
                        $employeeDocumentEvents,
                    ): void {
                        $employeeQuery
                            ->where('type', CompanyEmployeeWorkflowNotification::class)
                            ->whereIn('data->employee_id', $accessibleEmployeeIds)
                            ->whereIn('data->event', $employeeEvents)
                            ->where(function (Builder $documentQuery) use (
                                $accessibleEmployeeDocumentIds,
                                $employeeDocumentEvents,
                            ): void {
                                $documentQuery
                                    ->whereNotIn('data->event', $employeeDocumentEvents)
                                    ->orWhere(function (Builder $submittedDocumentQuery) use (
                                        $accessibleEmployeeDocumentIds,
                                        $employeeDocumentEvents,
                                    ): void {
                                        $submittedDocumentQuery
                                            ->whereIn('data->event', $employeeDocumentEvents)
                                            ->whereIn('data->document_id', $accessibleEmployeeDocumentIds);
                                    });
                            });
                    });
                }

                if ($this->hasPermission('machinery.view')) {
                    $machineryEvents = [
                        CompanyMachineryWorkflowNotification::EVENT_MACHINERY_SUBMITTED,
                        CompanyMachineryWorkflowNotification::EVENT_MACHINERY_RESUBMITTED,
                        CompanyMachineryWorkflowNotification::EVENT_MACHINERY_VALIDATED,
                        CompanyMachineryWorkflowNotification::EVENT_MACHINERY_REJECTED,
                        CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
                        CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_REPLACED,
                        CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
                        CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_REJECTED,
                    ];
                    $machineryQuery = function (Builder $visibleMachineryQuery) use (
                        $accessibleMachineryIds,
                        $machineryDocumentEvents,
                        $accessibleMachineryDocumentIds,
                        $machineryEvents,
                    ): Builder {
                        return $visibleMachineryQuery
                            ->where('type', CompanyMachineryWorkflowNotification::class)
                            ->whereIn('data->company_machinery_id', $accessibleMachineryIds)
                            ->whereIn('data->event', $machineryEvents)
                            ->where(function (Builder $documentQuery) use (
                                $accessibleMachineryDocumentIds,
                                $machineryDocumentEvents,
                            ): void {
                                $documentQuery
                                    ->whereNotIn('data->event', $machineryDocumentEvents)
                                    ->orWhere(function (Builder $submittedDocumentQuery) use (
                                        $accessibleMachineryDocumentIds,
                                        $machineryDocumentEvents,
                                    ): void {
                                        $submittedDocumentQuery
                                            ->whereIn('data->event', $machineryDocumentEvents)
                                            ->whereIn('data->document_id', $accessibleMachineryDocumentIds);
                                    });
                            });
                    };

                    if ($employeeEvents !== []) {
                        $visibleQuery->orWhere($machineryQuery);
                    } else {
                        $machineryQuery($visibleQuery);
                    }
                }

                if ($this->hasPermission('toolings.view')) {
                    $toolingEvents = [
                        CompanyToolingWorkflowNotification::EVENT_TOOLING_SUBMITTED,
                        CompanyToolingWorkflowNotification::EVENT_TOOLING_RESUBMITTED,
                        CompanyToolingWorkflowNotification::EVENT_TOOLING_VALIDATED,
                        CompanyToolingWorkflowNotification::EVENT_TOOLING_REJECTED,
                        CompanyToolingWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
                        CompanyToolingWorkflowNotification::EVENT_DOCUMENT_REPLACED,
                        CompanyToolingWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
                        CompanyToolingWorkflowNotification::EVENT_DOCUMENT_REJECTED,
                    ];
                    $toolingQuery = function (Builder $visibleToolingQuery) use (
                        $accessibleToolingIds,
                        $toolingDocumentEvents,
                        $accessibleToolingDocumentIds,
                        $toolingEvents,
                    ): Builder {
                        return $visibleToolingQuery
                            ->where('type', CompanyToolingWorkflowNotification::class)
                            ->whereIn('data->company_tooling_id', $accessibleToolingIds)
                            ->whereIn('data->event', $toolingEvents)
                            ->where(function (Builder $documentQuery) use (
                                $accessibleToolingDocumentIds,
                                $toolingDocumentEvents,
                            ): void {
                                $documentQuery
                                    ->whereNotIn('data->event', $toolingDocumentEvents)
                                    ->orWhere(function (Builder $submittedDocumentQuery) use (
                                        $accessibleToolingDocumentIds,
                                        $toolingDocumentEvents,
                                    ): void {
                                        $submittedDocumentQuery
                                            ->whereIn('data->event', $toolingDocumentEvents)
                                            ->whereIn('data->document_id', $accessibleToolingDocumentIds);
                                    });
                            });
                    };

                    if ($employeeEvents !== [] || $this->hasPermission('machinery.view')) {
                        $visibleQuery->orWhere($toolingQuery);
                    } else {
                        $toolingQuery($visibleQuery);
                    }
                }
            });
    }

    public function isAccountActive(): bool
    {
        return $this->is_active === null || (bool) $this->is_active;
    }

    public function accessiblePrestataires(): Builder
    {
        $query = self::query()
            ->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', 'prestataire'));

        if (! $this->isPrimaryManager()) {
            $query->whereHas('assignedManagers', fn (Builder $managerQuery) => $managerQuery->whereKey($this->getKey()));
        }

        return $query;
    }

    public static function reviewersForPrestataire(User $prestataire, string $permission): Builder
    {
        $principalId = ManagerSettings::query()
            ->whereKey(ManagerSettings::SINGLETON_ID)
            ->value('principal_user_id');

        return self::query()
            ->where(fn (Builder $query) => $query
                ->whereNull('is_active')
                ->orWhere('is_active', true))
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'gestionnaire'))
            ->where(function (Builder $query) use ($prestataire, $principalId): void {
                if ($principalId !== null) {
                    $query->whereKey($principalId);
                }

                $query->orWhereHas('assignedPrestataires', fn (Builder $assigned) => $assigned->whereKey($prestataire->getKey()));
            })
            ->where(function (Builder $query) use ($permission, $principalId): void {
                if ($principalId !== null) {
                    $query->whereKey($principalId);
                }

                $query->orWhereHas('permissions', fn (Builder $permissionQuery) => $permissionQuery->where('name', $permission));
            });
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'prestataire_id');
    }

    public function validatedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'validated_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
