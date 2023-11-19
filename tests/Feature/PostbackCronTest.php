<?php
namespace Tests\Feature;

use App\Http\Controllers\CampaignController;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SendPostback;

class PostbackCronTest extends TestCase
{
    public function testarEnfileiramento() 
    {
        Queue::fake();

        $request = new Request([
            'campaign_id' => 14,
            'postback_frequency' => 'minute',
            'postback_count' => 14,
        ]);
        (new CampaignController())->postbackCron($request);

        Queue::assertPushed(SendPostback::class, 14);
    }

    public function testarExecucaoDaFila()
    {
        Queue::fake();
        
        $request = new Request([
            'campaign_id' => 14,
            'postback_frequency' => 'minute',
            'postback_count' => 14,
        ]);

        (new CampaignController())->postbackCron($request);

        Queue::assertPushed(SendPostback::class, function ($job) {
            $job->handle();
            return true;
        });
    }
}