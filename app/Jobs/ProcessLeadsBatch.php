<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessLeadsBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $leads;
    protected $campaign_id;
    /**
     * Cria uma novão instância de job.
     * 
     * @param array $leadsBatch
     * @param integer $campaign_id
     * @return void
     */
    public function __construct(array $leads, $campaign_id)
    {
        $this->leads = $leads;
        $this->campaign_id = $campaign_id;
    }

    /**
     * Execute o job.
     * 
     * @return void
     */
    public function handle()
    {
        /*\Log::info("Processando lotes de leads para a campanha: " . $this->campaign_id);*/

        if (!Campaign::whereKey($this->campaign_id)->exists()) {
            return;
        }

        $now = now();
        $rows = array_map(fn ($leadData) => [
            'name' => $leadData['name'],
            'phone' => $leadData['phone'],
            'email' => $leadData['email'],
            'campaign_id' => $this->campaign_id,
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->leads);

        // Leads e contador na mesma transação: se o job for repetido, não duplica leads
        DB::transaction(function () use ($rows) {
            Lead::insert($rows);
            Campaign::whereKey($this->campaign_id)->increment('totalLeads', count($rows));
        });
    }
}
