<?php

namespace App\Jobs;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;

class SendPostback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $lead;
    protected $webhookUrl;
    protected $campaignId;

    /**
     * Create a new job instance.
     */
    public function __construct(Lead $lead, $webhookUrl)
    {
        $this->lead = $lead;
        $this->webhookUrl = $webhookUrl;
        $this->campaignId = $lead->campaign_id;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        /*\Log::info("Executando SendPostback para lead: " . json_encode($this->lead));*/
        $response = Http::post($this->webhookUrl, [
            'name' => $this->lead->name,
            'email' => $this->lead->email,
            'phone' => $this->lead->phone,
        ]);
    
        if ($response->successful()) {
            $campaign = Campaign::find($this->campaignId);
            if ($campaign) {
                \DB::transaction(function () use ($campaign) {
                    if(!$campaign->isProcessing) {
                        $campaign->update([
                            'isProcessing' => true,
                            'sendState' => 'Enviando',
                        ]);
                    }

                    $campaign->increment('sendedLeads');
                    $campaign->increment('processedLeadsCount');

                    if($campaign->processedLeadsCount == $campaign->leads()->count()) {
                        $campaign->update(['sendState' => 'Finalizado', 'isProcessing' => false]);
                    }

                    $campaign->save();
                });

                /*\Log::info("sendedLeads e processedLeadsCount atualizados para a campanha ID: {$this->campaignId}");*/
            } else {
                /*\Log::errror("Campanha não encontrada para o ID: {$this->campaignId}");*/
            }
        } else {
            /*\Log::error("Falha no envio do postback para o lead: ". json_encode($this->lead));*/
        }
    }
}