<?php

namespace App\Controllers;

use App\Models\EmailTemplateModel;
use App\Services\ActivityLogger;
use App\Services\TemplateRenderer;
use CodeIgniter\Controller;

class TemplateController extends Controller
{
    public function index()
    {
        // Separate instances so the stats strip always reflects the whole
        // table, unaffected by the search/status filters applied below.
        $stats = [
            'total'  => (new EmailTemplateModel())->countAll(),
            'active' => (new EmailTemplateModel())->where('status', 'active')->countAllResults(),
            'draft'  => (new EmailTemplateModel())->where('status', 'draft')->countAllResults(),
        ];

        $model = new EmailTemplateModel();
        $search = $this->request->getGet('q');
        $status = $this->request->getGet('status');

        $sortable = ['name', 'subject', 'status', 'created_at'];
        $sort = in_array($this->request->getGet('sort'), $sortable, true) ? $this->request->getGet('sort') : 'created_at';
        $dir = strtolower((string) $this->request->getGet('dir')) === 'asc' ? 'asc' : 'desc';

        $query = $model->orderBy($sort, $dir);
        if ($search) {
            $query->groupStart()->like('name', $search)->orLike('subject', $search)->groupEnd();
        }
        if (in_array($status, ['active', 'draft'], true)) {
            $query->where('status', $status);
        }

        return view('templates/index', [
            'title'     => 'Email Templates',
            'templates' => $query->paginate(15),
            'pager'     => $model->pager,
            'search'    => $search,
            'status'    => $status,
            'sort'      => $sort,
            'dir'       => $dir,
            'stats'     => $stats,
        ]);
    }

    public function create()
    {
        if ($this->request->getMethod() === 'GET') {
            return view('templates/form', ['title' => 'Create Template', 'template' => null]);
        }

        $model = new EmailTemplateModel();
        $data = $this->request->getPost(['name', 'subject', 'html_body', 'text_body']);

        if (! $model->insert($data)) {
            return view('templates/form', ['title' => 'Create Template', 'template' => $data, 'errors' => $model->errors()]);
        }

        ActivityLogger::log(session()->get('user_id'), 'template.created', 'Template created: ' . $data['name']);
        session()->setFlashdata('success', 'Template created successfully.');
        return redirect()->to('/templates');
    }

    public function edit($id)
    {
        $model = new EmailTemplateModel();
        $template = $model->find($id);
        if (! $template) {
            return redirect()->to('/templates')->with('error', 'Template not found.');
        }

        if ($this->request->getMethod() === 'GET') {
            return view('templates/form', ['title' => 'Edit Template', 'template' => $template]);
        }

        $data = $this->request->getPost(['name', 'subject', 'html_body', 'text_body']);
        if (! $model->update($id, $data)) {
            return view('templates/form', ['title' => 'Edit Template', 'template' => array_merge(['id' => $id], $data), 'errors' => $model->errors()]);
        }

        ActivityLogger::log(session()->get('user_id'), 'template.updated', 'Template updated: ' . $data['name']);
        session()->setFlashdata('success', 'Template updated successfully.');
        return redirect()->to('/templates');
    }

    public function delete($id)
    {
        $model = new EmailTemplateModel();
        $template = $model->find($id);
        if ($template) {
            $model->delete($id);
            ActivityLogger::log(session()->get('user_id'), 'template.deleted', 'Template deleted: ' . $template['name']);
        }
        session()->setFlashdata('success', 'Template deleted.');
        return redirect()->to('/templates');
    }

    public function duplicate($id)
    {
        $model = new EmailTemplateModel();
        $template = $model->find($id);
        if ($template) {
            unset($template['id'], $template['created_at'], $template['updated_at']);
            $template['name'] .= ' (Copy)';
            $model->insert($template);
        }
        session()->setFlashdata('success', 'Template duplicated.');
        return redirect()->to('/templates');
    }

    public function preview($id)
    {
        $model = new EmailTemplateModel();
        $template = $model->find($id);
        if (! $template) {
            return redirect()->to('/templates');
        }

        $rendered = (new TemplateRenderer())->render($template['html_body'], [
            'name' => 'Sample Name', 'email' => 'sample@example.com', 'company' => 'Sample Co',
        ]);

        // Template bodies are admin-authored HTML rendered unescaped so the
        // preview looks like the real email. A strict script-blocking CSP
        // (in place of the app-wide one, see SecurityHeaders filter) means
        // any injected <script> or event handler is inert even though the
        // markup itself renders — defense in depth if a template ever gets
        // edited by a less-trusted role than the one previewing it.
        $this->response->setHeader(
            'Content-Security-Policy',
            "default-src 'none'; style-src 'unsafe-inline'; img-src * data:; script-src 'none';"
        );

        return view('templates/preview', ['title' => 'Preview', 'rendered' => $rendered]);
    }
}
