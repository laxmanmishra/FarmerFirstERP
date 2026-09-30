<?php

namespace App\Livewire\Admin\Geography;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\ImportBatch;
use App\Services\AuditService;
use App\Services\Imports\GeographyImporter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Territory bulk import: Template → Upload → Validate → Preview → Error report → Confirm (SRS §6, v6.1 §12).
 */
#[Title('Import Geography')]
class Import extends Component
{
    use InteractsWithUi, WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    public ?int $batchId = null;

    public function mount(): void
    {
        $this->authorize('geography.import');
    }

    public function validateFile(GeographyImporter $importer): void
    {
        $this->authorize('geography.import');
        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240']], attributes: ['file' => __('file')]);

        $batch = $importer->validate($this->file, Auth::user());
        $this->batchId = $batch->id;
        $this->reset('file');

        $batch->status === ImportBatch::STATUS_VALIDATED
            ? $this->toast(__('File validated. Review the preview and confirm the import.'))
            : $this->toast(__('The file has errors. Nothing was imported.'), 'error');
    }

    public function confirm(GeographyImporter $importer, AuditService $audit): void
    {
        $this->authorize('geography.import');
        $batch = ImportBatch::query()->where('type', ImportBatch::TYPE_GEOGRAPHY)->findOrFail($this->batchId);

        if ($result = $this->attempt(fn () => $importer->import($batch))) {
            $audit->record('imported', 'geography', $result, newValues: $result->summary['created'] ?? []);
            $this->toast(__('Import complete.'));
        }
    }

    public function startOver(): void
    {
        $this->reset('file', 'batchId');
    }

    public function downloadTemplate(GeographyImporter $importer): StreamedResponse
    {
        $this->authorize('geography.import');

        return response()->streamDownload(fn () => print ($importer->templateCsv()), 'geography-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function render(): mixed
    {
        return view('livewire.admin.geography.import', [
            'batch' => $this->batchId ? ImportBatch::query()->find($this->batchId) : null,
            'history' => ImportBatch::query()->with('user:id,name')->where('type', ImportBatch::TYPE_GEOGRAPHY)->latest('id')->limit(10)->get(),
            'columns' => GeographyImporter::COLUMNS,
        ]);
    }
}
