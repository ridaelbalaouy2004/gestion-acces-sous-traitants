<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_employee_id',
    'company_employee_document_id',
    'user_id',
    'action',
    'from_status',
    'to_status',
    'reason',
    'metadata',
])]
class CompanyEmployeeHistory extends Model
{
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(CompanyEmployee::class, 'company_employee_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CompanyEmployeeDocument::class, 'company_employee_document_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
