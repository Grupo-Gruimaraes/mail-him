<?php

namespace App\Jobs;

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

    /**
     * Create a new job instance.
     */
    public function __construct(Lead $lead, $webhookUrl)
    {
        $this->lead = $lead;
        $this->webhookUrl = $webhookUrl;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        \Log::info("Executando SendPostback para lead: " . json_encode($this->lead));
        $webhookUrl = $this->lead->campaign->webhook_url;
        $data = [
            'name' => $this->lead->name,
            'email' => $this->lead->email,
            'phone' => $this->lead->phone,
        ];

        $response = Http::post($webhookUrl, $data);

        if (!$response->successful()) {
            
        }
    }
}
