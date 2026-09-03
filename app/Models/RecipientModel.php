<?php

namespace App\Models;

use CodeIgniter\Model;

class RecipientModel extends Model
{
    protected $table            = 'recipients';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['name', 'email', 'company', 'phone', 'status', 'notes'];
    protected $useTimestamps    = true;

    protected $validationRules = [
        'name'    => 'required|max_length[150]',
        'email'   => 'required|valid_email|max_length[191]|is_unique[recipients.email,id,{id}]',
        'company' => 'permit_empty|max_length[150]',
        'phone'   => 'permit_empty|max_length[30]',
        'notes'   => 'permit_empty|max_length[2000]',
    ];
}
