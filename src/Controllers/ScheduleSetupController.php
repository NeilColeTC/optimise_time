<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfToken;
use App\Core\Database;
use App\Core\Flash;
use DateTimeImmutable;
use Throwable;

final class ScheduleSetupController
{
    public function __construct(private Database $database)
    {
    }

    public function index(): void
    {
        if (!$this->database->isAvailable()) {
            $databaseMessage = $this->database->errorMessage();
            require dirname(__DIR__) . '/Views/database-unavailable.php';
            return;
        }
        $days = $this->database->all('SELECT * FROM week_days ORDER BY sort_order');
        $slots = $this->database->all('SELECT * FROM time_slots ORDER BY starts_at');
        $flash = Flash::consume();
        $csrfToken = CsrfToken::value();
        require dirname(__DIR__) . '/Views/planning/week.php';
    }

    public function addDay(): void
    {
        $this->requireDatabaseAndCsrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || strlen($name) > 20) {
            Flash::set('error', 'Enter a day name of no more than 20 characters.');
            $this->redirect('/week');
        }
        try {
            $order = (int) ($this->database->one('SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM week_days')['next_order'] ?? 1);
            $this->database->execute('INSERT INTO week_days (name, sort_order, is_teaching_day) VALUES (?, ?, 1)', [$name, $order]);
            Flash::set('success', 'Teaching day added.');
        } catch (Throwable $exception) {
            Flash::set('error', 'The day could not be added. Day names must be unique.');
        }
        $this->redirect('/week');
    }

    public function updateDay(): void
    {
        $this->requireDatabaseAndCsrf();
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string) ($_POST['name'] ?? ''));
        if (!$id || $name === '' || strlen($name) > 20) {
            Flash::set('error', 'Check the day details.');
            $this->redirect('/week');
        }
        try {
            $this->database->execute('UPDATE week_days SET name = ?, is_teaching_day = ? WHERE id = ?', [$name, isset($_POST['is_teaching_day']) ? 1 : 0, $id]);
            Flash::set('success', 'Teaching day updated.');
        } catch (Throwable $exception) {
            Flash::set('error', 'The day could not be updated. Day names must be unique.');
        }
        $this->redirect('/week');
    }

    public function addSlot(): void
    {
        $this->requireDatabaseAndCsrf();
        $label = trim((string) ($_POST['label'] ?? ''));
        $start = trim((string) ($_POST['starts_at'] ?? ''));
        $end = trim((string) ($_POST['ends_at'] ?? ''));
        $duration = $this->duration($start, $end);
        if ($label === '' || strlen($label) > 80 || $duration === null) {
            Flash::set('error', 'Enter a period label and a valid start and end time.');
            $this->redirect('/week');
        }
        if ($this->overlapsExisting($start, $end)) {
            Flash::set('error', 'This period overlaps an existing time block. Adjust its start or end time.');
            $this->redirect('/week');
        }
        try {
            $order = (int) ($this->database->one('SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM time_slots')['next_order'] ?? 1);
            $this->database->execute(
                'INSERT INTO time_slots (label, starts_at, ends_at, duration_minutes, sort_order, is_teaching_slot) VALUES (?, ?, ?, ?, ?, ?)',
                [$label, $start, $end, $duration, $order, isset($_POST['is_teaching_slot']) ? 1 : 0]
            );
            Flash::set('success', 'Time block added.');
        } catch (Throwable $exception) {
            Flash::set('error', 'The time block could not be added.');
        }
        $this->redirect('/week');
    }

    public function updateSlot(): void
    {
        $this->requireDatabaseAndCsrf();
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $label = trim((string) ($_POST['label'] ?? ''));
        $start = trim((string) ($_POST['starts_at'] ?? ''));
        $end = trim((string) ($_POST['ends_at'] ?? ''));
        $duration = $this->duration($start, $end);
        if (!$id || $label === '' || strlen($label) > 80 || $duration === null) {
            Flash::set('error', 'Check the time block details.');
            $this->redirect('/week');
        }
        if ($this->overlapsExisting($start, $end, $id)) {
            Flash::set('error', 'This time block overlaps another block. Adjust its start or end time.');
            $this->redirect('/week');
        }
        try {
            $this->database->execute(
                'UPDATE time_slots SET label = ?, starts_at = ?, ends_at = ?, duration_minutes = ?, is_teaching_slot = ? WHERE id = ?',
                [$label, $start, $end, $duration, isset($_POST['is_teaching_slot']) ? 1 : 0, $id]
            );
            Flash::set('success', 'Time block updated.');
        } catch (Throwable $exception) {
            Flash::set('error', 'The time block could not be updated.');
        }
        $this->redirect('/week');
    }

    private function requireDatabaseAndCsrf(): void
    {
        if (!$this->database->isAvailable()) {
            $this->redirect('/week');
        }
        if (!CsrfToken::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Your session token has expired. Return to the form and try again.');
        }
    }

    private function duration(string $start, string $end): ?int
    {
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $start)
            || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $end)) {
            return null;
        }
        $startTime = DateTimeImmutable::createFromFormat('!H:i', $start);
        $endTime = DateTimeImmutable::createFromFormat('!H:i', $end);
        if (!$startTime || !$endTime || $endTime <= $startTime) {
            return null;
        }
        $minutes = (int) (($endTime->getTimestamp() - $startTime->getTimestamp()) / 60);
        return $minutes > 0 ? $minutes : null;
    }

    private function overlapsExisting(string $start, string $end, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM time_slots WHERE starts_at < ? AND ends_at > ?';
        $parameters = [$end, $start];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $parameters[] = $excludeId;
        }
        return $this->database->one($sql . ' LIMIT 1', $parameters) !== null;
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}

