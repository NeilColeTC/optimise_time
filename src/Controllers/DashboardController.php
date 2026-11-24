<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

final class DashboardController
{
    public function __construct(private Database $database)
    {
    }

    public function index(): void
    {
        $stats = [
            'teachers' => $this->database->count('teachers'),
            'courses' => $this->database->count('courses'),
            'rooms' => $this->database->count('rooms'),
            'sessions' => $this->database->count('scheduled_sessions'),
        ];
        $databaseMessage = $this->database->errorMessage();
        $appName = getenv('APP_NAME') ?: 'Academic Timetable Optimiser';
        require dirname(__DIR__) . '/Views/dashboard/index.php';
    }
}
