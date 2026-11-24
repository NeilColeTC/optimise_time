<?php

declare(strict_types=1);

use App\Controllers\DashboardController;
use App\Controllers\EveningDutyController;
use App\Controllers\AvailabilityController;
use App\Controllers\PlanningDataController;
use App\Controllers\RulesController;
use App\Controllers\ScheduleSetupController;
use App\Controllers\TeacherController;
use App\Core\Database;
use App\Core\Router;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/Core/env.php';

session_start();

$router = new Router();
$database = Database::fromEnvironment();
$planningData = new PlanningDataController($database);
$scheduleSetup = new ScheduleSetupController($database);
$availability = new AvailabilityController($database);
$rules = new RulesController($database);
$evenings = new EveningDutyController($database);
$teachers = new TeacherController($database);
$router->get('/', static function (): void {
    (new DashboardController(Database::fromEnvironment()))->index();
});
$router->get('/teachers', static fn () => $teachers->index());
$router->get('/teachers/new', static fn () => $teachers->create());
$router->get('/teachers/edit', static fn () => $teachers->edit());
$router->post('/teachers', static fn () => $teachers->store());
$router->post('/teachers/update', static fn () => $teachers->update());
$router->post('/teachers/delete', static fn () => $teachers->delete());
$router->get('/demand', static fn () => $planningData->demand());
$router->post('/demand/save', static fn () => $planningData->saveDemand());
$router->post('/demand/delete', static fn () => $planningData->deleteDemand());
$router->get('/rooms', static fn () => $planningData->rooms());
$router->post('/rooms/save', static fn () => $planningData->saveRoom());
$router->post('/rooms/deactivate', static fn () => $planningData->deactivateRoom());
$router->get('/week', static fn () => $scheduleSetup->index());
$router->post('/week/day/add', static fn () => $scheduleSetup->addDay());
$router->post('/week/day/update', static fn () => $scheduleSetup->updateDay());
$router->post('/week/slot/add', static fn () => $scheduleSetup->addSlot());
$router->post('/week/slot/update', static fn () => $scheduleSetup->updateSlot());
$router->get('/availability', static fn () => $availability->index());
$router->post('/availability/save', static fn () => $availability->save());
$router->get('/rules', static fn () => $rules->index());
$router->post('/rules/save', static fn () => $rules->save());
$router->get('/evenings', static fn () => $evenings->index());
$router->post('/evenings/add', static fn () => $evenings->add());
$router->post('/evenings/toggle', static fn () => $evenings->toggle());

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

