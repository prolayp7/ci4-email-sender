<?php

namespace App\Controllers;

use App\Models\EmailTemplateModel;
use App\Models\RecipientModel;
use App\Services\ActivityLogger;
use CodeIgniter\Controller;

class RecipientController extends Controller
{
    public function index()
    {
        // Separate instances so the stats strip always reflects the whole
        // table, unaffected by the search/status filters applied below.
        $stats = [
            'total'        => (new RecipientModel())->countAll(),
            'active'       => (new RecipientModel())->where('status', 'active')->countAllResults(),
            'unsubscribed' => (new RecipientModel())->where('status', 'unsubscribed')->countAllResults(),
            'bounced'      => (new RecipientModel())->where('status', 'bounced')->countAllResults(),
            'suppressed'   => (new RecipientModel())->where('status', 'suppressed')->countAllResults(),
        ];

        $model = new RecipientModel();
        $search = $this->request->getGet('q');
        $status = $this->request->getGet('status');

        $sortable = ['name', 'email', 'company', 'location', 'status', 'created_at'];
        $sort = in_array($this->request->getGet('sort'), $sortable, true) ? $this->request->getGet('sort') : 'created_at';
        $dir = strtolower((string) $this->request->getGet('dir')) === 'asc' ? 'asc' : 'desc';

        $templateId = $this->request->getGet('template_id');
        $templateId = ($templateId !== null && $templateId !== '') ? (int) $templateId : null;
        $sentStatus = $this->request->getGet('sent_status');
        $sentStatus = in_array($sentStatus, ['sent', 'unsent'], true) ? $sentStatus : null;

        $query = $model->orderBy($sort, $dir);
        if ($search) {
            $query->groupStart()->like('name', $search)->orLike('email', $search)->orLike('company', $search)->orLike('location', $search)->groupEnd();
        }
        if (in_array($status, ['active', 'unsubscribed', 'bounced', 'suppressed'], true)) {
            $query->where('status', $status);
        }

        if ($templateId !== null) {
            // "Sent for this template" is derived on the fly from the emails
            // log (one row per recipient per send) rather than stored on the
            // recipient -- a recipient can be sent/unsent per template, so
            // there's no single flag on `recipients` that could hold this.
            $sentSub = db_connect()->table('emails')
                ->select('recipient_id, MAX(sent_at) AS last_sent_at')
                ->where('status', 'sent')
                ->where('template_id', $templateId)
                ->groupBy('recipient_id');

            $query->select('recipients.*, es.last_sent_at')
                ->join('(' . $sentSub->getCompiledSelect() . ') es', 'es.recipient_id = recipients.id', 'left');

            if ($sentStatus === 'unsent') {
                $query->where('es.recipient_id', null);
            } elseif ($sentStatus === 'sent') {
                $query->where('es.recipient_id IS NOT NULL', null, false);
            }
        }

        $recipients = $query->paginate(15);

        return view('recipients/index', [
            'title'       => 'Recipients',
            'recipients'  => $recipients,
            'pager'       => $model->pager,
            'search'      => $search,
            'status'      => $status,
            'sort'        => $sort,
            'dir'         => $dir,
            'stats'       => $stats,
            'templates'   => (new EmailTemplateModel())->where('status', 'active')->orderBy('name', 'asc')->findAll(),
            'templateId'  => $templateId,
            'sentStatus'  => $sentStatus,
        ]);
    }

    public function create()
    {
        if ($this->request->getMethod() === 'GET') {
            return view('recipients/form', ['title' => 'Add Recipient', 'recipient' => null]);
        }

        $wantsJson = $this->request->getHeaderLine('Accept') === 'application/json';

        $model = new RecipientModel();
        $data = $this->request->getPost(['name', 'email', 'company', 'location', 'phone', 'status', 'notes']);

        if (! $model->insert($data)) {
            if ($wantsJson) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success'  => false,
                    'errors'   => $model->errors(),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }
            return view('recipients/form', ['title' => 'Add Recipient', 'recipient' => $data, 'errors' => $model->errors()]);
        }

        ActivityLogger::log(session()->get('user_id'), 'recipient.created', 'Recipient created: ' . $data['email']);

        if ($wantsJson) {
            return $this->response->setJSON(['success' => true]);
        }

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

        $wantsJson = $this->request->getHeaderLine('Accept') === 'application/json';

        $data = $this->request->getPost(['name', 'email', 'company', 'location', 'phone', 'status', 'notes']);
        $model->setValidationRule('email', "required|valid_email|max_length[191]|is_unique[recipients.email,id,{$id}]");

        if (! $model->update($id, $data)) {
            if ($wantsJson) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success'  => false,
                    'errors'   => $model->errors(),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }
            return view('recipients/form', ['title' => 'Edit Recipient', 'recipient' => array_merge(['id' => $id], $data), 'errors' => $model->errors()]);
        }

        ActivityLogger::log(session()->get('user_id'), 'recipient.updated', 'Recipient updated: ' . $data['email']);
        session()->setFlashdata('success', 'Recipient updated successfully.');

        if ($wantsJson) {
            return $this->response->setJSON(['success' => true]);
        }
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
        fputcsv($out, ['Name', 'Email', 'Company', 'Location', 'Phone', 'Status']);
        foreach ($rows as $r) {
            fputcsv($out, array_map([$this, 'escapeCsvFormula'], [
                $r['name'], $r['email'], $r['company'], $r['location'], $r['phone'], $r['status'],
            ]));
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response->setBody($csv);
    }

    /**
     * Prefixes a leading =, +, -, @, tab, or CR with a single quote so
     * spreadsheet apps (Excel, Google Sheets) treat the cell as text
     * instead of executing it as a formula (CSV formula injection).
     */
    private function escapeCsvFormula(?string $value): string
    {
        $value = (string) $value;
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    }
}
