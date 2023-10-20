<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'campaign_id',
        'ftd'
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}