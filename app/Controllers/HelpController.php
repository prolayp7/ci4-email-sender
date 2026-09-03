<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class HelpController extends Controller
{
    public function index()
    {
        return view('help/index', ['title' => 'Help']);
    }
}
