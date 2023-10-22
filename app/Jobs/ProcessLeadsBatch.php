<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessLeadsBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $leadsBatch;
    /**
     * Cria uma novão instância de job.
     * 
     * @param array $leadsBatch
     * @return void
     */
    public function __construct(array $leadsBatch)
    {
        $this->leadsBatch = $leadsBatch;
    }

    /**
     * Execute o job.
     * 
     * @return void
     */
    public function handle()
    {
        foreach ($this->leadsBatch as $leadData) {
            $name = $leadData['name'];
            $phone = $leadData['phone'];
            $email = $leadData['email'];

            $lead = new Lead();
            $lead->name = $name;
            $lead->phone = $phone;
            $lead->email = $email;
            $lead->save();
        }
    }
}
