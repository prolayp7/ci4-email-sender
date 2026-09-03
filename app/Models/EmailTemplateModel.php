<?php

namespace App\Models;

use CodeIgniter\Model;

class EmailTemplateModel extends Model
{
    protected $table         = 'email_templates';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'subject', 'html_body', 'text_body', 'status'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'name'      => 'required|max_length[150]',
        'subject'   => 'required|max_length[255]',
        'html_body' => 'required',
    ];
}
