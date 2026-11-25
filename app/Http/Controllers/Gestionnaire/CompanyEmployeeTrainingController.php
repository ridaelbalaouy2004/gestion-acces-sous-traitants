<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Http\Controllers\Controller;
use App\Models\CompanyEmployee;
use App\Models\CompanyEmployeeTraining;
use App\Models\CompanyEmployeeTrainingVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyEmployeeTrainingController extends Controller
{
    public function downloadCertificate(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeTraining $training,
    ): StreamedResponse {
        [, $training] = $this->accessibleTraining($request, $employee, $training);

        return $this->privateDownload($training->file_path, $training->file_name);
    }

    public function previewCertificate(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeTraining $training,
    ): StreamedResponse {
        [, $training] = $this->accessibleTraining($request, $employee, $training);

        return $this->streamPrivateDocumentInline(
            $training->file_path,
            $training->file_name,
            $training->mime_type,
        );
    }

    public function downloadVersion(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeTraining $training,
        CompanyEmployeeTrainingVersion $version,
    ): StreamedResponse {
        [, $training] = $this->accessibleTraining($request, $employee, $training);
        $version = $training->versions()->findOrFail($version->getKey());

        return $this->privateDownload($version->file_path, $version->file_name);
    }

    public function previewVersion(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeTraining $training,
        CompanyEmployeeTrainingVersion $version,
    ): StreamedResponse {
        [, $training] = $this->accessibleTraining($request, $employee, $training);
        $version = $training->versions()->findOrFail($version->getKey());

        return $this->streamPrivateDocumentInline($version->file_path, $version->file_name, $version->mime_type);
    }

    /**
     * @return array{CompanyEmployee, CompanyEmployeeTraining}
     */
    private function accessibleTraining(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeTraining $training,
    ): array {
        $manager = $request->user();
        abort_unless(
            $manager instanceof User
            && $manager->roles()->where('name', 'gestionnaire')->exists()
            && $manager->hasPermission('employees.download'),
            403,
        );

        $employee = CompanyEmployee::query()
            ->visibleToManager()
            ->whereKey($employee->getKey())
            ->whereHas('company', fn (Builder $query) => $query
                ->whereIn('prestataire_id', $manager->accessiblePrestataires()->select('users.id')))
            ->firstOrFail();
        $training = $employee->trainings()->findOrFail($training->getKey());

        return [$employee, $training];
    }

    private function privateDownload(?string $path, ?string $fileName): StreamedResponse
    {
        abort_unless(is_string($path) && Storage::disk('documents_private')->exists($path), 404);

        return Storage::disk('documents_private')->download($path, $fileName ?: 'justificatif-formation.pdf');
    }
}
