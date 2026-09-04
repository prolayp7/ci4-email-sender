<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

if (ENVIRONMENT === 'testing') {
    $routes->set404Override(static function ($msg = null) {
        service('response')->setStatusCode(404);
        return 'Not Found';
    });
}

$routes->get('/', static function () {
    return redirect()->to('/login');
});

$routes->get('login', 'AuthController::showLogin');
$routes->post('login', 'AuthController::login');
$routes->post('logout', 'AuthController::logout');

// Any authenticated role (owner/admin/operator/viewer): read-only viewing
// and self-service account actions.
$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->get('dashboard', 'DashboardController::index');

    $routes->get('recipients', 'RecipientController::index');
    $routes->get('recipients/export', 'RecipientController::export');

    $routes->get('templates', 'TemplateController::index');
    $routes->get('templates/preview/(:num)', 'TemplateController::preview/$1');

    $routes->get('emails', 'EmailController::index');
    $routes->get('emails/drafts', 'EmailController::drafts');
    $routes->get('emails/trash', 'EmailController::trash');
    $routes->get('emails/(:num)/attachments/(:num)', 'EmailController::attachment/$1/$2');
    $routes->get('emails/(:num)', 'EmailController::show/$1');

    $routes->get('help', 'HelpController::index');
});

// owner/admin/operator: create, edit, delete, send, import, retry.
// Excludes 'viewer', which is read-only.
$routes->group('', ['filter' => ['auth', 'role:owner,admin,operator']], static function ($routes) {
    $routes->match(['get', 'post'], 'recipients/create', 'RecipientController::create');
    $routes->match(['get', 'post'], 'recipients/edit/(:num)', 'RecipientController::edit/$1');
    $routes->post('recipients/delete/(:num)', 'RecipientController::delete/$1');
    $routes->post('recipients/bulk-delete', 'RecipientController::bulkDelete');
    $routes->post('recipients/import', 'RecipientController::import');

    $routes->match(['get', 'post'], 'templates/create', 'TemplateController::create');
    $routes->match(['get', 'post'], 'templates/edit/(:num)', 'TemplateController::edit/$1');
    $routes->post('templates/delete/(:num)', 'TemplateController::delete/$1');
    $routes->post('templates/duplicate/(:num)', 'TemplateController::duplicate/$1');

    $routes->get('compose', 'ComposeController::index');
    $routes->post('compose/send', 'ComposeController::send');
    $routes->post('compose/draft', 'ComposeController::saveDraft');
    $routes->get('compose/edit/(:num)', 'ComposeController::edit/$1');
    $routes->post('compose/update/(:num)', 'ComposeController::update/$1');

    $routes->post('emails/retry/(:num)', 'EmailController::retry/$1');
    $routes->post('emails/send-draft/(:num)', 'EmailController::sendDraft/$1');
    $routes->post('emails/delete/(:num)', 'EmailController::delete/$1');
    $routes->post('emails/restore/(:num)', 'EmailController::restore/$1');
    $routes->post('emails/destroy/(:num)', 'EmailController::destroy/$1');
});

// owner/admin only: SMTP credentials are the most sensitive setting in the
// app (the app will authenticate to whatever host is configured with them).
$routes->group('', ['filter' => ['auth', 'role:owner,admin']], static function ($routes) {
    $routes->get('smtp', 'SmtpController::index');
    $routes->post('smtp', 'SmtpController::save');
    $routes->post('smtp/test', 'SmtpController::test');
});

// Any authenticated role: account settings act only on the current user.
$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->get('settings', 'SettingsController::index');
    $routes->post('settings/password', 'SettingsController::updatePassword');
});
