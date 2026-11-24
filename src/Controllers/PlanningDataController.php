<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfToken;
use App\Core\Database;
use App\Core\Flash;
use Throwable;

final class PlanningDataController
{
    public function __construct(private Database $database)
    {
    }

    public function demand(): void
    {
        if (!$this->database->isAvailable()) {
            $this->showDatabaseUnavailable();
            return;
        }
        $requirements = $this->database->all(
            'SELECT r.id, r.weekly_minutes, r.session_length_minutes, r.required_room_type, g.name AS group_name, g.cohort_size,
                s.name AS subject_name, s.code AS subject_code
             FROM group_subject_requirements r
             JOIN teaching_groups g ON g.id = r.teaching_group_id
             JOIN subjects s ON s.id = r.subject_id
             ORDER BY g.name, s.name'
        );
        $editId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $edit = $editId ? $this->database->one(
            'SELECT r.id, r.weekly_minutes, r.session_length_minutes, r.required_room_type, g.name AS group_name, g.cohort_size, s.name AS subject_name
             FROM group_subject_requirements r
             JOIN teaching_groups g ON g.id = r.teaching_group_id
             JOIN subjects s ON s.id = r.subject_id WHERE r.id = ?',
            [$editId]
        ) : null;
        if ($edit) {
            $edit['weekly_hours'] = number_format(((int) $edit['weekly_minutes']) / 60, 2, '.', '');
        }
        $flash = Flash::consume();
        $csrfToken = CsrfToken::value();
        require dirname(__DIR__) . '/Views/planning/demand.php';
    }

    public function saveDemand(): void
    {
        if (!$this->database->isAvailable()) {
            $this->redirect('/demand');
        }
        $this->requireCsrf();
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $groupName = trim((string) ($_POST['group_name'] ?? ''));
        $subjectName = trim((string) ($_POST['subject_name'] ?? ''));
        $cohortSize = filter_var($_POST['cohort_size'] ?? null, FILTER_VALIDATE_INT);
        $hours = filter_var($_POST['weekly_hours'] ?? null, FILTER_VALIDATE_FLOAT);
        $sessionLength = filter_var($_POST['session_length_minutes'] ?? null, FILTER_VALIDATE_INT);
        $roomType = trim((string) ($_POST['required_room_type'] ?? ''));
        if ($groupName === '' || strlen($groupName) > 120 || $subjectName === '' || strlen($subjectName) > 160
            || $cohortSize === false || $cohortSize < 0 || $cohortSize > 5000
            || $hours === false || $hours <= 0 || $hours > 168 || $sessionLength === false || $sessionLength < 15 || $sessionLength > 240
            || ((int) round((float) $hours * 60)) % $sessionLength !== 0 || strlen($roomType) > 100) {
            Flash::set('error', 'Check the group, subject, cohort size, weekly hours, session length, and room type. Weekly hours must divide into whole sessions.');
            $this->redirect('/demand' . ($id ? '?id=' . $id : ''));
        }

        try {
            $this->database->transaction(function (Database $database) use ($id, $groupName, $subjectName, $cohortSize, $hours, $sessionLength, $roomType): void {
                $group = $database->one('SELECT id FROM teaching_groups WHERE name = ?', [$groupName]);
                if (!$group) {
                    $database->execute('INSERT INTO teaching_groups (name, cohort_size) VALUES (?, ?)', [$groupName, $cohortSize]);
                    $groupId = $database->insertId();
                } else {
                    $groupId = (int) $group['id'];
                    $database->execute('UPDATE teaching_groups SET cohort_size = ? WHERE id = ?', [$cohortSize, $groupId]);
                }

                $subject = $database->one('SELECT id FROM subjects WHERE name = ?', [$subjectName]);
                if (!$subject) {
                    $database->execute('INSERT INTO subjects (name) VALUES (?)', [$subjectName]);
                    $subjectId = $database->insertId();
                } else {
                    $subjectId = (int) $subject['id'];
                }
                $minutes = (int) round((float) $hours * 60);
                if ($id) {
                    $database->execute(
                        'UPDATE group_subject_requirements SET teaching_group_id = ?, subject_id = ?, weekly_minutes = ?, session_length_minutes = ?, required_room_type = ? WHERE id = ?',
                        [$groupId, $subjectId, $minutes, $sessionLength, $roomType !== '' ? $roomType : null, $id]
                    );
                } else {
                    $database->execute(
                        'INSERT INTO group_subject_requirements (teaching_group_id, subject_id, weekly_minutes, session_length_minutes, required_room_type)
                         VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE weekly_minutes = VALUES(weekly_minutes), session_length_minutes = VALUES(session_length_minutes), required_room_type = VALUES(required_room_type)',
                        [$groupId, $subjectId, $minutes, $sessionLength, $roomType !== '' ? $roomType : null]
                    );
                }
            });
        } catch (Throwable $exception) {
            Flash::set('error', 'The teaching requirement could not be saved. Check that the database schema is up to date.');
            $this->redirect('/demand' . ($id ? '?id=' . $id : ''));
        }
        Flash::set('success', 'Teaching requirement saved.');
        $this->redirect('/demand');
    }

    public function deleteDemand(): void
    {
        $this->requireCsrf();
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $this->database->execute('DELETE FROM group_subject_requirements WHERE id = ?', [$id]);
            Flash::set('success', 'Teaching requirement removed.');
        }
        $this->redirect('/demand');
    }

    public function rooms(): void
    {
        if (!$this->database->isAvailable()) {
            $this->showDatabaseUnavailable();
            return;
        }
        $rooms = $this->database->all('SELECT * FROM rooms ORDER BY is_active DESC, name');
        $flash = Flash::consume();
        $csrfToken = CsrfToken::value();
        require dirname(__DIR__) . '/Views/planning/rooms.php';
    }

    public function saveRoom(): void
    {
        if (!$this->database->isAvailable()) {
            $this->redirect('/rooms');
        }
        $this->requireCsrf();
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $name = trim((string) ($_POST['name'] ?? ''));
        $capacity = filter_var($_POST['capacity'] ?? null, FILTER_VALIDATE_INT);
        $type = trim((string) ($_POST['room_type'] ?? ''));
        if ($name === '' || strlen($name) > 120 || $capacity === false || $capacity < 1 || $capacity > 5000 || strlen($type) > 100) {
            Flash::set('error', 'Enter a room name, a capacity from 1 to 5000, and a room type of no more than 100 characters.');
            $this->redirect('/rooms');
        }
        try {
            if ($id) {
                $this->database->execute('UPDATE rooms SET name = ?, capacity = ?, room_type = ?, is_active = ? WHERE id = ?', [$name, $capacity, $type !== '' ? $type : null, isset($_POST['is_active']) ? 1 : 0, $id]);
                Flash::set('success', 'Room updated.');
            } else {
                $this->database->execute('INSERT INTO rooms (name, capacity, room_type) VALUES (?, ?, ?)', [$name, $capacity, $type !== '' ? $type : null]);
                Flash::set('success', 'Room added.');
            }
        } catch (Throwable $exception) {
            Flash::set('error', 'The room could not be saved. Room names must be unique.');
        }
        $this->redirect('/rooms');
    }

    public function deactivateRoom(): void
    {
        if (!$this->database->isAvailable()) {
            $this->redirect('/rooms');
        }
        $this->requireCsrf();
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $this->database->execute('UPDATE rooms SET is_active = 0 WHERE id = ?', [$id]);
            Flash::set('success', 'Room deactivated. Existing timetable records are retained.');
        }
        $this->redirect('/rooms');
    }

    private function requireCsrf(): void
    {
        if (!CsrfToken::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Your session token has expired. Return to the form and try again.');
        }
    }

    private function showDatabaseUnavailable(): void
    {
        $databaseMessage = $this->database->errorMessage();
        require dirname(__DIR__) . '/Views/database-unavailable.php';
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}

