<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', static fn () => redirect()->to('/login'));

// --- Auth ---------------------------------------------------------
$routes->get('login', 'AuthController::showLogin');
$routes->post('login', 'AuthController::login');
$routes->post('logout', 'AuthController::logout');

$routes->get('forgot-password', 'AuthController::showForgotPassword');
$routes->post('forgot-password', 'AuthController::requestReset');
$routes->get('forgot-password/verify', 'AuthController::showVerifyReset');
$routes->post('forgot-password/verify', 'AuthController::verifyReset');
$routes->get('forgot-password/new', 'AuthController::showNewPassword');
$routes->post('forgot-password/new', 'AuthController::submitNewPassword');

$routes->group('account', ['filter' => 'authGuard'], static function ($routes) {
    $routes->get('set-password', 'AccountController::showSetPassword');
    $routes->post('set-password', 'AccountController::setPassword');
    $routes->post('set-language', 'AccountController::setLanguage');
});

// --- Payment gateway webhooks (no auth — verified by signature) ---
$routes->post('webhooks/cashfree/(:segment)', 'PaymentController::cashfreeWebhook/$1');

// --- Public receipt link, shared over SMS/WhatsApp (no auth — token is a random, non-enumerable value) ---
$routes->get('h/rid=(:segment)', 'PublicReceiptController::show/$1');
$routes->get('h/rid=(:segment)/pdf', 'PublicReceiptController::pdf/$1');

// --- Public info pages, linked from the login screen (no auth) ---
$routes->get('about', 'PagesController::about');
$routes->get('contact-us', 'PagesController::contactUs');
$routes->get('privacypolicy', 'PagesController::privacyPolicy');
$routes->get('termsconditions', 'PagesController::termsConditions');
$routes->get('services', 'PagesController::services');

// --- Cascading location dropdowns (JSON), used by enrolment + masters forms ---
$routes->group('api/locations', ['filter' => 'authGuard'], static function ($routes) {
    $routes->get('jilas/(:num)', 'Api\LocationApiController::jilas/$1');
    $routes->get('prakhands/(:num)', 'Api\LocationApiController::prakhands/$1');
});

// --- New Enrolment form live checks (JSON) ---
$routes->group('api/members', ['filter' => 'authGuard'], static function ($routes) {
    $routes->get('check-hc-phone/(:num)', 'Api\MemberApiController::checkHcPhone/$1');
});

// --- Admin surface --------------------------------------------------
$routes->group('admin', ['filter' => 'authGuard'], static function ($routes) {
    $routes->get('/', 'DashboardController::index', ['filter' => 'permission:Dashboard,Own']);

    $routes->group('masters', ['filter' => 'permission:Masters,View'], static function ($routes) {
        $routes->get('/', 'MastersController::index');
        $routes->get('programmes/new', 'MastersController::newProgramme', ['filter' => 'permission:Masters,Edit']);
        $routes->post('programmes', 'MastersController::createProgramme', ['filter' => 'permission:Masters,Edit']);
        $routes->get('programmes/(:num)/edit', 'MastersController::editProgramme/$1');
        $routes->post('programmes/(:num)', 'MastersController::updateProgramme/$1');
        $routes->post('programmes/(:num)/delete', 'MastersController::deleteProgramme/$1', ['filter' => 'permission:Masters,Edit']);
        $routes->get('locations/bulk-import/template', 'MastersController::downloadLocationTemplate');
        $routes->post('locations/bulk-import', 'MastersController::bulkImportLocations', ['filter' => 'permission:Masters,Edit']);
        $routes->post('locations/prant', 'MastersController::addPrant');
        $routes->post('locations/prant/(:num)/rename', 'MastersController::renamePrant/$1');
        $routes->post('locations/prant/(:num)/languages', 'MastersController::updatePrantLanguages/$1');
        $routes->post('locations/prant/(:num)/delete', 'MastersController::deletePrant/$1');
        $routes->post('locations/jila', 'MastersController::addJila');
        $routes->post('locations/jila/(:num)/rename', 'MastersController::renameJila/$1');
        $routes->post('locations/jila/(:num)/delete', 'MastersController::deleteJila/$1');
        $routes->post('locations/prakhand', 'MastersController::addPrakhand');
        $routes->post('locations/prakhand/(:num)/rename', 'MastersController::renamePrakhand/$1');
        $routes->post('locations/prakhand/(:num)/delete', 'MastersController::deletePrakhand/$1');
    });

    $routes->group('users', ['filter' => 'permission:Users & Hierarchy,View'], static function ($routes) {
        $routes->get('/', 'UsersController::index');
        $routes->post('/', 'UsersController::create', ['filter' => 'permission:Users & Hierarchy,Edit']);
        $routes->post('draft', 'UsersController::saveDraft', ['filter' => 'permission:Users & Hierarchy,Edit']);
        $routes->get('draft/(:segment)', 'UsersController::getDraft/$1', ['filter' => 'permission:Users & Hierarchy,Edit']);
        $routes->post('draft/(:segment)/delete', 'UsersController::deleteDraft/$1', ['filter' => 'permission:Users & Hierarchy,Edit']);
        $routes->get('(:num)/edit', 'UsersController::showEdit/$1', ['filter' => 'permission:Users & Hierarchy,Edit']);
        $routes->post('(:num)', 'UsersController::update/$1', ['filter' => 'permission:Users & Hierarchy,Edit']);
        $routes->post('(:num)/block', 'UsersController::toggleBlock/$1', ['filter' => 'permission:Users & Hierarchy,Edit']);
        $routes->post('(:num)/reset-password', 'UsersController::resetPassword/$1', ['filter' => 'permission:Users & Hierarchy,Edit']);
        $routes->get('(:num)/password', 'UsersController::viewPassword/$1', ['filter' => 'permission:Users & Hierarchy,Edit']);
    });

    $routes->group('enrolments', ['filter' => 'permission:Enrolments,Own'], static function ($routes) {
        $routes->get('/', 'EnrolmentController::index');
        $routes->get('new', 'EnrolmentController::newForm');
        $routes->get('subscriptions', 'EnrolmentController::subscriptions');
        $routes->post('/', 'EnrolmentController::store');
        $routes->post('draft', 'EnrolmentController::saveDraft');
        $routes->get('draft/(:segment)', 'EnrolmentController::getDraft/$1');
        $routes->post('draft/(:segment)/delete', 'EnrolmentController::deleteDraft/$1');
        $routes->get('(:num)/otp', 'EnrolmentController::showOtp/$1');
        $routes->post('(:num)/otp/send', 'EnrolmentController::sendOtp/$1');
        $routes->post('(:num)/otp/verify', 'EnrolmentController::verifyOtp/$1');
        $routes->get('(:num)/payment', 'EnrolmentController::showPayment/$1');
        $routes->post('(:num)/pay/upi', 'EnrolmentController::payUpi/$1');
        $routes->get('(:num)/pay/return', 'EnrolmentController::payReturn/$1');
        $routes->post('(:num)/pay/cash', 'EnrolmentController::payCash/$1');
        $routes->post('(:num)/pay/autopay', 'EnrolmentController::payAutopay/$1');
        $routes->get('(:num)/pay/autopay/return', 'EnrolmentController::autopayReturn/$1');
        $routes->get('(:num)/remit', 'EnrolmentController::payRemit/$1');
        $routes->get('(:num)/remit/return', 'EnrolmentController::remitReturn/$1');
        $routes->get('(:num)/receipt', 'EnrolmentController::receipt/$1');
        $routes->get('(:num)/receipt.pdf', 'EnrolmentController::receiptPdf/$1');
        $routes->post('(:num)/receipt/send/(:alpha)', 'EnrolmentController::sendReceipt/$1/$2');
        $routes->get('export.csv', 'EnrolmentController::exportCsv');
        $routes->post('remit-cash', 'EnrolmentController::remitCash');
    });

    $routes->group('reports', ['filter' => 'permission:Reports,View'], static function ($routes) {
        $routes->get('/', 'ReportController::index');
        $routes->get('export.csv', 'ReportController::exportCsv');
        $routes->get('export.xlsx', 'ReportController::exportExcel');
        $routes->get('export.pdf', 'ReportController::exportPdf');
        $routes->post('templates', 'ReportController::saveTemplate');
        $routes->post('templates/(:num)/delete', 'ReportController::deleteTemplate/$1');
    });

    $routes->group('settings', ['filter' => 'permission:Settings & Integrations,Full'], static function ($routes) {
        $routes->get('/', 'SettingsController::trust');
        $routes->get('trust', 'SettingsController::trust');
        $routes->post('trust/(:num)', 'SettingsController::saveTrust/$1');
        $routes->get('trust/bulk-import/template', 'SettingsController::downloadTrustTemplate');
        $routes->post('trust/bulk-import', 'SettingsController::bulkImportTrust');
        $routes->get('payment', 'SettingsController::payment');
        $routes->post('payment/(:num)', 'SettingsController::savePayment/$1');
        $routes->get('sms', 'SettingsController::sms');
        $routes->post('sms', 'SettingsController::saveSms');
        $routes->post('sms/test', 'SettingsController::testSms');
        $routes->post('sms/templates/(:num)', 'SettingsController::updateSmsTemplate/$1');
        $routes->get('whatsapp', 'SettingsController::whatsapp');
        $routes->post('whatsapp', 'SettingsController::saveWhatsapp');
        $routes->post('whatsapp/test', 'SettingsController::testWhatsapp');
        $routes->post('whatsapp/templates/(:num)', 'SettingsController::updateWhatsappTemplate/$1');
        $routes->get('email', 'SettingsController::email');
        $routes->post('email', 'SettingsController::saveEmail');
        $routes->post('email/test', 'SettingsController::testEmail');
        $routes->get('roles', 'SettingsController::roles');
        $routes->post('roles/update', 'SettingsController::updatePermission');
    });
});

// --- Karyakarta mobile-first surface (own enrolments/collections) ---
$routes->group('app', ['filter' => 'authGuard'], static function ($routes) {
    $routes->get('/', 'KaryakartaController::home');
    $routes->get('collections', 'KaryakartaController::collections');
    $routes->get('profile', 'KaryakartaController::profile');
});
