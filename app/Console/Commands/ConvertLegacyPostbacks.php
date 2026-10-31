<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConvertLegacyPostbacks extends Command
{
    protected $signature = 'campaigns:convert-legacy-postbacks {--dry-run : Só mostra o que seria feito}';

    protected $description = 'Passa os postbacks agendados pelo fluxo antigo (um job por lead) para o disparador por minuto';

    public function handle(): int
    {
        $pending = [];
        $lastAvailableAt = [];
        $jobIds = [];

        DB::table('jobs')->where('payload', 'like', '%SendPostback%')->select('id', 'payload', 'available_at')
            ->chunkById(2000, function ($jobs) use (&$pending, &$lastAvailableAt, &$jobIds) {
                foreach ($jobs as $job) {
                    $payload = json_decode($job->payload);
                    $command = $payload->data->command ?? '';
                    if (($payload->displayName ?? null) !== 'App\Jobs\SendPostback'
                        || !preg_match('/App\\\\Models\\\\Lead";s:2:"id";i:(\d+);/', $command, $lead)
                        || !preg_match('/campaignId";i:(\d+);/', $command, $campaign)) {
                        continue;
                    }

                    $pending[$campaign[1]][$lead[1]] = true;
                    $lastAvailableAt[$campaign[1]] = max($lastAvailableAt[$campaign[1]] ?? 0, $job->available_at);
                    $jobIds[] = $job->id;
                }
            });

        foreach (Campaign::orderBy('id')->get() as $campaign) {
            $leadIds = array_keys($pending[$campaign->id] ?? []);

            // Nunca iniciada, ou já está no fluxo novo
            if (!$leadIds && ($campaign->sendState === Campaign::WAITING
                || $campaign->leads()->where('postback_status', '!=', 'pending')->exists())) {
                continue;
            }

            $changes = ['sendState' => Campaign::FINISHED, 'dispatch_credit' => 0];
            if ($leadIds) {
                // O ritmo não era salvo; sai da distribuição dos jobs que faltavam
                $perMinute = count($leadIds) / max(1, ($lastAvailableAt[$campaign->id] - time()) / 60);
                $changes = $perMinute >= 1
                    ? ['postback_frequency' => 'minute', 'postback_count' => (int) round($perMinute)]
                    : ['postback_frequency' => 'hour', 'postback_count' => max(1, (int) round($perMinute * 60))];
                $changes += ['sendState' => Campaign::SENDING, 'dispatch_credit' => 0];
            }

            $this->line(sprintf(
                '#%d %s: %d leads, %d ainda sem envio -> %s%s',
                $campaign->id,
                $campaign->name,
                $campaign->leads()->count(),
                count($leadIds),
                $changes['sendState'],
                $leadIds ? " ({$changes['postback_count']} por " . ($changes['postback_frequency'] === 'hour' ? 'hora' : 'minuto') . ')' : ''
            ));

            if ($this->option('dry-run')) {
                continue;
            }

            DB::transaction(function () use ($campaign, $leadIds, $changes) {
                $campaign->leads()->update(['postback_status' => 'sent']);
                foreach (array_chunk($leadIds, 1000) as $chunk) {
                    Lead::whereIn('id', $chunk)->update(['postback_status' => 'pending']);
                }
                $campaign->update($changes);
            });
        }

        if (!$this->option('dry-run')) {
            foreach (array_chunk($jobIds, 1000) as $chunk) {
                DB::table('jobs')->whereIn('id', $chunk)->delete();
            }
        }

        $this->info(count($jobIds) . ' jobs antigos ' . ($this->option('dry-run') ? 'seriam convertidos.' : 'convertidos.'));

        return self::SUCCESS;
    }
}
