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

    $routes->get('templates', 'TemplateController::index');
    $routes->match(['get', 'post'], 'templates/create', 'TemplateController::create');
    $routes->match(['get', 'post'], 'templates/edit/(:num)', 'TemplateController::edit/$1');
    $routes->post('templates/delete/(:num)', 'TemplateController::delete/$1');
    $routes->post('templates/duplicate/(:num)', 'TemplateController::duplicate/$1');
    $routes->get('templates/preview/(:num)', 'TemplateController::preview/$1');
});
