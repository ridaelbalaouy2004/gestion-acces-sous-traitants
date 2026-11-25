<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Mail\RequiredDocumentsGuide;
use App\Models\User;
use App\Services\SharedRequiredDocumentsGuideService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RequiredDocumentsGuideController extends Controller
{
    public function index(Request $request, SharedRequiredDocumentsGuideService $guideService): View
    {
        $manager = $this->manager($request);
        $guide = $guideService->current();
        $canManageGuide = $manager->isPrimaryManager();

        return view('gestionnaire.guides.required-documents', $this->managerViewData([
            'guide' => $guide,
            'guideAvailable' => $guide !== null && $guideService->exists($guide),
            'canUploadGuide' => $canManageGuide,
            'canDeleteGuide' => $canManageGuide,
            'prestataires' => $manager->accessiblePrestataires()
                ->orderBy('name')
                ->orderBy('id')
                ->get(['users.id', 'users.name', 'users.email']),
            'sendToken' => Str::random(64),
            'defaultSubject' => RequiredDocumentsGuide::DEFAULT_SUBJECT,
            'defaultMessage' => RequiredDocumentsGuide::DEFAULT_MESSAGE,
        ]));
    }

    public function store(Request $request, SharedRequiredDocumentsGuideService $guideService): RedirectResponse
    {
        $manager = $this->manager($request);
        abort_unless($manager->isPrimaryManager(), 403);

        $validated = $request->validate([
            'guide' => [
                'required',
                'file',
                'mimes:docx',
                'mimetypes:'.SharedRequiredDocumentsGuideService::MIME_TYPE,
                'extensions:docx',
                'max:10240',
            ],
        ], [
            'guide.required' => 'Sélectionnez le fichier Word du Guide.',
            'guide.file' => 'Le fichier sélectionné est invalide.',
            'guide.mimes' => 'Le Guide doit être un fichier Word .docx valide.',
            'guide.mimetypes' => 'Le type MIME du fichier doit correspondre à un document Word .docx.',
            'guide.extensions' => 'Seuls les fichiers portant l’extension .docx sont autorisés.',
            'guide.max' => 'Le Guide ne peut pas dépasser 10 Mo.',
        ]);
        $upload = $validated['guide'];

        if (! $upload instanceof UploadedFile) {
            throw new RuntimeException('Le fichier Word validé est introuvable dans la requête.');
        }

        try {
            $guideService->replace($upload);
        } catch (Throwable $exception) {
            Log::error('Le Guide partagé n’a pas pu être téléversé ou remplacé.', [
                'manager_id' => $manager->getKey(),
                'exception' => $exception::class,
            ]);

            return back()->withErrors([
                'guide' => 'Le Guide Word n’a pas pu être enregistré. Le Guide précédent est conservé.',
            ]);
        }

        return to_route('gestionnaire.guides.required-documents.index')
            ->with('status', 'Le Guide Word partagé a été mis à jour.');
    }

    public function destroy(Request $request, SharedRequiredDocumentsGuideService $guideService): RedirectResponse
    {
        $manager = $this->manager($request);
        abort_unless($manager->isPrimaryManager(), 403);

        try {
            if (! $guideService->delete()) {
                return to_route('gestionnaire.guides.required-documents.index')
                    ->withErrors(['guide' => 'Aucun Guide partagé n’est disponible pour suppression.']);
            }
        } catch (Throwable $exception) {
            Log::error('La suppression du Guide partagé a échoué.', [
                'manager_id' => $manager->getKey(),
                'exception' => $exception::class,
            ]);

            return to_route('gestionnaire.guides.required-documents.index')
                ->withErrors(['guide' => 'La suppression du Guide Word n’a pas pu être terminée. Rechargez la page et vérifiez son état.']);
        }

        Log::info('Guide des documents requis supprimé.', [
            'manager_id' => $manager->getKey(),
            'result' => 'deleted',
        ]);

        return to_route('gestionnaire.guides.required-documents.index')
            ->with('status', 'Le Guide Word partagé a été supprimé.');
    }

    public function downloadForManager(SharedRequiredDocumentsGuideService $guideService): StreamedResponse
    {
        return $this->download($guideService);
    }

    public function downloadForPrestataire(
        Request $request,
        User $prestataire,
        SharedRequiredDocumentsGuideService $guideService,
    ): StreamedResponse {
        $manager = $this->manager($request);
        $this->ensurePrestataireAccessible($manager, $prestataire);

        return $this->download($guideService);
    }

    public function sendToSelectedPrestataire(
        Request $request,
        SharedRequiredDocumentsGuideService $guideService,
    ): RedirectResponse {
        $validated = $request->validate([
            'prestataire_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ], [
            'prestataire_id.required' => 'Sélectionnez un Prestataire destinataire.',
            'prestataire_id.exists' => 'Le Prestataire sélectionné est invalide.',
        ]);
        $manager = $this->manager($request);
        $prestataire = $manager->accessiblePrestataires()
            ->whereKey($validated['prestataire_id'])
            ->firstOrFail();

        return $this->send($request, $manager, $prestataire, $guideService, 'gestionnaire.guides.required-documents.index');
    }

    public function sendToPrestataire(
        Request $request,
        User $prestataire,
        SharedRequiredDocumentsGuideService $guideService,
    ): RedirectResponse {
        $manager = $this->manager($request);
        $this->ensurePrestataireAccessible($manager, $prestataire);

        return $this->send(
            $request,
            $manager,
            $prestataire,
            $guideService,
            'gestionnaire.prestataires.show',
            ['prestataire' => $prestataire],
        );
    }

    private function send(
        Request $request,
        User $manager,
        User $prestataire,
        SharedRequiredDocumentsGuideService $guideService,
        string $redirectRoute,
        array $routeParameters = [],
    ): RedirectResponse {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            'message' => ['required', 'string', 'max:10000'],
            'send_token' => ['required', 'string', 'size:64'],
            'confirmed' => ['accepted'],
        ], [
            'subject.required' => 'L’objet de l’e-mail est obligatoire.',
            'subject.max' => 'L’objet ne peut pas dépasser 255 caractères.',
            'subject.not_regex' => 'L’objet ne peut pas contenir de saut de ligne.',
            'message.required' => 'Le message de l’e-mail est obligatoire.',
            'message.max' => 'Le message ne peut pas dépasser 10 000 caractères.',
            'send_token.required' => 'Rechargez le formulaire avant de renvoyer le Guide.',
            'confirmed.accepted' => 'Confirmez l’envoi du Guide avant de continuer.',
        ]);
        $guide = $guideService->current();

        if ($guide === null || ! $guideService->exists($guide)) {
            return back()->withErrors(['guide' => 'Aucun Guide Word disponible ne peut être envoyé.']);
        }

        if (filter_var($prestataire->email, FILTER_VALIDATE_EMAIL) === false) {
            return back()->withErrors(['guide' => 'L’adresse e-mail enregistrée dans le compte Prestataire est invalide.']);
        }

        $configurationError = $this->mailConfigurationError();

        if ($configurationError !== null) {
            Log::warning('Guide non envoyé : configuration de messagerie incomplète.', [
                'manager_id' => $manager->getKey(),
                'prestataire_id' => $prestataire->getKey(),
                'result' => 'mail_configuration_missing',
            ]);

            return back()->withErrors(['guide' => $configurationError]);
        }

        $cacheKey = 'required-documents-guide-send:'.hash('sha256', $validated['send_token']);

        if (! Cache::add($cacheKey, true, now()->addMinutes(10))) {
            return back()->withErrors(['guide' => 'Cette demande d’envoi a déjà été traitée. Rechargez la page pour effectuer un nouvel envoi.']);
        }

        try {
            $guideContents = $guideService->contents($guide);
        } catch (Throwable $exception) {
            Log::error('Le fichier du Guide partagé n’a pas pu être lu avant envoi.', [
                'manager_id' => $manager->getKey(),
                'prestataire_id' => $prestataire->getKey(),
                'exception' => $exception::class,
            ]);

            return back()->withErrors(['guide' => 'Le fichier du Guide est actuellement indisponible. Réessayez ou contactez l’administrateur.']);
        }

        try {
            Mail::to($prestataire->email)->send(new RequiredDocumentsGuide(
                $validated['subject'],
                $validated['message'],
                $guide->file_name,
                $guideContents,
            ));
        } catch (Throwable $exception) {
            Log::error('L’envoi du Guide partagé par e-mail a échoué.', [
                'manager_id' => $manager->getKey(),
                'prestataire_id' => $prestataire->getKey(),
                'result' => 'mail_delivery_failed',
                'exception' => $exception::class,
            ]);

            return back()->withErrors([
                'guide' => 'L’envoi du Guide a échoué. Vérifiez la configuration de la messagerie et réessayez.',
            ]);
        }

        Log::info('Guide des documents requis envoyé au Prestataire.', [
            'manager_id' => $manager->getKey(),
            'prestataire_id' => $prestataire->getKey(),
            'result' => 'sent',
        ]);

        return to_route($redirectRoute, $routeParameters)
            ->with('status', 'Le Guide a été envoyé par e-mail au Prestataire.');
    }

    private function download(SharedRequiredDocumentsGuideService $guideService): StreamedResponse
    {
        $guide = $guideService->current();
        abort_unless($guide !== null && $guideService->exists($guide), 404);

        return Storage::disk(SharedRequiredDocumentsGuideService::STORAGE_DISK)->download(
            $guide->file_path,
            $guide->file_name,
            ['Content-Type' => SharedRequiredDocumentsGuideService::MIME_TYPE],
        );
    }

    private function ensurePrestataireAccessible(User $manager, User $prestataire): void
    {
        abort_unless(
            $prestataire->roles()->where('name', 'prestataire')->exists()
                && $manager->accessiblePrestataires()->whereKey($prestataire->getKey())->exists(),
            404,
        );
    }

    private function manager(Request $request): User
    {
        $manager = $request->user();
        abort_unless($manager instanceof User && $manager->hasPermission('prestataires.view'), 403);

        return $manager;
    }

    private function mailConfigurationError(): ?string
    {
        $mailer = config('mail.default');
        $mailers = config('mail.mailers', []);

        if (
            ! is_string($mailer)
            || ! is_array($mailers)
            || ! array_key_exists($mailer, $mailers)
            || in_array($mailer, ['log', 'array'], true)
        ) {
            return 'La messagerie Laravel n’est pas configurée pour une livraison réelle. Configurez MAIL_MAILER avec un transport de messagerie.';
        }

        if ($mailer !== 'smtp') {
            return null;
        }

        $missingSettings = [];

        if (blank(config('mail.mailers.smtp.host'))) {
            $missingSettings[] = 'MAIL_HOST';
        }

        if (blank(config('mail.from.address'))) {
            $missingSettings[] = 'MAIL_FROM_ADDRESS';
        }

        return $missingSettings === []
            ? null
            : 'La configuration SMTP est incomplète. Paramètres manquants : '.implode(', ', $missingSettings).'.';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function managerViewData(array $data = []): array
    {
        return array_merge([
            'role' => 'gestionnaire',
            'routePrefix' => 'gestionnaire',
            'roleLabel' => 'Gestionnaire',
            'navigation' => DashboardController::navigationForUser(auth()->user()),
        ], $data);
    }
}
