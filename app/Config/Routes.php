<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', static function () {
    return redirect()->to('/login');
});

$routes->get('login', 'AuthController::showLogin');
$routes->post('login', 'AuthController::login');
$routes->post('logout', 'AuthController::logout');

$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->get('dashboard', 'DashboardController::index');

    $routes->get('recipients', 'RecipientController::index');
    $routes->match(['get', 'post'], 'recipients/create', 'RecipientController::create');
    $routes->match(['get', 'post'], 'recipients/edit/(:num)', 'RecipientController::edit/$1');
    $routes->post('recipients/delete/(:num)', 'RecipientController::delete/$1');
    $routes->post('recipients/bulk-delete', 'RecipientController::bulkDelete');
    $routes->post('recipients/import', 'RecipientController::import');
    $routes->get('recipients/export', 'RecipientController::export');
});
