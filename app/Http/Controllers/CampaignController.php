<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    /**
     * Visualização do dashboard, a view que apresenta as campanhas criadas e os tratados nela.
     */
    public function index()
    {
        $campaigns = Campaign::all();
        return view('campaigns.dashboard', compact('campaigns'));
    }

    /**
     * Retornar a view create que adiciona as campanhas na tabela.
     */
    public function create()
    {
        return view('campaigns.create');
    }

    /**
     * Criar uma nova campanha no banco de dados adicionando os leads na tabela Leads quando 
     * tiver o anexo csv em anexo.
     */
    public function campaignStore(Request $request)
    {
        $campaign = new Campaign();
        $campaign->name = $request->input('name');
        $campaign->sendState = 'aguardando';
        $campaign->totalLeads = 0;
        $campaign->sendedLeads = 0;
        
        if ($request->hasFile('csv_file')) {
            $file = $request->file('csv_file');
            $filename = time() . '.' . $file->getClientOriginalExtension();
            $path =$file->storeAs('campaigns', $filename, 'public');
        
            $campaign->save();
            
            $csv_data = array_map('str_getcsv', file(storage_path('app/public/' . $path)));
            $headers = array_shift($csv_data);

            foreach($csv_data as $row) {
                $lead_data = array_combine($headers, $row);

                $lead = new Lead();
                $lead->name = $lead_data['name'];
                $lead->phone = $lead_data['phone'];
                $lead->email = $lead_data['email'];
                $lead->campaign_id = $campaign->id;
                $lead->ftd = isset($lead_data['ftd']) && !empty($lead_data['ftd']);
                $lead->save();
            }

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
}
