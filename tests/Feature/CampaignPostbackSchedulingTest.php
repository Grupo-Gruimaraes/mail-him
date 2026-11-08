<?php

namespace Tests\Feature;

use App\Jobs\SendLeadPostback;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignPostbackSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function campaign(array $attributes = [], int $leads = 5): Campaign
    {
        $campaign = Campaign::create($attributes + [
            'name' => 'Campanha teste',
            'sendState' => Campaign::WAITING,
            'totalLeads' => $leads,
            'sendedLeads' => 0,
            'webhook_url' => 'https://example.com/webhook',
        ]);

        for ($i = 1; $i <= $leads; $i++) {
            $campaign->leads()->create(['name' => "Lead {$i}", 'phone' => "551190000000{$i}", 'email' => "lead{$i}@example.com"]);
        }

        return $campaign;
    }

    private function start(Campaign $campaign, array $data = [])
    {
        return $this->actingAs(User::factory()->create())
            ->from('/campaigns-postback-cron-form/' . $campaign->id)
            ->post('/campaigns-postback-cron', $data + [
                'campaign_id' => $campaign->id,
                'postback_frequency' => 'minute',
                'postback_count' => 2,
            ]);
    }

    public function test_start_saves_settings_without_queueing_every_lead(): void
    {
        Queue::fake();
        $campaign = $this->campaign();

        $this->start($campaign)->assertRedirect(route('campaigns.index'));

        Queue::assertNothingPushed();
        $campaign->refresh();
        $this->assertSame(Campaign::SENDING, $campaign->sendState);
        $this->assertSame(2, $campaign->postback_count);
    }

    public function test_start_in_the_future_is_scheduled_in_brazil_time(): void
    {
        Carbon::setTestNow('2026-10-31 15:00:00');
        $campaign = $this->campaign();

        $this->start($campaign, ['starts_at' => '2026-10-31T13:00', 'ends_at' => '2026-10-31T18:00']);

        $campaign->refresh();
        $this->assertSame(Campaign::SCHEDULED, $campaign->sendState);
        $this->assertSame('2026-10-31 16:00:00', $campaign->starts_at->utc()->toDateTimeString());
        $this->assertSame('2026-10-31 21:00:00', $campaign->ends_at->utc()->toDateTimeString());
    }

    public function test_rejects_end_before_start(): void
    {
        $campaign = $this->campaign();

        $this->start($campaign, ['starts_at' => '2030-01-02T10:00', 'ends_at' => '2030-01-02T09:00'])
            ->assertSessionHasErrors('ends_at');

        $this->assertSame(Campaign::WAITING, $campaign->fresh()->sendState);
    }

    public function test_start_saves_custom_daily_window(): void
    {
        $campaign = $this->campaign();

        $this->start($campaign, ['daily_start_time' => '08:00', 'daily_end_time' => '23:00'])->assertRedirect(route('campaigns.index'));

        $campaign->refresh();
        $this->assertSame('08:00', substr($campaign->daily_start_time, 0, 5));
        $this->assertSame('23:00', substr($campaign->daily_end_time, 0, 5));
    }

    public function test_daily_window_needs_two_different_times(): void
    {
        $campaign = $this->campaign();

        $this->start($campaign, ['daily_start_time' => '08:00'])->assertSessionHasErrors('daily_end_time');
        $this->start($campaign, ['daily_start_time' => '08:00', 'daily_end_time' => '08:00'])->assertSessionHasErrors('daily_end_time');

        $this->assertSame(Campaign::WAITING, $campaign->fresh()->sendState);
    }

    public function test_dispatcher_only_sends_inside_the_daily_window(): void
    {
        Queue::fake();
        $campaign = $this->campaign([
            'sendState' => Campaign::SENDING, 'postback_frequency' => 'minute', 'postback_count' => 2,
            'daily_start_time' => '08:00', 'daily_end_time' => '23:00',
        ]);

        Carbon::setTestNow('2026-11-03 05:30:00'); // 02:30 em Brasília
        $this->artisan('campaigns:dispatch-postbacks');
        Queue::assertNothingPushed();
        $this->assertSame(0.0, $campaign->fresh()->dispatch_credit);

        Carbon::setTestNow('2026-11-03 15:00:00'); // 12:00 em Brasília
        $this->artisan('campaigns:dispatch-postbacks');
        Queue::assertPushed(SendLeadPostback::class, 2);
    }

    public function test_daily_window_can_cross_midnight(): void
    {
        $campaign = new Campaign(['daily_start_time' => '22:00:00', 'daily_end_time' => '06:00:00']);

        $this->assertTrue($campaign->withinDailyWindow(Carbon::parse('2026-11-04 02:30:00'))); // 23:30 em Brasília
        $this->assertTrue($campaign->withinDailyWindow(Carbon::parse('2026-11-04 08:59:00'))); // 05:59
        $this->assertFalse($campaign->withinDailyWindow(Carbon::parse('2026-11-04 09:00:00'))); // 06:00
        $this->assertFalse($campaign->withinDailyWindow(Carbon::parse('2026-11-04 15:00:00'))); // 12:00
    }

    public function test_queued_postback_outside_daily_window_goes_back_to_pending(): void
    {
        Http::fake();
        Carbon::setTestNow('2026-11-03 05:30:00'); // 02:30 em Brasília
        $campaign = $this->campaign(['sendState' => Campaign::SENDING, 'daily_start_time' => '08:00', 'daily_end_time' => '23:00'], 1);
        $lead = $campaign->leads()->first();
        $lead->update(['postback_status' => 'queued', 'queued_at' => now()]);

        (new SendLeadPostback($lead->id, $campaign->id))->handle();

        Http::assertNothingSent();
        $this->assertSame('pending', $lead->fresh()->postback_status);
    }

    public function test_dispatcher_queues_only_the_rate_of_the_minute(): void
    {
        Queue::fake();
        $campaign = $this->campaign(['sendState' => Campaign::SENDING, 'postback_frequency' => 'minute', 'postback_count' => 2]);

        $this->artisan('campaigns:dispatch-postbacks')->assertSuccessful();

        Queue::assertPushed(SendLeadPostback::class, 2);
        $this->assertSame(2, $campaign->leads()->where('postback_status', 'queued')->count());
        $this->assertSame(3, $campaign->leads()->where('postback_status', 'pending')->count());
    }

    public function test_hourly_rate_accumulates_between_minutes(): void
    {
        Queue::fake();
        $this->campaign(['sendState' => Campaign::SENDING, 'postback_frequency' => 'hour', 'postback_count' => 30]);

        $this->artisan('campaigns:dispatch-postbacks');
        Queue::assertPushed(SendLeadPostback::class, 0);

        $this->artisan('campaigns:dispatch-postbacks');
        Queue::assertPushed(SendLeadPostback::class, 1);
    }

    public function test_dispatcher_respects_start_and_end(): void
    {
        Queue::fake();
        $future = $this->campaign(['sendState' => Campaign::SENDING, 'postback_frequency' => 'minute', 'postback_count' => 5, 'starts_at' => now()->addHour()]);
        $past = $this->campaign(['sendState' => Campaign::SENDING, 'postback_frequency' => 'minute', 'postback_count' => 5, 'ends_at' => now()->subMinute()]);

        $this->artisan('campaigns:dispatch-postbacks');

        Queue::assertNothingPushed();
        $this->assertSame(Campaign::SCHEDULED, $future->fresh()->sendState);
        $this->assertSame(Campaign::ENDED, $past->fresh()->sendState);
    }

    public function test_dispatcher_finishes_when_every_lead_is_done(): void
    {
        $campaign = $this->campaign(['sendState' => Campaign::SENDING, 'postback_frequency' => 'minute', 'postback_count' => 5], 2);
        $campaign->leads()->update(['postback_status' => 'sent', 'sent_at' => now()]);

        $this->artisan('campaigns:dispatch-postbacks');

        $this->assertSame(Campaign::FINISHED, $campaign->fresh()->sendState);
    }

    public function test_pause_and_resume(): void
    {
        $campaign = $this->campaign(['sendState' => Campaign::SENDING, 'postback_frequency' => 'minute', 'postback_count' => 2]);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/campaigns-pause/' . $campaign->id)->assertRedirect(route('campaigns.index'));
        $this->assertSame(Campaign::PAUSED, $campaign->fresh()->sendState);

        Queue::fake();
        $this->artisan('campaigns:dispatch-postbacks');
        Queue::assertNothingPushed();

        $this->actingAs($user)->post('/campaigns-resume/' . $campaign->id);
        $this->assertSame(Campaign::SENDING, $campaign->fresh()->sendState);
    }

    public function test_queued_postback_of_paused_campaign_goes_back_to_pending(): void
    {
        Http::fake();
        $campaign = $this->campaign(['sendState' => Campaign::PAUSED], 1);
        $lead = $campaign->leads()->first();
        $lead->update(['postback_status' => 'queued', 'queued_at' => now()]);

        (new SendLeadPostback($lead->id, $campaign->id))->handle();

        Http::assertNothingSent();
        $this->assertSame('pending', $lead->fresh()->postback_status);
    }

    public function test_postback_is_sent_only_once(): void
    {
        Http::fake(['*' => Http::response('ok')]);
        $campaign = $this->campaign(['sendState' => Campaign::SENDING], 1);
        $lead = $campaign->leads()->first();
        $lead->update(['postback_status' => 'queued', 'queued_at' => now()]);

        (new SendLeadPostback($lead->id, $campaign->id))->handle();
        (new SendLeadPostback($lead->id, $campaign->id))->handle();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['email'] === 'lead1@example.com');
        $this->assertSame('sent', $lead->fresh()->postback_status);
        $this->assertNotNull($lead->fresh()->sent_at);
        $this->assertSame(1, $campaign->fresh()->sendedLeads);
    }

    public function test_failed_postback_is_marked_and_not_counted(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);
        $campaign = $this->campaign(['sendState' => Campaign::SENDING], 1);
        $lead = $campaign->leads()->first();
        $lead->update(['postback_status' => 'queued', 'queued_at' => now()]);

        (new SendLeadPostback($lead->id, $campaign->id))->handle();

        $this->assertSame('failed', $lead->fresh()->postback_status);
        $this->assertSame(0, $campaign->fresh()->sendedLeads);
    }

    public function test_dashboard_shows_last_24h_and_last_send(): void
    {
        Carbon::setTestNow('2026-10-31 15:00:00');
        $campaign = $this->campaign([
            'sendState' => Campaign::SENDING, 'postback_frequency' => 'hour', 'postback_count' => 120,
            'daily_start_time' => '08:00', 'daily_end_time' => '10:00',
        ], 3);
        $leads = $campaign->leads()->orderBy('id')->get();
        $leads[0]->update(['postback_status' => 'sent', 'sent_at' => now()->subMinutes(30)]);
        $leads[1]->update(['postback_status' => 'sent', 'sent_at' => now()->subDays(2)]);

        $this->actingAs(User::factory()->create())->get('/campaigns')
            ->assertOk()
            ->assertSeeInOrder(['Leads restantes:', '1', 'Envios últimas 24h:', '1', 'Último envio:', '31/10/2026 11:30'])
            ->assertSee('120 por hora')
            ->assertSeeText('Faixa diária: 08:00 às 10:00')
            ->assertSee('fora do horário agora')
            ->assertSee('Pausar');
    }

    public function test_finished_campaign_with_new_leads_can_be_started_again(): void
    {
        $campaign = $this->campaign(['sendState' => Campaign::FINISHED], 2);
        $campaign->leads()->orderBy('id')->first()->update(['postback_status' => 'sent', 'sent_at' => now()]);

        $this->actingAs(User::factory()->create())->get('/campaigns')
            ->assertSeeText('Leads restantes: 1')
            ->assertSee('/campaigns-postback-cron-form/' . $campaign->id, false)
            ->assertSee('Iniciar');
    }

    public function test_converts_legacy_jobs_keeping_unsent_leads(): void
    {
        $campaign = $this->campaign(['sendState' => Campaign::SENDING, 'totalLeads' => 99], 4);
        $campaign->leads()->create(['name' => 'Lead 4', 'phone' => '5511900000004', 'email' => 'lead4@example.com']);
        [$sent, $pendingA, $pendingB, $neverQueued, $repeated] = $campaign->leads()->orderBy('id')->pluck('id')->all();
        foreach ([$pendingA, $pendingB, $pendingA] as $leadId) {
            $command = 'O:22:"App\Jobs\SendPostback":3:{s:7:"' . "\0*\0" . 'lead";O:45:"Illuminate\Contracts\Database\ModelIdentifier":5:{s:5:"class";s:15:"App\Models\Lead";s:2:"id";i:' . $leadId
                . ';s:9:"relations";a:0:{}s:10:"connection";s:5:"mysql";s:15:"collectionClass";N;}s:13:"' . "\0*\0" . 'webhookUrl";s:27:"https://example.com/webhook";s:13:"' . "\0*\0" . 'campaignId";i:' . $campaign->id . ';}';
            DB::table('jobs')->insert([
                'queue' => 'default',
                'payload' => json_encode(['displayName' => 'App\Jobs\SendPostback', 'data' => ['commandName' => 'App\Jobs\SendPostback', 'command' => $command]]),
                'attempts' => 0,
                'available_at' => time() + 600,
                'created_at' => time(),
            ]);
        }

        $this->artisan('campaigns:convert-legacy-postbacks', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(3, DB::table('jobs')->count());

        $this->artisan('campaigns:convert-legacy-postbacks', ['--skip-duplicates' => true])->assertSuccessful();

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame('sent', Lead::find($sent)->postback_status);
        $this->assertSame(['pending', 'pending'], Lead::whereIn('id', [$pendingA, $pendingB])->pluck('postback_status')->all());
        $this->assertSame('pending', Lead::find($neverQueued)->postback_status);
        $this->assertSame('skipped', Lead::find($repeated)->postback_status);
        $campaign->refresh();
        $this->assertSame(Campaign::SENDING, $campaign->sendState);
        $this->assertSame('hour', $campaign->postback_frequency);
        $this->assertSame(12, $campaign->postback_count);
        $this->assertSame(4, $campaign->totalLeads);
    }
}
