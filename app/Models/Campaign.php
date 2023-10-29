<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Campaign extends Model
{
    use HasFactory;

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }
}