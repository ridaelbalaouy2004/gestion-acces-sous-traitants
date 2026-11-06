<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_id', 'type', 'description', 'member_name', 'user_id', 'reason'])]
class DocumentRevision extends Model
{
    public const TYPE_MEMBER_ADDED = 'member_added';

    public const TYPE_MEMBER_UPDATED = 'member_updated';

    public const TYPE_MEMBER_DELETED = 'member_deleted';

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_MEMBER_ADDED => 'Ajout d’un membre',
            self::TYPE_MEMBER_UPDATED => 'Modification d’un membre',
            self::TYPE_MEMBER_DELETED => 'Suppression d’un membre',
            default => 'Modification du document',
        };
    }
}
