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
        $model = new EmailTemplateModel();
        return view('templates/index', [
            'title'     => 'Email Templates',
            'templates' => $model->orderBy('created_at', 'DESC')->paginate(15),
            'pager'     => $model->pager,
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

        return view('templates/preview', ['title' => 'Preview', 'rendered' => $rendered]);
    }
}
