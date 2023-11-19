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
        \Log::info("Processando batch de leads para a campanha: " . $this->campaign_id);

        $processedLeads = [];
        foreach ($this->leads as $leadData) {
            $lead = Lead::create([
                'name' => $leadData['name'],
                'phone' => $leadData['phone'],
                'email' => $leadData['email'],
                'campaign_id' => $this->campaign_id,
            ]);

            $processedLeads[] = $lead->toArray();
        }

        $campaign = Campaign::find($this->campaign_id);

        if ($campaign) {
            $currentLeads = json_decode($campaign->leads, true) ?? [];
            $updatedLeads = array_merge($currentLeads, $processedLeads);
            $campaign->leads = json_encode($updatedLeads);
            $campaign->totalLeads += count ($processedLeads);
            $campaign->save();
        }
    }
}
