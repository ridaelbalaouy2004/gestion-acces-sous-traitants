<?php

namespace App\Services;

use App\Models\SharedRequiredDocumentsGuide;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SharedRequiredDocumentsGuideService
{
    public const STORAGE_DISK = 'documents_private';

    public const MIME_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    private const STORAGE_DIRECTORY = 'shared-required-documents-guides';

    public function current(): ?SharedRequiredDocumentsGuide
    {
        return SharedRequiredDocumentsGuide::query()->find(SharedRequiredDocumentsGuide::SINGLETON_ID);
    }

    public function delete(): bool
    {
        $disk = Storage::disk(self::STORAGE_DISK);

        return DB::transaction(function () use ($disk): bool {
            $guide = SharedRequiredDocumentsGuide::query()
                ->lockForUpdate()
                ->find(SharedRequiredDocumentsGuide::SINGLETON_ID);

            if ($guide === null) {
                return false;
            }

            if ($disk->exists($guide->file_path) && ! $disk->delete($guide->file_path)) {
                throw new RuntimeException('Le fichier privé du Guide n’a pas pu être supprimé.');
            }

            $guide->delete();

            return true;
        });
    }

    public function exists(SharedRequiredDocumentsGuide $guide): bool
    {
        return Storage::disk(self::STORAGE_DISK)->exists($guide->file_path);
    }

    public function replace(UploadedFile $upload): SharedRequiredDocumentsGuide
    {
        $disk = Storage::disk(self::STORAGE_DISK);
        $fileName = Str::uuid()->toString().'.docx';
        $targetPath = self::STORAGE_DIRECTORY.'/'.$fileName;

        try {
            $path = $upload->storeAs(self::STORAGE_DIRECTORY, $fileName, self::STORAGE_DISK);
        } catch (Throwable $exception) {
            $this->cleanupFile($disk, $targetPath);

            throw $exception;
        }

        if (! is_string($path)) {
            $this->cleanupFile($disk, $targetPath);

            throw new RuntimeException('Le Guide Word n’a pas pu être enregistré dans le stockage privé.');
        }

        $oldPath = null;

        try {
            $guide = DB::transaction(function () use ($path, $upload, &$oldPath): SharedRequiredDocumentsGuide {
                $guide = SharedRequiredDocumentsGuide::query()
                    ->lockForUpdate()
                    ->find(SharedRequiredDocumentsGuide::SINGLETON_ID)
                    ?? new SharedRequiredDocumentsGuide(['id' => SharedRequiredDocumentsGuide::SINGLETON_ID]);

                $oldPath = $guide->file_path;
                $guide->file_name = $this->safeOriginalFileName($upload->getClientOriginalName());
                $guide->file_path = $path;
                $guide->save();

                return $guide;
            });
        } catch (Throwable $exception) {
            $this->cleanupFile($disk, $path);

            throw $exception;
        }

        if (is_string($oldPath) && $oldPath !== $path) {
            try {
                if ($disk->exists($oldPath) && ! $disk->delete($oldPath)) {
                    Log::warning('L’ancien fichier du Guide n’a pas pu être supprimé après son remplacement.');
                }
            } catch (Throwable $exception) {
                Log::warning('L’ancien fichier du Guide n’a pas pu être supprimé après son remplacement.', [
                    'exception' => $exception::class,
                ]);
            }
        }

        return $guide;
    }

    public function contents(SharedRequiredDocumentsGuide $guide): string
    {
        $disk = Storage::disk(self::STORAGE_DISK);

        if (! $this->exists($guide)) {
            throw new RuntimeException('Le fichier du Guide partagé est introuvable dans le stockage privé.');
        }

        $contents = $disk->get($guide->file_path);

        if (! is_string($contents)) {
            throw new RuntimeException('Le fichier du Guide partagé n’a pas pu être lu.');
        }

        return $contents;
    }

    private function safeOriginalFileName(string $fileName): string
    {
        $fileName = basename(str_replace('\\', '/', $fileName));
        $fileName = preg_replace('/[\x00-\x1F\x7F]/u', '', $fileName) ?: 'guide.docx';

        if (mb_strlen($fileName) > 255) {
            return mb_substr($fileName, 0, 250).'.docx';
        }

        return $fileName;
    }

    private function cleanupFile(FilesystemAdapter $disk, string $path): void
    {
        try {
            if ($disk->exists($path) && ! $disk->delete($path)) {
                Log::warning('Un fichier temporaire du Guide n’a pas pu être supprimé.');
            }
        } catch (Throwable $exception) {
            Log::warning('Un fichier temporaire du Guide n’a pas pu être supprimé.', [
                'exception' => $exception::class,
            ]);
        }
    }
}
