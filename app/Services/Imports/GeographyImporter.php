<?php

namespace App\Services\Imports;

use App\Exceptions\BusinessRuleException;
use App\Models\District;
use App\Models\ImportBatch;
use App\Models\State;
use App\Models\Tehsil;
use App\Models\User;
use App\Models\Village;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Territory bulk import (SRS §6, v6.1 §12): Template → Upload → Validate → Preview →
 * Error Report → Confirm. Nothing is written unless every row is valid, and the
 * confirmed import runs in one transaction. Existing districts/tehsils/villages are
 * reused, so re-importing the same file is harmless.
 *
 * Columns: state, district, tehsil, village, pin_code (optional), village_code (optional).
 */
class GeographyImporter
{
    public const COLUMNS = ['state', 'district', 'tehsil', 'village', 'pin_code', 'village_code'];

    private const REQUIRED = ['state', 'district', 'tehsil', 'village'];

    public const MAX_ROWS = 20000;

    public function validate(UploadedFile $file, User $user): ImportBatch
    {
        $path = $file->store('imports/geography', 'local');
        $rows = $this->readRows(Storage::disk('local')->path($path), strtolower($file->getClientOriginalExtension()));
        [$errors, $summary] = $this->analyse($rows);

        return ImportBatch::create([
            'type' => ImportBatch::TYPE_GEOGRAPHY,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 250),
            'stored_path' => $path,
            'status' => $errors === [] ? ImportBatch::STATUS_VALIDATED : ImportBatch::STATUS_FAILED,
            'total_rows' => count($rows),
            'error_rows' => count(array_unique(array_column($errors, 'row'))),
            'errors' => array_slice($errors, 0, 500),
            'summary' => $summary,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Imports a previously validated batch. Re-reads and re-validates the stored file so
     * data that changed since the preview cannot slip through.
     */
    public function import(ImportBatch $batch): ImportBatch
    {
        if ($batch->status !== ImportBatch::STATUS_VALIDATED) {
            throw new BusinessRuleException(__('Only a validated import without errors can be confirmed.'), 'import_not_validated');
        }

        $rows = $this->readRows(Storage::disk('local')->path($batch->stored_path), strtolower(pathinfo($batch->stored_path, PATHINFO_EXTENSION)));
        [$errors] = $this->analyse($rows);

        if ($errors !== []) {
            $batch->update(['status' => ImportBatch::STATUS_FAILED, 'errors' => array_slice($errors, 0, 500), 'error_rows' => count($errors)]);

            throw new BusinessRuleException(__('The data changed since validation and now has errors. Review the error report.'), 'import_revalidation_failed');
        }

        $created = ['districts' => 0, 'tehsils' => 0, 'villages' => 0];

        DB::transaction(function () use ($rows, &$created): void {
            $states = State::query()->get()->keyBy(fn (State $state) => $this->key($state->name));
            $districtCache = [];
            $tehsilCache = [];

            foreach ($rows as $row) {
                $state = $states[$this->key($row['state'])];

                $districtKey = $state->id.'|'.$this->key($row['district']);
                $district = $districtCache[$districtKey] ??= District::query()->where('state_id', $state->id)->where('name', $this->clean($row['district']))->first()
                    ?? tap(District::create(['state_id' => $state->id, 'name' => $this->clean($row['district'])]), function () use (&$created): void {
                        $created['districts']++;
                    });

                $tehsilKey = $district->id.'|'.$this->key($row['tehsil']);
                $tehsil = $tehsilCache[$tehsilKey] ??= Tehsil::query()->where('district_id', $district->id)->where('name', $this->clean($row['tehsil']))->first()
                    ?? tap(Tehsil::create(['district_id' => $district->id, 'name' => $this->clean($row['tehsil'])]), function () use (&$created): void {
                        $created['tehsils']++;
                    });

                $exists = Village::query()->where('tehsil_id', $tehsil->id)->where('name', $this->clean($row['village']))->exists();

                if (! $exists) {
                    Village::create([
                        'tehsil_id' => $tehsil->id,
                        'name' => $this->clean($row['village']),
                        'pin_code' => $this->clean($row['pin_code'] ?? '') ?: null,
                        'code' => $this->clean($row['village_code'] ?? '') ?: null,
                    ]);
                    $created['villages']++;
                }
            }
        });

        $batch->update(['status' => ImportBatch::STATUS_IMPORTED, 'imported_at' => now(), 'summary' => [...($batch->summary ?? []), 'created' => $created]]);

        return $batch;
    }

    public function templateCsv(): string
    {
        return implode(',', self::COLUMNS)."\n".'Madhya Pradesh,Sehore,Sehore,Bilkisganj,466001,'."\n";
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{0: list<array{row: int, column: string, message: string}>, 1: array<string, int>}
     */
    private function analyse(array $rows): array
    {
        $errors = [];

        if ($rows === []) {
            return [[['row' => 1, 'column' => '', 'message' => __('The file contains no data rows.')]], []];
        }

        if (count($rows) > self::MAX_ROWS) {
            return [[['row' => 1, 'column' => '', 'message' => __('A single import is limited to :max rows.', ['max' => self::MAX_ROWS])]], []];
        }

        $states = State::query()->active()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [$this->key($name) => $id]);
        $existingVillages = Village::query()->join('tehsils', 'tehsils.id', '=', 'villages.tehsil_id')
            ->join('districts', 'districts.id', '=', 'tehsils.district_id')
            ->get(['districts.state_id', 'districts.name as district', 'tehsils.name as tehsil', 'villages.name as village'])
            ->map(fn ($row) => $row->state_id.'|'.$this->key($row->district).'|'.$this->key($row->tehsil).'|'.$this->key($row->village))
            ->flip();
        $existingCodes = Village::query()->whereNotNull('code')->pluck('code')->flip();

        $seen = [];
        $seenCodes = [];
        $newVillages = 0;
        $existing = 0;
        $newDistricts = [];
        $newTehsils = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2; // header is line 1

            foreach (self::REQUIRED as $column) {
                if ($this->clean($row[$column] ?? '') === '') {
                    $errors[] = ['row' => $line, 'column' => $column, 'message' => __('Required.')];
                }
            }

            foreach (['district' => 100, 'tehsil' => 100, 'village' => 150] as $column => $max) {
                if (mb_strlen($this->clean($row[$column] ?? '')) > $max) {
                    $errors[] = ['row' => $line, 'column' => $column, 'message' => __('Longer than :max characters.', ['max' => $max])];
                }
            }

            $pin = $this->clean($row['pin_code'] ?? '');

            if ($pin !== '' && ! preg_match('/^\d{6}$/', $pin)) {
                $errors[] = ['row' => $line, 'column' => 'pin_code', 'message' => __('PIN code must be 6 digits.')];
            }

            $stateId = $states[$this->key($row['state'] ?? '')] ?? null;

            if ($this->clean($row['state'] ?? '') !== '' && $stateId === null) {
                $errors[] = ['row' => $line, 'column' => 'state', 'message' => __('Unknown or inactive state ":state". Add it under Geography first.', ['state' => $this->clean($row['state'])])];
            }

            if ($stateId === null || $this->clean($row['village'] ?? '') === '') {
                continue;
            }

            $key = $stateId.'|'.$this->key($row['district']).'|'.$this->key($row['tehsil']).'|'.$this->key($row['village']);

            if (isset($seen[$key])) {
                $errors[] = ['row' => $line, 'column' => 'village', 'message' => __('Duplicate of row :row in this file.', ['row' => $seen[$key]])];

                continue;
            }

            $seen[$key] = $line;
            $code = $this->clean($row['village_code'] ?? '');

            if ($code !== '') {
                if (isset($seenCodes[$code]) || (isset($existingCodes[$code]) && ! isset($existingVillages[$key]))) {
                    $errors[] = ['row' => $line, 'column' => 'village_code', 'message' => __('Village code ":code" is already used.', ['code' => $code])];
                }
                $seenCodes[$code] = true;
            }

            if (isset($existingVillages[$key])) {
                $existing++;
            } else {
                $newVillages++;
                $newDistricts[$stateId.'|'.$this->key($row['district'])] = true;
                $newTehsils[$stateId.'|'.$this->key($row['district']).'|'.$this->key($row['tehsil'])] = true;
            }
        }

        return [$errors, [
            'new_villages' => $newVillages,
            'existing_villages' => $existing,
            'districts_touched' => count($newDistricts),
            'tehsils_touched' => count($newTehsils),
        ]];
    }

    /**
     * @return list<array<string, string>>
     */
    private function readRows(string $absolutePath, string $extension): array
    {
        $reader = $extension === 'xlsx' ? new XlsxReader : new CsvReader;
        $reader->open($absolutePath);

        $header = null;
        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = array_map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value, $row->toArray());

                    if ($header === null) {
                        $header = array_map(fn (string $name) => Str::snake(Str::lower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $name)))), $values);

                        continue;
                    }

                    if (implode('', $values) === '') {
                        continue;
                    }

                    $rows[] = array_intersect_key(array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), '')), array_flip(self::COLUMNS));
                }

                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    private function clean(?string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value));
    }

    private function key(?string $value): string
    {
        return mb_strtolower($this->clean($value));
    }
}
