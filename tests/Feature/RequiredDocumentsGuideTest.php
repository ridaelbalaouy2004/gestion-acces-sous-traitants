<?php

namespace Tests\Feature;

use App\Mail\RequiredDocumentsGuide;
use App\Models\ManagerSettings;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedRequiredDocumentsGuide;
use App\Models\User;
use App\Services\SharedRequiredDocumentsGuideService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class RequiredDocumentsGuideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Storage::fake(SharedRequiredDocumentsGuideService::STORAGE_DISK);
    }

    public function test_primary_manager_can_upload_and_replace_the_single_private_shared_docx(): void
    {
        $manager = $this->primaryManager();

        $this->actingAs($manager)
            ->get(route('gestionnaire.guides.required-documents.index'))
            ->assertOk()
            ->assertSee('Aucun guide n’a encore été téléversé.')
            ->assertSee('Téléverser le Guide Word')
            ->assertDontSee('Supprimer le Guide');

        $this->uploadGuide($manager, 'Guide initial.docx');
        $this->actingAs($manager)
            ->get(route('gestionnaire.guides.required-documents.index'))
            ->assertOk()
            ->assertSee('Supprimer le Guide')
            ->assertSee('data-confirm="Voulez-vous vraiment supprimer le Guide des documents requis ? Cette action est irréversible."', false);

        $guide = SharedRequiredDocumentsGuide::query()->findOrFail(SharedRequiredDocumentsGuide::SINGLETON_ID);
        $oldPath = $guide->file_path;
        $disk = Storage::disk(SharedRequiredDocumentsGuideService::STORAGE_DISK);

        $disk->assertExists($oldPath);

        $this->actingAs($manager)
            ->get(route('gestionnaire.guides.required-documents.download'))
            ->assertOk()
            ->assertHeader('content-type', SharedRequiredDocumentsGuideService::MIME_TYPE)
            ->assertHeader('content-disposition', 'attachment; filename="Guide initial.docx"');

        $this->uploadGuide($manager, 'Guide actualisé.docx');

        $updatedGuide = SharedRequiredDocumentsGuide::query()->findOrFail(SharedRequiredDocumentsGuide::SINGLETON_ID);
        $this->assertSame('Guide actualisé.docx', $updatedGuide->file_name);
        $this->assertNotSame($oldPath, $updatedGuide->file_path);
        $disk->assertMissing($oldPath);
        $disk->assertExists($updatedGuide->file_path);
        $this->assertCount(1, $disk->allFiles('shared-required-documents-guides'));
    }

    public function test_invalid_extension_mime_and_size_do_not_replace_the_current_guide(): void
    {
        $manager = $this->primaryManager();
        $this->uploadGuide($manager, 'Guide conservé.docx');
        $guide = SharedRequiredDocumentsGuide::query()->findOrFail(SharedRequiredDocumentsGuide::SINGLETON_ID);
        $oldPath = $guide->file_path;

        $invalidFiles = [
            $this->docx('guide.pdf'),
            $this->docx('guide.docx', 'application/pdf'),
            $this->docx('guide-trop-grand.docx', SharedRequiredDocumentsGuideService::MIME_TYPE, 10241),
        ];

        foreach ($invalidFiles as $invalidFile) {
            $this->actingAs($manager)
                ->from(route('gestionnaire.guides.required-documents.index'))
                ->post(route('gestionnaire.guides.required-documents.store'), ['guide' => $invalidFile])
                ->assertRedirect(route('gestionnaire.guides.required-documents.index'))
                ->assertSessionHasErrors('guide');
        }

        $this->assertSame($oldPath, $guide->fresh()->file_path);
        Storage::disk(SharedRequiredDocumentsGuideService::STORAGE_DISK)->assertExists($oldPath);
        $this->assertCount(1, Storage::disk(SharedRequiredDocumentsGuideService::STORAGE_DISK)->allFiles('shared-required-documents-guides'));
    }

    public function test_manager_section_scopes_recipients_and_only_primary_manager_can_upload(): void
    {
        $manager = $this->managerWithGuidePermission();
        $assignedPrestataire = $this->userWithRole('prestataire');
        $unassignedPrestataire = $this->userWithRole('prestataire');
        $this->assign($manager, $assignedPrestataire);
        $this->uploadGuide($this->primaryManager(), 'Guide partagé.docx');

        $this->actingAs($manager)
            ->get(route('gestionnaire.guides.required-documents.index'))
            ->assertOk()
            ->assertSee($assignedPrestataire->email)
            ->assertDontSee($unassignedPrestataire->email)
            ->assertSee('Confirmer et envoyer')
            ->assertDontSee('name="guide"', false)
            ->assertDontSee('type="file"', false);

        $this->actingAs($manager)
            ->delete(route('gestionnaire.guides.required-documents.destroy'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('gestionnaire.guides.required-documents.store'), ['guide' => $this->docx()])
            ->assertForbidden();
    }

    public function test_primary_manager_can_delete_the_guide_and_prestataires_lose_access(): void
    {
        $manager = $this->primaryManager();
        $firstPrestataire = $this->userWithRole('prestataire');
        $secondPrestataire = $this->userWithRole('prestataire');
        $this->uploadGuide($manager, 'Guide à supprimer.docx');
        $guide = SharedRequiredDocumentsGuide::query()->findOrFail(SharedRequiredDocumentsGuide::SINGLETON_ID);
        $disk = Storage::disk(SharedRequiredDocumentsGuideService::STORAGE_DISK);

        $this->actingAs($manager)
            ->from(route('gestionnaire.guides.required-documents.index'))
            ->delete(route('gestionnaire.guides.required-documents.destroy'))
            ->assertRedirect(route('gestionnaire.guides.required-documents.index'))
            ->assertSessionHas('status', 'Le Guide Word partagé a été supprimé.');

        $this->assertDatabaseMissing('shared_required_documents_guides', ['id' => SharedRequiredDocumentsGuide::SINGLETON_ID]);
        $disk->assertMissing($guide->file_path);

        $this->actingAs($manager)
            ->get(route('gestionnaire.guides.required-documents.index'))
            ->assertOk()
            ->assertSee('Aucun guide n’a encore été téléversé.')
            ->assertDontSee('Supprimer le Guide');

        foreach ([$firstPrestataire, $secondPrestataire] as $prestataire) {
            $this->actingAs($prestataire)
                ->get(route('prestataire.dashboard'))
                ->assertOk()
                ->assertSee("Le Guide des documents requis n'est pas encore disponible.", false)
                ->assertDontSee(route('prestataire.guides.required-documents.download'), false);

            $this->get(route('prestataire.guides.required-documents.download'))
                ->assertNotFound();
        }
    }

    public function test_all_prestataires_download_the_same_shared_file_and_cannot_manage_it(): void
    {
        $this->uploadGuide($this->primaryManager(), 'Guide commun.docx');
        $guide = SharedRequiredDocumentsGuide::query()->findOrFail(SharedRequiredDocumentsGuide::SINGLETON_ID);
        $contents = Storage::disk(SharedRequiredDocumentsGuideService::STORAGE_DISK)->get($guide->file_path);
        $guideService = app(SharedRequiredDocumentsGuideService::class);
        $this->assertSame($guide->getKey(), $guideService->current()?->getKey());
        $this->assertTrue($guideService->exists($guide));
        Storage::disk(SharedRequiredDocumentsGuideService::STORAGE_DISK)->assertExists($guide->file_path);
        $firstPrestataire = $this->userWithRole('prestataire');
        $secondPrestataire = $this->userWithRole('prestataire');

        foreach ([$firstPrestataire, $secondPrestataire] as $prestataire) {
            $this->actingAs($prestataire)
                ->get(route('prestataire.dashboard'))
                ->assertOk()
                ->assertViewHas('requiredDocumentsGuide', fn ($viewGuide): bool => $viewGuide instanceof SharedRequiredDocumentsGuide
                    && $viewGuide->is($guide))
                ->assertViewHas('requiredDocumentsGuideAvailable', true)
                ->assertSee('Guide commun.docx')
                ->assertSee(route('prestataire.guides.required-documents.download'), false)
                ->assertDontSee('Remplacer le Guide')
                ->assertSee('Résumé de mon espace')
                ->assertSee('Mes entreprises');

            $downloadResponse = $this->get(route('prestataire.guides.required-documents.download'))
                ->assertOk();

            $this->assertSame($contents, $downloadResponse->streamedContent());
        }

        $this->actingAs($firstPrestataire)
            ->post(route('prestataire.guides.required-documents.download'))
            ->assertMethodNotAllowed();
    }

    public function test_manager_can_send_custom_email_with_the_shared_attachment_only_after_confirmation(): void
    {
        $manager = $this->managerWithGuidePermission();
        $prestataire = $this->userWithRole('prestataire');
        $this->assign($manager, $prestataire);
        $this->uploadGuide($this->primaryManager(), 'Guide partagé.docx');
        $guide = SharedRequiredDocumentsGuide::query()->findOrFail(SharedRequiredDocumentsGuide::SINGLETON_ID);
        $contents = Storage::disk(SharedRequiredDocumentsGuideService::STORAGE_DISK)->get($guide->file_path);
        $sendToken = Str::random(64);
        Mail::fake();
        $this->configureSmtp();

        $this->actingAs($manager)
            ->get(route('gestionnaire.prestataires.show', $prestataire))
            ->assertOk()
            ->assertSee('data-guide-email-form', false)
            ->assertSee('Adresse récupérée automatiquement depuis le compte Prestataire.')
            ->assertSee('Voulez-vous vraiment envoyer ce Guide au Prestataire sélectionné ?')
            ->assertSee('Confirmer et envoyer');

        $sendRoute = route('gestionnaire.prestataires.guide.send', $prestataire);
        $emailData = [
            'send_token' => $sendToken,
            'subject' => 'Guide personnalisé',
            'message' => 'Bonjour, message personnalisé.',
            'recipient' => 'destinataire-non-autorise@example.test',
        ];

        $this->actingAs($manager)
            ->from(route('gestionnaire.prestataires.show', $prestataire))
            ->post($sendRoute, $emailData)
            ->assertRedirect(route('gestionnaire.prestataires.show', $prestataire))
            ->assertSessionHasErrors('confirmed');

        Mail::assertNothingSent();

        $this->actingAs($manager)
            ->from(route('gestionnaire.prestataires.show', $prestataire))
            ->post($sendRoute, $emailData + ['confirmed' => 'yes'])
            ->assertRedirect(route('gestionnaire.prestataires.show', $prestataire))
            ->assertSessionHas('status', 'Le Guide a été envoyé par e-mail au Prestataire.');

        Mail::assertSent(RequiredDocumentsGuide::class, function (RequiredDocumentsGuide $mail) use ($prestataire, $contents): bool {
            $mail->assertSeeInText('Bonjour, message personnalisé.');

            return $mail->hasTo($prestataire->email)
                && $mail->hasSubject('Guide personnalisé')
                && $mail->fileName === 'Guide partagé.docx'
                && $mail->guideContents === $contents
                && $mail->hasAttachment(
                    Attachment::fromData(
                        fn (): string => $mail->guideContents,
                        'Guide partagé.docx',
                    )->withMime(SharedRequiredDocumentsGuideService::MIME_TYPE),
                );
        });

        $this->actingAs($manager)
            ->from(route('gestionnaire.prestataires.show', $prestataire))
            ->post($sendRoute, $emailData + ['confirmed' => 'yes'])
            ->assertRedirect(route('gestionnaire.prestataires.show', $prestataire))
            ->assertSessionHasErrors('guide');

        Mail::assertSentCount(1);
    }

    public function test_manager_can_send_from_the_standalone_section_to_a_selected_accessible_provider(): void
    {
        $manager = $this->managerWithGuidePermission();
        $prestataire = $this->userWithRole('prestataire');
        $this->assign($manager, $prestataire);
        $this->uploadGuide($this->primaryManager(), 'Guide partagé.docx');
        Mail::fake();
        $this->configureSmtp();

        $this->actingAs($manager)
            ->get(route('gestionnaire.guides.required-documents.index'))
            ->assertOk()
            ->assertSee($prestataire->name.' — '.$prestataire->email)
            ->assertSee(RequiredDocumentsGuide::DEFAULT_SUBJECT)
            ->assertSee('Veuillez trouver en pièce jointe le Guide des documents requis pour préparer votre dossier.');

        $this->actingAs($manager)
            ->post(route('gestionnaire.guides.required-documents.send'), [
                'prestataire_id' => $prestataire->getKey(),
                'subject' => RequiredDocumentsGuide::DEFAULT_SUBJECT,
                'message' => RequiredDocumentsGuide::DEFAULT_MESSAGE,
                'send_token' => Str::random(64),
                'confirmed' => 'yes',
            ])
            ->assertRedirect(route('gestionnaire.guides.required-documents.index'))
            ->assertSessionHas('status');

        Mail::assertSent(RequiredDocumentsGuide::class, fn (RequiredDocumentsGuide $mail): bool => $mail->hasTo($prestataire->email));

        $unassignedPrestataire = $this->userWithRole('prestataire');

        $this->actingAs($manager)
            ->post(route('gestionnaire.guides.required-documents.send'), [
                'prestataire_id' => $unassignedPrestataire->getKey(),
                'subject' => RequiredDocumentsGuide::DEFAULT_SUBJECT,
                'message' => RequiredDocumentsGuide::DEFAULT_MESSAGE,
                'send_token' => Str::random(64),
                'confirmed' => 'yes',
            ])
            ->assertNotFound();

        Mail::assertSentCount(1);
    }

    public function test_users_without_existing_manager_access_cannot_view_upload_or_send_the_guide(): void
    {
        $manager = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $securityManager = $this->userWithRole('responsable_securite');

        $this->actingAs($manager)
            ->get(route('gestionnaire.guides.required-documents.index'))
            ->assertForbidden();

        $this->actingAs($securityManager)
            ->get(route('gestionnaire.guides.required-documents.index'))
            ->assertForbidden();

        $this->actingAs($securityManager)
            ->post(route('gestionnaire.guides.required-documents.store'), ['guide' => $this->docx()])
            ->assertForbidden();

        $this->actingAs($prestataire)
            ->post(route('gestionnaire.prestataires.guide.send', $prestataire), [
                'subject' => RequiredDocumentsGuide::DEFAULT_SUBJECT,
                'message' => RequiredDocumentsGuide::DEFAULT_MESSAGE,
                'send_token' => Str::random(64),
                'confirmed' => 'yes',
            ])
            ->assertForbidden();
    }

    public function test_missing_guide_has_no_download_or_email_action(): void
    {
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');

        $this->actingAs($manager)
            ->get(route('gestionnaire.guides.required-documents.index'))
            ->assertOk()
            ->assertSee('Aucun guide n’a encore été téléversé.')
            ->assertDontSee(route('gestionnaire.guides.required-documents.download'), false)
            ->assertDontSee('Envoyer par e-mail');

        $this->actingAs($prestataire)
            ->get(route('prestataire.dashboard'))
            ->assertOk()
            ->assertSee("Le Guide des documents requis n'est pas encore disponible.", false)
            ->assertDontSee(route('prestataire.guides.required-documents.download'), false);

        $this->actingAs($prestataire)
            ->get(route('prestataire.guides.required-documents.download'))
            ->assertNotFound();
    }

    public function test_mailer_and_delivery_failures_never_show_a_success_message(): void
    {
        $manager = $this->managerWithGuidePermission();
        $prestataire = $this->userWithRole('prestataire');
        $this->assign($manager, $prestataire);
        $this->uploadGuide($this->primaryManager(), 'Guide partagé.docx');
        Mail::fake();

        config(['mail.default' => 'log']);

        $this->actingAs($manager)
            ->from(route('gestionnaire.prestataires.show', $prestataire))
            ->post(route('gestionnaire.prestataires.guide.send', $prestataire), [
                'subject' => RequiredDocumentsGuide::DEFAULT_SUBJECT,
                'message' => RequiredDocumentsGuide::DEFAULT_MESSAGE,
                'send_token' => Str::random(64),
                'confirmed' => 'yes',
            ])
            ->assertRedirect(route('gestionnaire.prestataires.show', $prestataire))
            ->assertSessionHasErrors('guide')
            ->assertSessionMissing('status');

        Mail::assertNothingSent();

        $this->configureSmtp();
        Mail::shouldReceive('to')
            ->once()
            ->with($prestataire->email)
            ->andThrow(new RuntimeException('SMTP unavailable'));

        $this->actingAs($manager)
            ->from(route('gestionnaire.prestataires.show', $prestataire))
            ->post(route('gestionnaire.prestataires.guide.send', $prestataire), [
                'subject' => RequiredDocumentsGuide::DEFAULT_SUBJECT,
                'message' => RequiredDocumentsGuide::DEFAULT_MESSAGE,
                'send_token' => Str::random(64),
                'confirmed' => 'yes',
            ])
            ->assertRedirect(route('gestionnaire.prestataires.show', $prestataire))
            ->assertSessionHasErrors('guide')
            ->assertSessionMissing('status');
    }

    private function uploadGuide(User $manager, string $fileName): void
    {
        $this->actingAs($manager)
            ->post(route('gestionnaire.guides.required-documents.store'), ['guide' => $this->docx($fileName)])
            ->assertRedirect(route('gestionnaire.guides.required-documents.index'))
            ->assertSessionHas('status', 'Le Guide Word partagé a été mis à jour.');
    }

    private function docx(
        string $fileName = 'Guide.docx',
        string $mimeType = SharedRequiredDocumentsGuideService::MIME_TYPE,
        int $kilobytes = 100,
    ): UploadedFile {
        return UploadedFile::fake()->create($fileName, $kilobytes, $mimeType);
    }

    private function primaryManager(): User
    {
        $manager = $this->userWithRole('gestionnaire');
        ManagerSettings::query()->whereKey(ManagerSettings::SINGLETON_ID)->update([
            'principal_user_id' => $manager->getKey(),
        ]);

        return $manager;
    }

    private function managerWithGuidePermission(): User
    {
        $manager = $this->userWithRole('gestionnaire');
        $manager->permissions()->attach(Permission::query()->where('name', 'prestataires.view')->firstOrFail());

        return $manager;
    }

    private function assign(User $manager, User $prestataire): void
    {
        $manager->assignedPrestataires()->attach($prestataire, ['assigned_by' => $manager->getKey()]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }

    private function configureSmtp(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.from.address' => 'sender@example.test',
        ]);
    }
}
