<?php

namespace App\Console\Commands;

use App\Jobs\SendLeadPostback;
use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Console\Command;

class DispatchPostbacks extends Command
{
    protected $signature = 'campaigns:dispatch-postbacks';

    protected $description = 'Coloca na fila os postbacks do minuto de cada campanha ativa';

    /**
     * Roda a cada minuto pelo scheduler e respeita o ritmo, o agendamento e a pausa de cada campanha.
     */
    public function handle(): void
    {
        $now = now();

        Campaign::whereIn('sendState', [Campaign::SCHEDULED, Campaign::SENDING])->each(function (Campaign $campaign) use ($now) {
            if ($campaign->ends_at && $now->gte($campaign->ends_at)) {
                $campaign->update(['sendState' => Campaign::ENDED, 'dispatch_credit' => 0]);
                return;
            }

            if ($campaign->starts_at && $now->lt($campaign->starts_at)) {
                $campaign->update(['sendState' => Campaign::SCHEDULED]);
                return;
            }

            $perMinute = $campaign->postbacksPerMinute();
            $credit = $campaign->dispatch_credit + $perMinute;
            $queued = $campaign->leads()->where('postback_status', 'queued')->count();
            // Se os workers não estiverem dando conta do ritmo, espera a fila baixar em vez de acumular
            $quota = min((int) floor($credit), max(0, (int) ceil($perMinute * 2) + 10 - $queued));

            $leadIds = $campaign->leads()->where('postback_status', 'pending')->orderBy('id')->limit($quota)->pluck('id');

            if ($leadIds->isEmpty() && $queued === 0 && !$campaign->leads()->where('postback_status', 'pending')->exists()) {
                $campaign->update(['sendState' => Campaign::FINISHED, 'dispatch_credit' => 0]);
                return;
            }

            Lead::whereIn('id', $leadIds)->update(['postback_status' => 'queued', 'queued_at' => $now]);
            $campaign->update([
                'sendState' => Campaign::SENDING,
                'dispatch_credit' => min($credit - $leadIds->count(), max(1, $perMinute)),
            ]);

            foreach ($leadIds as $leadId) {
                SendLeadPostback::dispatch($leadId, $campaign->id)->delay($now->copy()->addSeconds(random_int(0, 59)));
            }
        });
    }
}
