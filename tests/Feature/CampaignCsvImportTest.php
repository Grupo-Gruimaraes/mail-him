<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CampaignCsvImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function upload(string $csv)
    {
        return $this->actingAs(User::factory()->create())
            ->from('/campaigns-create')
            ->post('/campaigns-create-store', [
                'name' => 'Campanha teste',
                'webhook_url' => 'https://example.com/webhook',
                'csv_file' => UploadedFile::fake()->createWithContent('leads.csv', $csv),
            ]);
    }

    public function test_imports_comma_separated_csv(): void
    {
        $this->upload("name,phone,email\nMaria Silva,5511987654321,maria@example.com\n\"Ana Lima, Jr.\",5531976543210,ana@example.com\n")
            ->assertRedirect(route('campaigns.index'));

        $this->assertSame(['Maria Silva', 'Ana Lima, Jr.'], Lead::orderBy('id')->pluck('name')->all());
        $this->assertCount(1, Storage::disk('local')->files('campaigns'));
    }

    public function test_imports_excel_semicolon_csv_in_windows_1252(): void
    {
        $this->upload("name;phone;email\r\nJo\xE3o Souza;5521998765432;joao@example.com\r\n")
            ->assertRedirect(route('campaigns.index'));

        $this->assertSame('João Souza', Lead::first()->name);
    }

    public function test_imports_excel_utf8_csv_with_bom(): void
    {
        $this->upload("\xEF\xBB\xBFname,phone,email\nMaria Silva,5511987654321,maria@example.com\n")
            ->assertRedirect(route('campaigns.index'));

        $this->assertSame(1, Lead::count());
    }

    public function test_imports_csv_with_portuguese_header(): void
    {
        $this->upload("ID;Nome;E-mail;Telefone;\"External ID\"\r\n1;Maria Silva;maria@example.com;5511987654321;abc-1\r\n")
            ->assertRedirect(route('campaigns.index'));

        $lead = Lead::first();
        $this->assertSame(['Maria Silva', '5511987654321', 'maria@example.com'], [$lead->name, $lead->phone, $lead->email]);
    }

    public function test_rejects_csv_without_required_columns(): void
    {
        $this->upload("cliente,contato,email\nMaria,5511987654321,maria@example.com\n")
            ->assertRedirect('/campaigns-create')
            ->assertSessionHasErrors('csv_file');

        $this->assertSame(0, Campaign::count());
    }

    public function test_rejects_rows_with_wrong_column_count(): void
    {
        $this->upload("name,phone,email\nMaria,5511987654321,maria@example.com\nJoão,5521998765432\n")
            ->assertSessionHasErrors(['csv_file' => 'Linha 3: tem 2 colunas, mas o cabeçalho tem 3.']);

        $this->assertSame(0, Campaign::count());
    }
}
