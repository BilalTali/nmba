<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    protected $fillable = ['id', 'name', 'slug', 'district_id'];
    public $incrementing = false; // critical — we manage IDs manually
    protected $keyType = 'int';
}
