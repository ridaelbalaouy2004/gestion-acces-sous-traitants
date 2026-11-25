<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_tooling_id',
    'company_tooling_document_id',
    'user_id',
    'action',
    'from_status',
    'to_status',
    'reason',
    'metadata',
])]
class CompanyToolingHistory extends Model
{
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function tooling(): BelongsTo
    {
        return $this->belongsTo(CompanyTooling::class, 'company_tooling_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CompanyToolingDocument::class, 'company_tooling_document_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
