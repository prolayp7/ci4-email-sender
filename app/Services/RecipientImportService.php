<?php

namespace App\Services;

use App\Models\RecipientModel;

class RecipientImportService
{
    private const FIELDS = ['name', 'email', 'company', 'location', 'phone'];

    /**
     * Reads just the header row for the Map Columns step, plus a best-effort
     * auto-mapping (exact, case-insensitive header-name match) so a file that
     * already uses our column names doesn't force the user to map anything
     * by hand.
     *
     * @return array{headers: list<string>, suggestedMapping: array<string,int|null>}|array{error:string}
     */
    public function readHeader(string $csvPath): array
    {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return ['error' => 'Could not read the uploaded file.'];
        }

        $header = fgetcsv($handle);
        fclose($handle);

        if ($header === false || $header === [null]) {
            return ['error' => 'The CSV file appears to be empty.'];
        }
        $header = array_map(static fn ($h) => trim((string) $h), $header);

        $lowerHeader = array_map('strtolower', $header);
        $suggested   = [];
        foreach (self::FIELDS as $field) {
            $index             = array_search($field, $lowerHeader, true);
            $suggested[$field] = $index === false ? null : $index;
        }

        return ['headers' => $header, 'suggestedMapping' => $suggested];
    }

    /**
     * Dry-run over the whole file with the given column mapping: validates
     * and classifies every row but writes nothing -- powers the Validate
     * step so the user sees valid/duplicate/invalid counts before committing
     * to anything.
     *
     * @param array<string,int|null> $mapping target field => source column index
     */
    public function preview(string $csvPath, array $mapping): array
    {
        return $this->walk($csvPath, $mapping, null);
    }

    /**
     * Same walk as preview(), but actually writes rows. A new email is
     * always inserted; an email that already exists is left alone (default)
     * or overwritten with this row's values when $duplicateMode is 'update'.
     *
     * @param array<string,int|null> $mapping        target field => source column index
     * @param 'skip'|'update'        $duplicateMode
     */
    public function import(string $csvPath, array $mapping, string $duplicateMode = 'skip'): array
    {
        return $this->walk($csvPath, $mapping, $duplicateMode === 'update' ? 'update' : 'skip');
    }

    /**
     * @param array<string,int|null> $mapping
     * @param 'skip'|'update'|null   $duplicateMode null = dry run (preview), no DB writes
     */
    private function walk(string $csvPath, array $mapping, ?string $duplicateMode): array
    {
        $model   = new RecipientModel();
        $summary = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'invalid' => 0, 'duplicates' => 0, 'errors' => []];

        if (($mapping['email'] ?? null) === null) {
            $summary['errors'][] = 'Map a column to Email before importing.';

            return $summary;
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $summary['errors'][] = 'Could not read the uploaded file.';

            return $summary;
        }
        fgetcsv($handle); // header row -- already consumed by readHeader()

        $seenInFile = [];
        $rowNum     = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count(array_filter($row, static fn ($v) => $v !== '' && $v !== null)) === 0) {
                continue;
            }

            $get   = static fn (string $field): string => trim((string) ($row[$mapping[$field] ?? -1] ?? ''));
            $email = $get('email');
            $name  = $get('name');

            if ($email === '' || $name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($name) > 150) {
                $summary['invalid']++;
                $summary['errors'][] = "Row {$rowNum}: invalid name or email.";
                continue;
            }

            $emailLower = strtolower($email);
            if (isset($seenInFile[$emailLower])) {
                $summary['duplicates']++;
                continue;
            }
            $seenInFile[$emailLower] = true;

            $fields = [
                'name'     => $name,
                'email'    => $email,
                'company'  => $get('company') ?: null,
                'location' => $get('location') ?: null,
                'phone'    => $get('phone') ?: null,
            ];

            $existing = $model->where('email', $email)->first();
            if ($existing) {
                $summary['duplicates']++;
                if ($duplicateMode === 'update') {
                    // is_unique[...,{id}] only expands {id} from the row's own
                    // updated data, which doesn't include the primary key --
                    // without this, it compares against the literal string
                    // "{id}" and the row's unchanged email fails uniqueness
                    // against itself. Same workaround RecipientController::edit()
                    // already uses.
                    $model->setValidationRule('email', "required|valid_email|max_length[191]|is_unique[recipients.email,id,{$existing['id']}]");
                    $model->update($existing['id'], $fields);
                    $summary['updated']++;
                }
                continue;
            }

            if ($duplicateMode === null) {
                // Preview only -- this row would be a new insert, but don't write it.
                $summary['imported']++;
                continue;
            }

            if ($model->insert($fields, false)) {
                $summary['imported']++;
            } else {
                $summary['skipped']++;
                $summary['errors'][] = "Row {$rowNum}: " . implode('; ', $model->errors());
            }
        }

        fclose($handle);

        return $summary;
    }
}
