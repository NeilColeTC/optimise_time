<?php

declare(strict_types=1);

use App\Controllers\DashboardController;
use App\Core\Database;
use App\Core\Router;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/Core/env.php';

session_start();

$router = new Router();
$router->get('/', static function (): void {
    $database = Database::fromEnvironment();
    (new DashboardController($database))->index();
});

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

