<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Http\Request;
use App\Jobs\ProcessLeadsBatch;
use App\Jobs\SendPostback;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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
            'name' => 'required|string|max:255',
            'webhook_url' => 'required|url',
            'csv_file' => 'required|file|max:51200',
        ]);

        $file = $request->file('csv_file');
        $csvErrors = $this->validateLeadsCsv($file->getRealPath());
        if ($csvErrors) {
            return back()->withErrors(['csv_file' => $csvErrors])->withInput();
        }

        $campaign = new Campaign();
        $campaign->name = $request->input('name');
        $campaign->sendState = 'Aguardando';
        $campaign->totalLeads = 0;
        $campaign->sendedLeads = 0;
        $campaign->webhook_url = $request->input('webhook_url');
        $campaign->save();

        // Disco privado: o CSV tem dados pessoais dos leads
        $path = $file->store('campaigns', 'local');

        $batchSize = 500;
        $leadsBatch = [];
        $headers = null;
        foreach ($this->readCsvRows(Storage::disk('local')->path($path)) as $row) {
            if ($headers === null) {
                $headers = $this->normalizeHeaders($row);
                continue;
            }
            $leadsBatch[] = array_combine($headers, $row);
            if (count($leadsBatch) == $batchSize) {
                ProcessLeadsBatch::dispatch($leadsBatch, $campaign->id);
                $leadsBatch = [];
            }
        }
        if (count($leadsBatch) > 0) {
            /*\Log::info("Leads processados do CSV: ", $leadsBatch);*/
            ProcessLeadsBatch::dispatch($leadsBatch, $campaign->id);
        }

        $campaign->totalLeads = Lead::where('campaign_id', $campaign->id)->count();
        $campaign->ftdLeads = Lead::where('campaign_id', $campaign->id)->where('ftd', true)->count();
        $campaign->save();

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
    public function deleteCampaign($id)
    {
        $campaign = Campaign::find($id);

        if (!$campaign) {
            return redirect()-route('campaigns.index')-with('error', 'Campanha não encontrada.');
        }

        Lead::where('campaign_id', $campaign->id)->delete();

        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campanha excluida com sucesso.');
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

        for ($batch = 0; $batch < $totalBatches; $batch++) {
            $batchStart = $batch * $request->postback_count;
            $batchLeads = $leads->slice($batchStart, $request->postback_count);

            foreach ($batchLeads as $lead) {
                $randomDelay = rand(0, $intervalInSeconds / $request->postback_count);
                $delayForThisLead = ($batch * $intervalInSeconds) + $randomDelay;
                SendPostback::dispatch($lead, $campaign->webhook_url)->delay(now()->addSeconds($delayForThisLead));
            }
        }

        return redirect()->route('campaigns.index')->with('message', 'Postbacks agendados');
    }

    /**
     * Confere o cabeçalho e a quantidade de colunas do CSV antes de criar a campanha.
     */
    private function validateLeadsCsv(string $path): array
    {
        $headers = null;
        $errors = [];
        $leads = 0;

        foreach ($this->readCsvRows($path) as $line => $row) {
            if ($headers === null) {
                $headers = $this->normalizeHeaders($row);
                $missing = array_diff(['name', 'phone', 'email'], $headers);
                if ($missing) {
                    return ['O cabeçalho precisa ter as colunas name, phone e email (ou Nome, Telefone e Email). Faltando: ' . implode(', ', $missing) . '.'];
                }
                continue;
            }

            $leads++;
            if (count($row) !== count($headers)) {
                $errors[] = "Linha {$line}: tem " . count($row) . ' colunas, mas o cabeçalho tem ' . count($headers) . '.';
                if (count($errors) === 5) {
                    $errors[] = 'Pode haver outras linhas com o mesmo problema.';
                    break;
                }
            }
        }

        if ($leads === 0) {
            return ['O arquivo não tem nenhum lead abaixo do cabeçalho.'];
        }

        return $errors;
    }

    /**
     * Nomes das colunas em minúsculas, aceitando o cabeçalho em português.
     */
    private function normalizeHeaders(array $headers): array
    {
        $aliases = ['nome' => 'name', 'telefone' => 'phone', 'e-mail' => 'email'];

        return array_map(function ($header) use ($aliases) {
            $header = mb_strtolower($header);
            return $aliases[$header] ?? $header;
        }, $headers);
    }

    /**
     * Lê o CSV linha a linha. Aceita vírgula ou ponto e vírgula, BOM e Windows-1252,
     * que é como o Excel em português salva.
     */
    private function readCsvRows(string $path): \Generator
    {
        $handle = fopen($path, 'r');
        $firstLine = (string) fgets($handle);
        rewind($handle);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $line = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if ($line === 1) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
            }
            if ($row === [null] || $row === ['']) {
                continue;
            }

            yield $line => array_map(function ($value) {
                $value = (string) $value;
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
                }
                return trim($value);
            }, $row);
        }

        fclose($handle);
    }
}