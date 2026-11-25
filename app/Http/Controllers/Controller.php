<?php

namespace App\Http\Controllers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class Controller
{
    protected function generatedDocumentFileName(
        UploadedFile $file,
        string $documentLabel,
        string $recordReference,
    ): string {
        $extension = $file->getClientOriginalExtension();

        if ($extension === '' || preg_match('/\A[A-Za-z0-9]{1,10}\z/', $extension) !== 1) {
            $extension = $file->guessExtension() ?? '';
        }

        if (preg_match('/\A[A-Za-z0-9]{1,10}\z/', $extension) !== 1) {
            throw new RuntimeException('L’extension du fichier téléversé n’a pas pu être déterminée.');
        }

        $name = Str::slug($documentLabel.'-'.$recordReference).'-'.now()->format('Ymd');
        $name = mb_substr($name, 0, 254 - mb_strlen($extension));
        $name = rtrim($name, '-');

        return ($name !== '' ? $name : 'document').'.'.$extension;
    }

    protected function deletePrivateDocumentFile(string $path): void
    {
        $disk = Storage::disk('documents_private');

        if ($disk->exists($path) && ! $disk->delete($path)) {
            throw new RuntimeException('Le fichier privé n’a pas pu être supprimé.');
        }
    }

    protected function streamPrivateDocumentInline(
        ?string $path,
        ?string $fileName,
        ?string $mimeType,
    ): StreamedResponse {
        $disk = Storage::disk('documents_private');
        $previewableMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];

        abort_unless(
            is_string($path)
                && $disk->exists($path)
                && is_string($mimeType)
                && in_array($mimeType, $previewableMimeTypes, true),
            404,
        );

        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('Le fichier privé n’a pas pu être lu.');
        }

        $prefix = fread($stream, 8192);
        $detectedMimeType = is_string($prefix)
            ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($prefix)
            : false;

        if ($detectedMimeType !== $mimeType) {
            fclose($stream);

            abort(404);
        }

        $safeFileName = Str::of($fileName ?? 'document')
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9._-]/', '_')
            ->limit(180, '')
            ->toString();
        $safeFileName = $safeFileName !== '' ? $safeFileName : 'document';

        return response()->stream(
            function () use ($prefix, $stream): void {
                try {
                    echo $prefix;
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            },
            200,
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'Content-Disposition' => 'inline; filename="'.$safeFileName.'"',
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    protected function streamPrivateImageInline(?string $path): StreamedResponse
    {
        $disk = Storage::disk('documents_private');

        abort_unless(is_string($path) && $disk->exists($path), 404);

        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('La photo privée n’a pas pu être lue.');
        }

        $prefix = fread($stream, 8192);
        $mimeType = is_string($prefix)
            ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($prefix)
            : false;
        $extensionsByMimeType = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (! is_string($mimeType) || ! array_key_exists($mimeType, $extensionsByMimeType)) {
            fclose($stream);

            abort(404);
        }

        $extension = $extensionsByMimeType[$mimeType];

        return response()->stream(
            function () use ($prefix, $stream): void {
                try {
                    echo $prefix;
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            },
            200,
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'Content-Disposition' => 'inline; filename="profile-photo.'.$extension.'"',
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
