<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['principal_user_id'])]
class ManagerSettings extends Model
{
    public const SINGLETON_ID = 1;

    protected $table = 'manager_settings';

    public $incrementing = false;

    protected $keyType = 'int';

    public function principal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'principal_user_id');
    }
}
