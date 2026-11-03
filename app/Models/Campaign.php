<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
        'daily_start_time', 'daily_end_time',
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

    public function hasDailyWindow(): bool
    {
        return $this->daily_start_time && $this->daily_end_time;
    }

    /**
     * Se o horário (no fuso de exibição) está dentro da faixa diária. Aceita faixa que passa da meia-noite.
     */
    public function withinDailyWindow(?Carbon $at = null): bool
    {
        if (!$this->hasDailyWindow()) {
            return true;
        }

        $time = ($at ?? now())->copy()->setTimezone(config('app.display_timezone'))->format('H:i');
        $start = substr($this->daily_start_time, 0, 5);
        $end = substr($this->daily_end_time, 0, 5);

        return $start < $end ? $time >= $start && $time < $end : $time >= $start || $time < $end;
    }

    public function postbacksPerMinute(): float
    {
        return $this->postback_frequency === 'hour' ? $this->postback_count / 60 : (float) $this->postback_count;
    }
}
