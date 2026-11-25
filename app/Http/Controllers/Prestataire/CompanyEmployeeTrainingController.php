<?php

namespace App\Http\Controllers\Prestataire;

use App\Http\Controllers\Controller;
use App\Models\CompanyEmployee;
use App\Models\CompanyEmployeeTraining;
use App\Models\CompanyEmployeeTrainingVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CompanyEmployeeTrainingController extends Controller
{
    public function store(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        $employee = $this->ownedEmployee($request, $employee);
        $validated = $request->validate($this->trainingRules(), $this->trainingMessages());
        $certificate = $validated['certificate'] ?? null;
        unset($validated['certificate']);
        $file = $certificate instanceof UploadedFile
            ? $this->storeCertificate($certificate, $employee, $validated['title'])
            : null;

        try {
            DB::transaction(function () use ($employee, $validated, $file, $request): void {
                $lockedEmployee = CompanyEmployee::query()
                    ->whereKey($employee->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $training = $lockedEmployee->trainings()->create($this->trainingAttributes($validated));

                if ($file !== null) {
                    $this->recordCertificateVersion($training, $file, $request->user()->getKey());
                }

                $lockedEmployee->history()->create([
                    'user_id' => $request->user()->getKey(),
                    'action' => 'ajout_formation',
                    'reason' => 'Ajout d’une formation.',
                    'metadata' => [
                        'training_id' => $training->getKey(),
                        'title' => $training->title,
                        'certificate_uploaded' => $file !== null,
                    ],
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredCertificateAfterFailure($file, $exception);

            throw $exception;
        }

        return to_route('prestataire.employees.show', $employee)
            ->with('status', 'La formation a été ajoutée.');
    }

    public function update(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeTraining $training,
    ): RedirectResponse {
        $employee = $this->ownedEmployee($request, $employee);
        $training = $this->ownedTraining($employee, $training);
        $validated = $request->validate($this->trainingRules(), $this->trainingMessages());
        $certificate = $validated['certificate'] ?? null;
        unset($validated['certificate']);
        $file = $certificate instanceof UploadedFile
            ? $this->storeCertificate($certificate, $employee, $validated['title'])
            : null;

        try {
            DB::transaction(function () use ($employee, $training, $validated, $file, $request): void {
                $lockedTraining = $employee->trainings()
                    ->whereKey($training->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $originalTitle = $lockedTraining->title;
                $lockedTraining->update($this->trainingAttributes($validated));

                if ($file !== null) {
                    $this->recordCertificateVersion($lockedTraining, $file, $request->user()->getKey());
                }

                $employee->history()->create([
                    'user_id' => $request->user()->getKey(),
                    'action' => 'modification_formation',
                    'reason' => 'Modification d’une formation.',
                    'metadata' => [
                        'training_id' => $lockedTraining->getKey(),
                        'title_before' => $originalTitle,
                        'title_after' => $lockedTraining->title,
                        'certificate_replaced' => $file !== null,
                    ],
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredCertificateAfterFailure($file, $exception);

            throw $exception;
        }

        return to_route('prestataire.employees.show', $employee)
            ->with('status', 'La formation a été mise à jour.');
    }

    public function destroy(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeTraining $training,
    ): RedirectResponse {
        $employee = $this->ownedEmployee($request, $employee);
        $training = $this->ownedTraining($employee, $training);

        DB::transaction(function () use ($employee, $training, $request): void {
            $lockedTraining = $employee->trainings()
                ->whereKey($training->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedTraining->delete();
            $employee->history()->create([
                'user_id' => $request->user()->getKey(),
                'action' => 'suppression_formation',
                'reason' => 'Suppression logique d’une formation.',
                'metadata' => [
                    'training_id' => $lockedTraining->getKey(),
                    'title' => $lockedTraining->title,
                ],
            ]);
        });

        return to_route('prestataire.employees.show', $employee)
            ->with('status', 'La formation a été supprimée.');
    }

    public function downloadCertificate(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeTraining $training,
    ): StreamedResponse {
        $employee = $this->ownedEmployee($request, $employee);
        $training = $this->ownedTraining($employee, $training);

        return $this->privateDownload($training->file_path, $training->file_name);
    }

    public function previewCertificate(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeTraining $training,
    ): StreamedResponse {
        $employee = $this->ownedEmployee($request, $employee);
        $training = $this->ownedTraining($employee, $training);

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
        $employee = $this->ownedEmployee($request, $employee);
        $training = $this->ownedTraining($employee, $training);
        $version = $training->versions()->findOrFail($version->getKey());

        return $this->privateDownload($version->file_path, $version->file_name);
    }

    public function previewVersion(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeTraining $training,
        CompanyEmployeeTrainingVersion $version,
    ): StreamedResponse {
        $employee = $this->ownedEmployee($request, $employee);
        $training = $this->ownedTraining($employee, $training);
        $version = $training->versions()->findOrFail($version->getKey());

        return $this->streamPrivateDocumentInline($version->file_path, $version->file_name, $version->mime_type);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function trainingRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'training_date' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'certificate' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
                'max:10240',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function trainingMessages(): array
    {
        return [
            'title.required' => 'L’intitulé de la formation est obligatoire.',
            'title.max' => 'L’intitulé de la formation ne peut pas dépasser 255 caractères.',
            'provider.max' => 'L’organisme ne peut pas dépasser 255 caractères.',
            'training_date.date' => 'La date de formation doit être une date valide.',
            'expires_at.date' => 'La date d’expiration doit être une date valide.',
            'description.max' => 'Les remarques ne peuvent pas dépasser 5 000 caractères.',
            'certificate.mimes' => 'Le justificatif doit être au format PDF, JPG, JPEG ou PNG.',
            'certificate.mimetypes' => 'Le type de fichier du justificatif n’est pas autorisé.',
            'certificate.max' => 'Le justificatif ne peut pas dépasser 10 Mo.',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function trainingAttributes(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'provider' => $validated['provider'] ?? null,
            'training_date' => $validated['training_date'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'description' => $validated['description'] ?? null,
        ];
    }

    /**
     * @return array{file_name: string, file_path: string, mime_type: string}
     */
    private function storeCertificate(
        UploadedFile $certificate,
        CompanyEmployee $employee,
        string $trainingTitle,
    ): array {
        $extension = $certificate->guessExtension();

        if (! is_string($extension)) {
            throw new RuntimeException('Le type du justificatif n’a pas pu être déterminé.');
        }

        $fileName = $this->generatedDocumentFileName(
            $certificate,
            'Certificat '.($trainingTitle ?: 'formation'),
            $employee->cin ?: trim($employee->first_name.' '.$employee->last_name) ?: (string) $employee->getKey(),
        );
        $path = $certificate->storeAs(
            'company-employee-trainings/'.$employee->getKey(),
            Str::uuid()->toString().'.'.$extension,
            'documents_private',
        );

        if (! is_string($path)) {
            throw new RuntimeException('Le justificatif n’a pas pu être enregistré.');
        }

        $mimeType = $certificate->getMimeType();

        if (! is_string($mimeType)) {
            Storage::disk('documents_private')->delete($path);

            throw new RuntimeException('Le type du justificatif n’a pas pu être déterminé.');
        }

        return [
            'file_name' => $fileName,
            'file_path' => $path,
            'mime_type' => $mimeType,
        ];
    }

    /**
     * @param  array{file_name: string, file_path: string, mime_type: ?string}  $file
     */
    private function recordCertificateVersion(
        CompanyEmployeeTraining $training,
        array $file,
        int $uploadedBy,
    ): void {
        $currentVersion = $training->versions()
            ->where('is_current', true)
            ->lockForUpdate()
            ->first();
        $versionNumber = ($training->versions()->max('version') ?? 0) + 1;
        $currentVersion?->update(['is_current' => false]);
        $training->update($file);
        $training->versions()->create([
            ...$file,
            'version' => $versionNumber,
            'uploaded_by' => $uploadedBy,
            'uploaded_at' => now(),
            'notes' => $currentVersion === null
                ? 'Justificatif initial de la formation.'
                : 'Remplacement du justificatif de la formation.',
            'is_current' => true,
        ]);
    }

    /**
     * @param  array{file_name: string, file_path: string, mime_type: string}|null  $file
     */
    private function deleteStoredCertificateAfterFailure(?array $file, Throwable $exception): void
    {
        if ($file === null) {
            return;
        }

        if (! Storage::disk('documents_private')->delete($file['file_path'])) {
            throw new RuntimeException(
                'Le justificatif enregistré avant l’échec n’a pas pu être supprimé.',
                previous: $exception,
            );
        }
    }

    private function ownedEmployee(Request $request, CompanyEmployee $employee): CompanyEmployee
    {
        $prestataire = $request->user();
        abort_unless($prestataire instanceof User && $prestataire->roles()->where('name', 'prestataire')->exists(), 403);

        return CompanyEmployee::query()
            ->whereKey($employee->getKey())
            ->whereHas('company', fn (Builder $query) => $query->where('prestataire_id', $prestataire->getKey()))
            ->firstOrFail();
    }

    private function ownedTraining(
        CompanyEmployee $employee,
        CompanyEmployeeTraining $training,
    ): CompanyEmployeeTraining {
        return $employee->trainings()->findOrFail($training->getKey());
    }

    private function privateDownload(?string $path, ?string $fileName): StreamedResponse
    {
        abort_unless(is_string($path) && Storage::disk('documents_private')->exists($path), 404);

        return Storage::disk('documents_private')->download($path, $fileName ?: 'justificatif-formation.pdf');
    }
}
