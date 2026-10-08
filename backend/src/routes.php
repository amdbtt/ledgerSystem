<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\ClientController;
use App\Controllers\InvoiceController;
use App\Controllers\PaymentController;
use App\Controllers\PaymentModeController;
use App\Controllers\QuoteController;
use App\Controllers\SettingController;
use App\Controllers\TaxController;
use App\Config;
use App\Response;
use App\Router;

$router = new Router();

// Auth (public)
$router->post('/api/login', static fn ($req) => AuthController::login($req), false);
$router->post('/api/forgetpassword', static fn ($req) => AuthController::forgetPassword($req), false);
$router->post('/api/resetpassword', static fn ($req) => AuthController::resetPassword($req), false);

// Auth (protected)
$router->post('/api/logout', static fn ($req) => AuthController::logout($req));
$router->patch('/api/admin/profile/update', static fn ($req) => AuthController::updateProfile($req));
$router->patch('/api/admin/profile/password', static fn ($req) => AuthController::updatePassword($req));

// Settings
$router->get('/api/setting/listAll', static fn ($req) => SettingController::listAll($req));
$router->patch('/api/setting/updateBySettingKey/{key}', static fn ($req, $p) => SettingController::updateBySettingKey($req, $p));
$router->patch('/api/setting/updateManySetting', static fn ($req) => SettingController::updateMany($req));
$router->post('/api/setting/upload/{key}', static fn ($req, $p) => SettingController::upload($req, $p));

$entities = [
    'client' => ClientController::class,
    'taxes' => TaxController::class,
    'paymentMode' => PaymentModeController::class,
    'invoice' => InvoiceController::class,
    'quote' => QuoteController::class,
    'payment' => PaymentController::class,
];

foreach ($entities as $entity => $class) {
    $router->post("/api/{$entity}/create", static fn ($req) => $class::create($req));
    $router->get("/api/{$entity}/read/{id}", static fn ($req, $p) => $class::read($req, $p));
    $router->patch("/api/{$entity}/update/{id}", static fn ($req, $p) => $class::update($req, $p));
    $router->delete("/api/{$entity}/delete/{id}", static fn ($req, $p) => $class::delete($req, $p));
    $router->get("/api/{$entity}/list", static fn ($req) => $class::list($req));
    $router->get("/api/{$entity}/listAll", static fn ($req) => $class::listAll($req));
    $router->get("/api/{$entity}/search", static fn ($req) => $class::search($req));
    $router->get("/api/{$entity}/filter", static fn ($req) => $class::filter($req));
    $router->get("/api/{$entity}/summary", static fn ($req) => $class::summary($req));
}

$router->get('/api/quote/convert/{id}', static fn ($req, $p) => QuoteController::convert($req, $p));
$router->post('/api/invoice/mail', static fn ($req) => InvoiceController::mail($req));
$router->post('/api/quote/mail', static fn ($req) => QuoteController::mail($req));
$router->post('/api/payment/mail', static fn ($req) => PaymentController::mail($req));

// PDF download placeholder
$router->get('/download/{type}/{name}', static function ($req, $p) {
    if (!Config::bool('PDF_ENABLED', false)) {
        Response::fail('PDF download is not enabled yet. Set PDF_ENABLED=true after Dompdf is installed.', 501);
    }
    Response::fail('PDF generation is not configured', 501);
}, true);

// Health check
$router->get('/api/health', static function () {
    Response::ok(['status' => 'ok', 'time' => date('c')]);
}, false);

return $router;
