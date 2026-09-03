<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return $this->response->setBody('dashboard placeholder');
    }
}
