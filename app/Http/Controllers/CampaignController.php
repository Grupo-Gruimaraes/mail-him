<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Http\Request;
use App\Jobs\ProcessLeadsBatch;
use App\Jobs\SendPostback;
use Illuminate\Support\Facades\Http;

class CampaignController extends Controller
{
    /**
     * Visualização do dashboard, a view que apresenta as campanhas criadas e os leads recebidos.
     * 
     * 
     */
    public function index()
    {
        $campaigns = Campaign::paginate(10);
        return view('campaigns.dashboard', compact('campaigns'));
    }

    /**
     * Retornar a view campaign.create que adiciona as novas campanhas na tabela.
     * 
     * 
     */
    public function create()
    {
        return view('campaigns.create');
    }

    /**
     * Criar uma nova campanha no banco de dados adicionando os leads na tabela Leads quando 
     * tiver o csv em anexo.
     * 
     * 
     */
    public function campaignStore(Request $request)
    {
        $request->validate([
            'webhook_url' => 'required|url',
        ]);

        $campaign = new Campaign();
        $campaign->name = $request->input('name');
        $campaign->sendState = 'aguardando';
        $campaign->totalLeads = 0;
        $campaign->sendedLeads = 0;
        $campaign->webhook_url = $request->input('webhook_url');
        $campaign->save();
        
        if ($request->hasFile('csv_file')) {
            $file = $request->file('csv_file');
            $filename = time() . '.' . $file->getClientOriginalExtension();
            $path =$file->storeAs('campaigns', $filename, 'public');
        
            
            $handle = fopen(storage_path('app/public/' . $path), 'r');
            $headers = fgetcsv($handle, 1000, ",");

            $batchSize = 500;
            $leadsBatch = [];
            while($data = fgetcsv($handle, 1000, ",")) {
                $leadsBatch[] = array_combine($headers, $data);
                if (count($leadsBatch) == $batchSize) {
                    ProcessLeadsBatch::dispatch($leadsBatch, $campaign->id);
                    $leadsBatch = [];
                }
            }
            if (count($leadsBatch) > 0) {
                \Log::info("Leads processados do CSV: ", $leadsBatch);
                ProcessLeadsBatch::dispatch($leadsBatch, $campaign->id);
            }

            $campaign->totalLeads = Lead::where('campaign_id', $campaign->id)->count();
            $campaign->ftdLeads = Lead::where('campaign_id', $campaign->id)->where('ftd', true)->count();
            $campaign->save();
        } else {
            $campaign->save();
        }
        
        return redirect()->route('campaigns.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function showPostbackCronForm($campaignId)
    {
        /*\Log::info("Recebido ID da campanha: " . $campaignId);*/

        $selectedCampaign = Campaign::find($campaignId);
        $campaigns = Campaign::all();
        if(!$selectedCampaign) {
            abort(404, 'Campanha não encontrada');
        }

        /*\Log::info("Campanha selecionada: " . $selectedCampaign->name);*/

        return view('campaigns.postback-cron', [
            'selectedCampaign' => $selectedCampaign,
            'campaigns' => $campaigns
        ]);
    }

    public function postbackCron(Request $request)
    {
        $campaign = Campaign::find($request->campaign_id);
        if (!$campaign) {
            /*\Log::error("Campanha não encontrada para o ID: " . $request->campaign_id);*/
            return response()->json(['error' => 'Campanha não encontrada'], 404);
        }

        $intervalInSeconds = ($request->postback_frequency == 'minute') ? 60 : 3600;

        $leads = $campaign->leads()->get();
        $totalLeads = count($leads);
        $totalBatches = ceil($totalLeads / $request->postback_count);

        $this->updateCampaignSendState($campaign->id);

        for ($batch = 0; $batch < $totalBatches; $batch++) {
            $batchStart = $batch * $request->postback_count;
            $batchLeads = $leads->slice($batchStart, $request->postback_count);

            foreach ($batchLeads as $index => $lead) {
                $randomDelay = rand(0, $intervalInSeconds / $request->postback_count);
                $delayForThisLead = ($batch * $intervalInSeconds) + ($index * ($intervalInSeconds / $request->postback_count)) + $randomDelay;
                SendPostback::dispatch($lead, $campaign->webhook_url)->delay(now()->addSeconds($delayForThisLead));
            }

            $this->updateCampaignLeads($campaign->id, count($batchLeads));

            $isFinalBatch = ($batch == $totalBatches - 1);
            $this->updateCampaignSendState($campaign->id, $isFinalBatch);

        }

        return redirect()->route('campaigns.index')->with('message', 'Postbacks agendados');
    }

    private function updateCampaignLeads($campaignId, $leadsCount)
    {
        $campaign = Campaign::find($campaignId);
        if($campaign) 
        {
            $campaign->sendedLeads += $leadsCount;
            $campaign->save();
        }
    }

    private function updateCampaignSendState($campaignId, $isFinalBatch = false)
    {
        $campaign = Campaign::find($campaignId);
        if($campaign)
        {
            if ($isFinalBatch)
            {
                $campaign->sendState = 'Finalizada';
            } else {
                $campaign->sendState = 'Enviando';
            }
            $campaign->save();
        } 
    }

    /*public function temporaryWebhook(Request $request) {
        \Log::info('Dados recebidos pelo webhook: ', $request->all());

        return response()->json(['message' => 'Postbacks iniciados']);
    }*/
}
