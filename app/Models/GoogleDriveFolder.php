<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleDriveFolder extends Model
{
    protected $fillable = [
        'folder_key',
        'folder_id',
        'name',
    ];
}
