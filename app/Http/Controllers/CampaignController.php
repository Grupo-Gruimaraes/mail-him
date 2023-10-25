<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Http\Request;
use App\Jobs\ProcessLeadsBatch;
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
        $campaigns = Campaign::all();
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
        $campaign = new Campaign();
        $campaign->name = $request->input('name');
        $campaign->sendState = 'aguardando';
        $campaign->totalLeads = 0;
        $campaign->sendedLeads = 0;
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

    public function showPostbackCronForm()
    {
        $campaigns = Campaign::all();
        return view('campaigns.postback-cron', ['campaigns' => $campaigns]);
    }

    public function postbackCron(Request $request)
    {
        $campaign = Campaign::find($request->campaign_id);
        $interval = 0;

        if ($request->postback_frequency == 'minute') {
            $interval = 60 / $request->postback_count;
        } elseif ($request->postback_frequency == 'hour') {
            $interval = 3600 / $request->postback_count;
        }

        $leads = $campaign->leads;
        foreach ($leads as $lead) {
            $data = [
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
            ];

            $webhookUrl = "URL_DO_WEBHOOK";
            $response = Http::post($webhookUrl, $data);

            if (!$response->successful()) {

            }

            sleep($interval);
        }

        return redirect()->route('campaigns.index')->with('message', 'Postbacks iniciados');
    }
}
