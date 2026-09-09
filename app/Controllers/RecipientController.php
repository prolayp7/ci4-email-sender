<?php

namespace App\Controllers;

use App\Models\EmailTemplateModel;
use App\Models\RecipientModel;
use App\Services\ActivityLogger;
use App\Services\GroupService;
use App\Services\RecipientImportService;
use App\Services\TagService;
use CodeIgniter\Controller;

class RecipientController extends Controller
{
    private const PER_PAGE_OPTIONS = [25, 50, 100];
    private const LAST_ACTIVITY_OPTIONS = ['7', '30', '90', 'never'];

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
        $location = (string) $this->request->getGet('location');
        $company = (string) $this->request->getGet('company');
        $tagId = $this->request->getGet('tag_id');
        $tagId = ($tagId !== null && $tagId !== '') ? (int) $tagId : null;
        $lastActivity = $this->request->getGet('last_activity');
        $lastActivity = in_array($lastActivity, self::LAST_ACTIVITY_OPTIONS, true) ? $lastActivity : null;
        $perPage = (int) $this->request->getGet('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];

        $sortable = ['name', 'email', 'company', 'location', 'status', 'created_at'];
        $sort = in_array($this->request->getGet('sort'), $sortable, true) ? $this->request->getGet('sort') : 'created_at';
        $dir = strtolower((string) $this->request->getGet('dir')) === 'asc' ? 'asc' : 'desc';

        $templateId = $this->request->getGet('template_id');
        $templateId = ($templateId !== null && $templateId !== '') ? (int) $templateId : null;
        $sentStatus = $this->request->getGet('sent_status');
        $sentStatus = in_array($sentStatus, ['sent', 'unsent'], true) ? $sentStatus : null;

        $query = $model->orderBy($sort, $dir);
        $needsExplicitSelect = false;

        if ($search) {
            $query->groupStart()->like('name', $search)->orLike('email', $search)->orLike('company', $search)->orLike('location', $search)->groupEnd();
        }
        if (in_array($status, ['active', 'unsubscribed', 'bounced', 'suppressed'], true)) {
            $query->where('status', $status);
        }
        if ($location !== '') {
            $query->where('location', $location);
        }
        if ($company !== '') {
            $query->where('company', $company);
        }

        if ($tagId !== null) {
            $needsExplicitSelect = true;
            $query->join('recipient_tags rtf', 'rtf.recipient_id = recipients.id AND rtf.tag_id = ' . $tagId);
        }

        if ($templateId !== null) {
            // "Sent for this template" is derived on the fly from the emails
            // log (one row per recipient per send) rather than stored on the
            // recipient -- a recipient can be sent/unsent per template, so
            // there's no single flag on `recipients` that could hold this.
            $needsExplicitSelect = true;
            $sentSub = db_connect()->table('emails')
                ->select('recipient_id, MAX(sent_at) AS last_sent_at')
                ->where('status', 'sent')
                ->where('template_id', $templateId)
                ->groupBy('recipient_id');

            $query->select('es.last_sent_at')
                ->join('(' . $sentSub->getCompiledSelect() . ') es', 'es.recipient_id = recipients.id', 'left');

            if ($sentStatus === 'unsent') {
                $query->where('es.recipient_id', null);
            } elseif ($sentStatus === 'sent') {
                $query->where('es.recipient_id IS NOT NULL', null, false);
            }
        }

        // Always joined (not just when filtering) so the Last Activity column
        // has a value to show for every row, independent of the per-template
        // sent/unsent filter above.
        $needsExplicitSelect = true;
        $lastSub = db_connect()->table('emails')
            ->select('recipient_id, MAX(sent_at) AS last_activity_at')
            ->where('status', 'sent')
            ->groupBy('recipient_id');

        $query->select('la.last_activity_at')
            ->join('(' . $lastSub->getCompiledSelect() . ') la', 'la.recipient_id = recipients.id', 'left');

        if ($lastActivity === 'never') {
            $query->where('la.recipient_id', null);
        } elseif ($lastActivity !== null) {
            $query->where('la.last_activity_at >=', date('Y-m-d H:i:s', strtotime('-' . (int) $lastActivity . ' days')));
        }

        if ($needsExplicitSelect) {
            $query->select('recipients.*');
        }

        $recipients = $query->paginate($perPage);
        $recipientIds = array_column($recipients, 'id');
        $tagsByRecipient = $this->tagsByRecipient($recipientIds);
        $lastCampaignByRecipient = $this->lastCampaignByRecipient($recipientIds);

        return view('recipients/index', [
            'title'         => 'Recipients',
            'recipients'    => $recipients,
            'pager'         => $model->pager,
            'search'        => $search,
            'status'        => $status,
            'location'      => $location,
            'company'       => $company,
            'sort'          => $sort,
            'dir'           => $dir,
            'stats'         => $stats,
            'templates'     => (new EmailTemplateModel())->where('status', 'active')->orderBy('name', 'asc')->findAll(),
            'templateId'    => $templateId,
            'sentStatus'    => $sentStatus,
            'tags'          => (new TagService())->all(),
            'tagId'         => $tagId,
            'tagsByRecipient' => $tagsByRecipient,
            'lastCampaignByRecipient' => $lastCampaignByRecipient,
            'lastActivity'  => $lastActivity,
            'perPage'       => $perPage,
            'locations'     => $this->distinctLocations(),
            'companies'     => (new RecipientModel())->distinct()->select('company')->where('company IS NOT NULL')->where('company !=', '')->orderBy('company', 'asc')->findAll(),
            'groups'        => (new GroupService())->all(),
        ]);
    }

    /**
     * Every distinct, non-empty Location already in use -- offered as
     * datalist suggestions on the recipient form so people reuse an
     * existing value (e.g. "Canada • Ontario") instead of a slightly
     * different one that would silently split into its own group on the
     * Compose page, which groups recipients by exact Location string match.
     *
     * @return list<array{location: string}>
     */
    private function distinctLocations(): array
    {
        return (new RecipientModel())->distinct()->select('location')
            ->where('location IS NOT NULL')->where('location !=', '')
            ->orderBy('location', 'asc')->findAll();
    }

    /** @param list<int> $recipientIds @return array<int,list<string>> recipient id => tag names */
    private function tagsByRecipient(array $recipientIds): array
    {
        if ($recipientIds === []) {
            return [];
        }

        $rows = db_connect()->table('recipient_tags rt')
            ->select('rt.recipient_id, t.name')
            ->join('tags t', 't.id = rt.tag_id')
            ->whereIn('rt.recipient_id', $recipientIds)
            ->orderBy('t.name', 'asc')
            ->get()->getResultArray();

        $tagMap = [];
        foreach ($rows as $row) {
            $tagMap[(int) $row['recipient_id']][] = $row['name'];
        }
        return $tagMap;
    }

    /**
     * @param list<int> $recipientIds
     * @return array<int,string> recipient id => name of the most recent campaign sent to them
     *                            ("One-off email" when that send had no template)
     */
    private function lastCampaignByRecipient(array $recipientIds): array
    {
        if ($recipientIds === []) {
            return [];
        }

        $latestSub = db_connect()->table('emails')
            ->select('recipient_id, MAX(sent_at) AS max_sent_at')
            ->where('status', 'sent')
            ->whereIn('recipient_id', $recipientIds)
            ->groupBy('recipient_id');

        $rows = db_connect()->table('emails e')
            ->select('e.recipient_id, et.name AS campaign_name')
            ->join('(' . $latestSub->getCompiledSelect() . ') latest', 'latest.recipient_id = e.recipient_id AND latest.max_sent_at = e.sent_at')
            ->join('email_templates et', 'et.id = e.template_id', 'left')
            ->where('e.status', 'sent')
            ->get()->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['recipient_id']] = $row['campaign_name'] ?? 'One-off email';
        }
        return $map;
    }

    public function create()
    {
        if ($this->request->getMethod() === 'GET') {
            return view('recipients/form', ['title' => 'Add Recipient', 'recipient' => null, 'locations' => $this->distinctLocations()]);
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
            return view('recipients/form', ['title' => 'Add Recipient', 'recipient' => $data, 'errors' => $model->errors(), 'locations' => $this->distinctLocations()]);
        }

        (new TagService())->syncForRecipient($model->getInsertID(), (string) $this->request->getPost('tags'));
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
            $recipient['tags'] = implode(', ', (new TagService())->namesForRecipient((int) $id));
            return view('recipients/form', ['title' => 'Edit Recipient', 'recipient' => $recipient, 'locations' => $this->distinctLocations()]);
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
            return view('recipients/form', ['title' => 'Edit Recipient', 'recipient' => array_merge(['id' => $id], $data), 'errors' => $model->errors(), 'locations' => $this->distinctLocations()]);
        }

        (new TagService())->syncForRecipient((int) $id, (string) $this->request->getPost('tags'));
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

    public function bulkStatus()
    {
        $ids = array_filter(array_map('intval', $this->request->getPost('ids') ?? []));
        $status = $this->request->getPost('status');

        if (empty($ids) || ! in_array($status, ['active', 'unsubscribed', 'bounced', 'suppressed'], true)) {
            session()->setFlashdata('error', 'No recipients selected or invalid status.');
            return redirect()->to('/recipients');
        }

        db_connect()->table('recipients')->whereIn('id', $ids)->update(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);

        ActivityLogger::log(session()->get('user_id'), 'recipient.bulk_status', count($ids) . ' recipient(s) set to ' . $status);
        session()->setFlashdata('success', count($ids) . ' recipient(s) updated to ' . $status . '.');
        return redirect()->to('/recipients');
    }

    public function updateStatus($id)
    {
        $status = $this->request->getPost('status');
        $model = new RecipientModel();
        $recipient = $model->find($id);

        if (! $recipient || ! in_array($status, ['active', 'unsubscribed', 'bounced', 'suppressed'], true)) {
            session()->setFlashdata('error', 'Recipient not found or invalid status.');
            return redirect()->to('/recipients');
        }

        $model->update($id, ['status' => $status]);
        ActivityLogger::log(session()->get('user_id'), 'recipient.status_changed', $recipient['email'] . ' set to ' . $status);
        session()->setFlashdata('success', 'Recipient status updated.');
        return redirect()->to('/recipients');
    }

    public function profile($id)
    {
        $recipient = (new RecipientModel())->find($id);
        if (! $recipient) {
            return redirect()->to('/recipients')->with('error', 'Recipient not found.');
        }

        $db = db_connect();
        $emails = $db->table('emails')
            ->select('emails.*, email_templates.name AS template_name')
            ->join('email_templates', 'email_templates.id = emails.template_id', 'left')
            ->where('recipient_id', $id)
            ->orderBy('created_at', 'DESC')
            ->get()->getResultArray();

        $emailIds = array_column($emails, 'id');
        $events = $emailIds === [] ? [] : $db->table('email_events')
            ->whereIn('email_id', $emailIds)
            ->orderBy('created_at', 'DESC')
            ->get()->getResultArray();

        $eventsByEmail = [];
        foreach ($events as $event) {
            $eventsByEmail[(int) $event['email_id']][] = $event;
        }

        // Merge sends and open/click events into one reverse-chronological
        // timeline, each entry carrying its own timestamp for sorting.
        $timeline = [];
        foreach ($emails as $email) {
            $timeline[] = ['kind' => 'email', 'at' => $email['created_at'], 'data' => $email];
            foreach ($eventsByEmail[(int) $email['id']] ?? [] as $event) {
                $timeline[] = ['kind' => 'event', 'at' => $event['created_at'], 'data' => $event, 'email' => $email];
            }
        }
        usort($timeline, static fn ($a, $b) => strcmp($b['at'] ?? '', $a['at'] ?? ''));

        return view('recipients/profile', [
            'title'     => $recipient['name'],
            'recipient' => $recipient,
            'tags'      => (new TagService())->namesForRecipient((int) $id),
            'timeline'  => $timeline,
        ]);
    }

    /**
     * Step 1 of the import wizard: validates and stores the uploaded file
     * under a random name, then returns its header row plus a best-effort
     * column mapping. The returned token is echoed back by the client on
     * the later validate/commit steps to locate the same file server-side.
     */
    public function importUpload()
    {
        $file = $this->request->getFile('csv');

        if (! $file || ! $file->isValid()) {
            return $this->jsonResponse(false, 'Please choose a valid CSV file.');
        }

        if ($file->getSize() > 2 * 1024 * 1024) {
            return $this->jsonResponse(false, 'CSV file must be smaller than 2MB.');
        }

        $mime = $file->getMimeType();
        $ext = strtolower($file->getClientExtension());
        if ($ext !== 'csv' || ! in_array($mime, ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'], true)) {
            return $this->jsonResponse(false, 'Only CSV files are allowed.');
        }

        $token = $file->getRandomName();
        $file->move(WRITEPATH . 'uploads', $token);

        $result = (new RecipientImportService())->readHeader(WRITEPATH . 'uploads/' . $token);
        if (isset($result['error'])) {
            @unlink(WRITEPATH . 'uploads/' . $token);
            return $this->jsonResponse(false, $result['error']);
        }

        return $this->response->setJSON([
            'success'          => true,
            'token'            => $token,
            'headers'          => $result['headers'],
            'suggestedMapping' => $result['suggestedMapping'],
            'csrf_hash'        => csrf_hash(),
        ]);
    }

    /** Step 2 (Validate): dry-runs the file with the chosen column mapping -- no writes. */
    public function importValidate()
    {
        $path = $this->resolveImportToken((string) $this->request->getPost('token'));
        if (! $path) {
            return $this->jsonResponse(false, 'Upload session expired. Please choose the file again.');
        }

        $summary = (new RecipientImportService())->preview($path, $this->importMappingFromPost());

        return $this->response->setJSON(['success' => true, 'summary' => $summary, 'csrf_hash' => csrf_hash()]);
    }

    /** Step 3 (Import): actually writes the rows, then deletes the temp upload. */
    public function importCommit()
    {
        $path = $this->resolveImportToken((string) $this->request->getPost('token'));
        if (! $path) {
            return $this->jsonResponse(false, 'Upload session expired. Please choose the file again.');
        }

        $duplicateMode = $this->request->getPost('duplicate_mode') === 'update' ? 'update' : 'skip';
        $summary = (new RecipientImportService())->import($path, $this->importMappingFromPost(), $duplicateMode);
        @unlink($path);

        $groupName = trim((string) $this->request->getPost('group_name'));
        if ($groupName !== '' && $summary['recipientIds'] !== []) {
            $groupService = new GroupService();
            $groupService->addRecipients($groupService->findOrCreate($groupName), $summary['recipientIds']);
        }

        ActivityLogger::log(session()->get('user_id'), 'recipients.imported',
            "CSV import: {$summary['imported']} imported, {$summary['updated']} updated, {$summary['duplicates']} duplicates, {$summary['invalid']} invalid");

        return $this->response->setJSON(['success' => true, 'summary' => $summary, 'csrf_hash' => csrf_hash()]);
    }

    /** @return array<string,int|null> */
    private function importMappingFromPost(): array
    {
        $mapping = [];
        foreach (['name', 'email', 'company', 'location', 'phone'] as $field) {
            $value = $this->request->getPost('map_' . $field);
            $mapping[$field] = ($value === null || $value === '') ? null : (int) $value;
        }
        return $mapping;
    }

    /**
     * Resolves an import token to a real path, rejecting anything that
     * doesn't look like one we generated ourselves (the token is
     * client-echoed input on the validate/commit steps).
     */
    private function resolveImportToken(string $token): ?string
    {
        if (! preg_match('/^[A-Za-z0-9._-]+\.csv$/', $token)) {
            return null;
        }
        $path = WRITEPATH . 'uploads/' . $token;
        return is_file($path) ? $path : null;
    }

    private function jsonResponse(bool $success, string $message)
    {
        return $this->response->setJSON(['success' => $success, 'message' => $message, 'csrf_hash' => csrf_hash()]);
    }

    public function export()
    {
        $model = new RecipientModel();
        $ids = array_filter(array_map('intval', $this->request->getGet('ids') ?? $this->request->getPost('ids') ?? []));
        if ($ids !== []) {
            $model->whereIn('id', $ids);
        }
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
