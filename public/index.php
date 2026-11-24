<?php

declare(strict_types=1);

use App\Controllers\DashboardController;
use App\Controllers\TeacherController;
use App\Core\Database;
use App\Core\Router;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/Core/env.php';

session_start();

$router = new Router();
$database = Database::fromEnvironment();
$router->get('/', static function (): void {
    (new DashboardController(Database::fromEnvironment()))->index();
});
$router->get('/teachers', static fn () => (new TeacherController($database))->index());
$router->get('/teachers/new', static fn () => (new TeacherController($database))->create());
$router->get('/teachers/edit', static fn () => (new TeacherController($database))->edit());
$router->post('/teachers', static fn () => (new TeacherController($database))->store());
$router->post('/teachers/update', static fn () => (new TeacherController($database))->update());
$router->post('/teachers/delete', static fn () => (new TeacherController($database))->delete());

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

