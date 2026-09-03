<?php

namespace App\Controllers;

use App\Models\RecipientModel;
use App\Services\ActivityLogger;
use CodeIgniter\Controller;

class RecipientController extends Controller
{
    public function index()
    {
        $model = new RecipientModel();
        $search = $this->request->getGet('q');

        $query = $model->orderBy('created_at', 'DESC');
        if ($search) {
            $query->groupStart()->like('name', $search)->orLike('email', $search)->orLike('company', $search)->groupEnd();
        }

        $recipients = $query->paginate(15);

        return view('recipients/index', [
            'title'      => 'Recipients',
            'recipients' => $recipients,
            'pager'      => $model->pager,
            'search'     => $search,
        ]);
    }

    public function create()
    {
        if ($this->request->getMethod() === 'GET') {
            return view('recipients/form', ['title' => 'Add Recipient', 'recipient' => null]);
        }

        $model = new RecipientModel();
        $data = $this->request->getPost(['name', 'email', 'company', 'phone', 'notes']);

        if (! $model->insert($data)) {
            return view('recipients/form', ['title' => 'Add Recipient', 'recipient' => $data, 'errors' => $model->errors()]);
        }

        ActivityLogger::log(session()->get('user_id'), 'recipient.created', 'Recipient created: ' . $data['email']);
        session()->setFlashdata('success', 'Recipient added successfully.');
        return redirect()->to('/recipients');
    }

    public function edit($id)
    {
        $model = new RecipientModel();
        $recipient = $model->find($id);
        if (! $recipient) {
            return redirect()->to('/recipients')->with('error', 'Recipient not found.');
        }

        if ($this->request->getMethod() === 'GET') {
            return view('recipients/form', ['title' => 'Edit Recipient', 'recipient' => $recipient]);
        }

        $data = $this->request->getPost(['name', 'email', 'company', 'phone', 'notes']);
        $model->setValidationRule('email', "required|valid_email|max_length[191]|is_unique[recipients.email,id,{$id}]");

        if (! $model->update($id, $data)) {
            return view('recipients/form', ['title' => 'Edit Recipient', 'recipient' => array_merge(['id' => $id], $data), 'errors' => $model->errors()]);
        }

        ActivityLogger::log(session()->get('user_id'), 'recipient.updated', 'Recipient updated: ' . $data['email']);
        session()->setFlashdata('success', 'Recipient updated successfully.');
        return redirect()->to('/recipients');
    }

    public function delete($id)
    {
        $model = new RecipientModel();
        $recipient = $model->find($id);
        if ($recipient) {
            $model->delete($id);
            ActivityLogger::log(session()->get('user_id'), 'recipient.deleted', 'Recipient deleted: ' . $recipient['email']);
        }
        session()->setFlashdata('success', 'Recipient deleted.');
        return redirect()->to('/recipients');
    }

    public function bulkDelete()
    {
        $ids = array_filter(array_map('intval', $this->request->getPost('ids') ?? []));
        if (empty($ids)) {
            session()->setFlashdata('error', 'No recipients selected.');
            return redirect()->to('/recipients');
        }

        $model = new RecipientModel();
        $model->whereIn('id', $ids)->delete();

        ActivityLogger::log(session()->get('user_id'), 'recipient.bulk_deleted', count($ids) . ' recipients deleted');
        session()->setFlashdata('success', count($ids) . ' recipient(s) deleted.');
        return redirect()->to('/recipients');
    }

    public function import()
    {
        $file = $this->request->getFile('csv');

        if (! $file || ! $file->isValid()) {
            session()->setFlashdata('error', 'Please choose a valid CSV file.');
            return redirect()->to('/recipients');
        }

        if ($file->getSize() > 2 * 1024 * 1024) {
            session()->setFlashdata('error', 'CSV file must be smaller than 2MB.');
            return redirect()->to('/recipients');
        }

        $mime = $file->getMimeType();
        $ext = strtolower($file->getClientExtension());
        if ($ext !== 'csv' || ! in_array($mime, ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'], true)) {
            session()->setFlashdata('error', 'Only CSV files are allowed.');
            return redirect()->to('/recipients');
        }

        $newName = $file->getRandomName();
        $file->move(WRITEPATH . 'uploads', $newName);
        $path = WRITEPATH . 'uploads/' . $newName;

        $summary = (new \App\Services\RecipientImportService())->import($path);
        @unlink($path);

        ActivityLogger::log(session()->get('user_id'), 'recipients.imported',
            "CSV import: {$summary['imported']} imported, {$summary['duplicates']} duplicates, {$summary['invalid']} invalid");

        session()->setFlashdata('importSummary', $summary);
        return redirect()->to('/recipients');
    }

    public function export()
    {
        $model = new RecipientModel();
        $rows = $model->orderBy('created_at', 'DESC')->findAll();

        $this->response->setHeader('Content-Type', 'text/csv');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="recipients.csv"');

        $out = fopen('php://temp', 'w');
        fputcsv($out, ['Name', 'Email', 'Company', 'Phone', 'Status']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['name'], $r['email'], $r['company'], $r['phone'], $r['status']]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response->setBody($csv);
    }
}
