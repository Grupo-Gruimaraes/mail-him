<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    public const WAITING = 'Aguardando';
    public const SCHEDULED = 'Agendado';
    public const SENDING = 'Enviando';
    public const PAUSED = 'Pausado';
    public const ENDED = 'Encerrado';
    public const FINISHED = 'Finalizado';

    protected $fillable = [
        'name', 'totalLeads', 'isProcessing', 'sendState', 'status', 'sendedLeads', 'processedLeadsCount',
        'webhook_url', 'postback_frequency', 'postback_count', 'starts_at', 'ends_at', 'dispatch_credit',
    ];

    protected $casts = [
        'isProcessing' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'dispatch_credit' => 'float',
    ];


    use HasFactory;

    public function leads()
    {

        return $this->hasMany(Lead::class);
    }

    public function isActive(): bool
    {
        return in_array($this->sendState, [self::SCHEDULED, self::SENDING]);
    }

    public function postbacksPerMinute(): float
    {
        return $this->postback_frequency === 'hour' ? $this->postback_count / 60 : (float) $this->postback_count;
    }
}
