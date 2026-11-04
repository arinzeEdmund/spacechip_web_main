<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentRecord extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['granted' => 'bool', 'meta' => 'array', 'created_at' => 'datetime'];
}
