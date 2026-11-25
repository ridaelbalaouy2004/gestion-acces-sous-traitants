<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SharedRequiredDocumentsGuide extends Model
{
    public const SINGLETON_ID = 1;

    public $incrementing = false;

    protected $fillable = [
        'id',
        'file_name',
        'file_path',
    ];

    protected $keyType = 'int';
}
