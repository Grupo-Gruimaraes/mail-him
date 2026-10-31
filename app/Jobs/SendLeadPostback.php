<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendLeadPostback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Sem nova tentativa automática: repetir um postback que talvez já tenha chegado duplica o envio.
     */
    public $tries = 1;

    public $timeout = 30;

    public function __construct(public int $leadId, public int $campaignId)
    {
    }

    /**
     * Envia o postback de um lead que o disparador colocou na fila.
     */
    public function handle(): void
    {
        $campaign = Campaign::find($this->campaignId);
        $lead = Lead::find($this->leadId);
        if (!$campaign || !$lead || $lead->postback_status !== 'queued') {
            return;
        }

        // Pausada ou encerrada depois de entrar na fila: o lead volta a esperar a vez
        if ($campaign->sendState !== Campaign::SENDING) {
            $lead->update(['postback_status' => 'pending', 'queued_at' => null]);
            return;
        }

        $response = Http::timeout(15)->post($campaign->webhook_url, [
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
        ]);

        if (!$response->successful()) {
            $lead->update(['postback_status' => 'failed']);
            return;
        }

        $lead->update(['postback_status' => 'sent', 'sent_at' => now()]);
        Campaign::whereKey($this->campaignId)->increment('sendedLeads');
    }

    public function failed(?Throwable $exception): void
    {
        Lead::whereKey($this->leadId)->update(['postback_status' => 'failed']);
    }
}
