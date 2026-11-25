<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_machinery_document_id',
    'version',
    'file_name',
    'file_path',
    'mime_type',
    'uploaded_by',
    'uploaded_at',
    'notes',
    'is_current',
])]
class CompanyMachineryDocumentVersion extends Model
{
    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CompanyMachineryDocument::class, 'company_machinery_document_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
