<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_machinery_id',
    'company_machinery_document_id',
    'user_id',
    'action',
    'from_status',
    'to_status',
    'reason',
    'metadata',
])]
class CompanyMachineryHistory extends Model
{
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function machinery(): BelongsTo
    {
        return $this->belongsTo(CompanyMachinery::class, 'company_machinery_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CompanyMachineryDocument::class, 'company_machinery_document_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
