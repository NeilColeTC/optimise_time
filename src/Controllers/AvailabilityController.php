<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfToken;
use App\Core\Database;
use App\Core\Flash;
use Throwable;

final class AvailabilityController
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
        $teachers = $this->database->all('SELECT id, first_name, last_name FROM teachers WHERE is_active = 1 ORDER BY last_name, first_name');
        $requestedId = filter_input(INPUT_GET, 'teacher_id', FILTER_VALIDATE_INT);
        $teacherId = $requestedId ?: (int) ($teachers[0]['id'] ?? 0);
        if ($teacherId && !in_array($teacherId, array_map('intval', array_column($teachers, 'id')), true)) {
            $teacherId = (int) ($teachers[0]['id'] ?? 0);
        }
        $selectedTeacher = null;
        foreach ($teachers as $teacher) {
            if ((int) $teacher['id'] === $teacherId) {
                $selectedTeacher = $teacher;
                break;
            }
        }
        $days = $this->database->all('SELECT id, name FROM week_days WHERE is_teaching_day = 1 ORDER BY sort_order');
        $slots = $this->database->all('SELECT id, label, starts_at, ends_at FROM time_slots WHERE is_teaching_slot = 1 ORDER BY starts_at');
        $availability = [];
        if ($teacherId) {
            foreach ($this->database->all('SELECT week_day_id, time_slot_id, availability FROM teacher_availability WHERE teacher_id = ?', [$teacherId]) as $row) {
                $availability[(int) $row['week_day_id']][(int) $row['time_slot_id']] = $row['availability'];
            }
        }
        $flash = Flash::consume();
        $csrfToken = CsrfToken::value();
        require dirname(__DIR__) . '/Views/planning/availability.php';
    }

    public function save(): void
    {
        if (!$this->database->isAvailable()) {
            $this->redirect('/availability');
        }
        if (!CsrfToken::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Your session token has expired. Return to the form and try again.');
        }
        $teacherId = filter_var($_POST['teacher_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$teacherId || !$this->database->one('SELECT id FROM teachers WHERE id = ? AND is_active = 1', [$teacherId])) {
            Flash::set('error', 'Choose an active teacher before saving availability.');
            $this->redirect('/availability');
        }
        $days = array_map('intval', array_column($this->database->all('SELECT id FROM week_days WHERE is_teaching_day = 1'), 'id'));
        $slots = array_map('intval', array_column($this->database->all('SELECT id FROM time_slots WHERE is_teaching_slot = 1'), 'id'));
        $submitted = $_POST['availability'] ?? [];
        if (!is_array($submitted)) {
            Flash::set('error', 'Availability details were not submitted correctly.');
            $this->redirect('/availability?teacher_id=' . $teacherId);
        }
        $values = [];
        foreach ($submitted as $dayId => $slotValues) {
            $dayId = filter_var($dayId, FILTER_VALIDATE_INT);
            if (!$dayId || !in_array($dayId, $days, true) || !is_array($slotValues)) {
                continue;
            }
            foreach ($slotValues as $slotId => $status) {
                $slotId = filter_var($slotId, FILTER_VALIDATE_INT);
                if (!$slotId || !in_array($slotId, $slots, true) || !in_array($status, ['available', 'unavailable', 'preferred'], true)) {
                    continue;
                }
                if ($status !== 'available') {
                    $values[] = [$teacherId, $dayId, $slotId, $status];
                }
            }
        }
        try {
            $this->database->transaction(function (Database $database) use ($teacherId, $values): void {
                $database->execute('DELETE FROM teacher_availability WHERE teacher_id = ?', [$teacherId]);
                foreach ($values as [$staffId, $dayId, $slotId, $status]) {
                    $database->execute(
                        'INSERT INTO teacher_availability (teacher_id, week_day_id, time_slot_id, availability) VALUES (?, ?, ?, ?)',
                        [$staffId, $dayId, $slotId, $status]
                    );
                }
            });
            Flash::set('success', 'Teacher availability saved.');
        } catch (Throwable $exception) {
            Flash::set('error', 'Availability could not be saved. Check that the week structure is configured.');
        }
        $this->redirect('/availability?teacher_id=' . $teacherId);
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}

