<?php

namespace Tests\Feature\Crm;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Admin\Geography\Import;
use App\Models\District;
use App\Models\ImportBatch;
use App\Models\Village;
use App\Services\Imports\GeographyImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Tests\TestCase;

class GeographyImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seedReferenceData();
    }

    public function test_valid_csv_is_previewed_then_imported(): void
    {
        $owner = $this->userWithRole('Owner');
        $csv = "state,district,tehsil,village,pin_code\nMadhya Pradesh,Sehore,Sehore,Bilkisganj,466001\nMadhya Pradesh,Vidisha,Basoda,Pathari,464221\nmadhya pradesh , Vidisha , Basoda , Tyonda ,\n";

        $batch = app(GeographyImporter::class)->validate(UploadedFile::fake()->createWithContent('villages.csv', $csv), $owner);

        $this->assertSame(ImportBatch::STATUS_VALIDATED, $batch->status);
        $this->assertEquals(['new_villages' => 2, 'existing_villages' => 1, 'districts_touched' => 1, 'tehsils_touched' => 1], $batch->summary);
        $this->assertNull(District::query()->where('name', 'Vidisha')->first(), 'Validation must not write data.');

        app(GeographyImporter::class)->import($batch);

        $this->assertSame(ImportBatch::STATUS_IMPORTED, $batch->fresh()->status);
        $this->assertSame(2, Village::query()->whereHas('tehsil', fn ($query) => $query->where('name', 'Basoda'))->count());
        $this->assertEquals(['districts' => 1, 'tehsils' => 1, 'villages' => 2], $batch->fresh()->summary['created']);
    }

    public function test_any_error_blocks_the_whole_file(): void
    {
        $csv = "state,district,tehsil,village,pin_code\nMadhya Pradesh,Vidisha,Basoda,Pathari,464221\nAtlantis,Nowhere,X,Y,\nMadhya Pradesh,Vidisha,Basoda,Pathari,\nMadhya Pradesh,Vidisha,,Z,12\n";

        $batch = app(GeographyImporter::class)->validate(UploadedFile::fake()->createWithContent('bad.csv', $csv), $this->userWithRole('Owner'));

        $this->assertSame(ImportBatch::STATUS_FAILED, $batch->status);
        $messages = collect($batch->errors)->map(fn ($error) => $error['row'].':'.$error['column'])->all();
        $this->assertContains('3:state', $messages);
        $this->assertContains('4:village', $messages);
        $this->assertContains('5:tehsil', $messages);
        $this->assertContains('5:pin_code', $messages);

        $this->expectException(BusinessRuleException::class);
        app(GeographyImporter::class)->import($batch);
    }

    public function test_xlsx_files_are_supported(): void
    {
        $path = storage_path('framework/testing/geo.xlsx');
        @mkdir(dirname($path), 0777, true);
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['State', 'District', 'Tehsil', 'Village', 'PIN Code']));
        $writer->addRow(Row::fromValues(['Madhya Pradesh', 'Bhopal', 'Berasia', 'Nazirabad', '463106']));
        $writer->close();

        $batch = app(GeographyImporter::class)->validate(new UploadedFile($path, 'geo.xlsx', null, null, true), $this->userWithRole('Owner'));
        app(GeographyImporter::class)->import($batch);

        $this->assertSame('463106', Village::query()->where('name', 'Nazirabad')->value('pin_code'));
    }

    public function test_import_screen_requires_permission_and_runs_the_flow(): void
    {
        $this->actingAs($this->userWithPermissions(['geography.view']))->get(route('admin.geography.import'))->assertForbidden();

        Livewire::actingAs($this->userWithRole('Owner'))->test(Import::class)
            ->set('file', UploadedFile::fake()->createWithContent('v.csv', "state,district,tehsil,village\nMadhya Pradesh,Harda,Timarni,Rahatgaon\n"))
            ->call('validateFile')
            ->assertSee(__('3. Confirm import'))
            ->call('confirm')
            ->assertSee(__('Imported'));

        $this->assertTrue(Village::query()->where('name', 'Rahatgaon')->exists());
        $this->assertDatabaseHas('audit_logs', ['module' => 'geography', 'event' => 'imported']);
    }
}
