<?php

namespace App\Services;

use App\Models\RecipientModel;

class RecipientImportService
{
    public function import(string $csvPath): array
    {
        $model = new RecipientModel();
        $summary = ['imported' => 0, 'skipped' => 0, 'invalid' => 0, 'duplicates' => 0, 'errors' => []];

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $summary['errors'][] = 'Could not read the uploaded file.';
            return $summary;
        }

        $header = fgetcsv($handle);
        if ($header === false || ! in_array('Email', $header, true)) {
            $summary['errors'][] = 'CSV must include a header row with an Email column.';
            fclose($handle);
            return $summary;
        }
        $header = array_map('trim', $header);

        $seenInFile = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count(array_filter($row, static fn ($v) => $v !== '' && $v !== null)) === 0) {
                continue;
            }
            $data = array_combine($header, array_pad($row, count($header), ''));

            $email = trim($data['Email'] ?? '');
            $name = trim($data['Name'] ?? '');

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

            if ($model->where('email', $email)->first()) {
                $summary['duplicates']++;
                continue;
            }

            $inserted = $model->insert([
                'name'    => $name,
                'email'   => $email,
                'company' => trim($data['Company'] ?? '') ?: null,
                'phone'   => trim($data['Phone'] ?? '') ?: null,
            ], false);

            if ($inserted) {
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
