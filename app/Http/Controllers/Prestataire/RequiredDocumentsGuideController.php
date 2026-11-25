<?php

namespace App\Http\Controllers\Prestataire;

use App\Http\Controllers\Controller;
use App\Services\SharedRequiredDocumentsGuideService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequiredDocumentsGuideController extends Controller
{
    public function download(SharedRequiredDocumentsGuideService $guideService): StreamedResponse
    {
        $guide = $guideService->current();
        abort_unless($guide !== null && $guideService->exists($guide), 404);

        return Storage::disk(SharedRequiredDocumentsGuideService::STORAGE_DISK)->download(
            $guide->file_path,
            $guide->file_name,
            ['Content-Type' => SharedRequiredDocumentsGuideService::MIME_TYPE],
        );
    }
}
