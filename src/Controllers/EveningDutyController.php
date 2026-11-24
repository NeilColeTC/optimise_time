<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfToken;
use App\Core\Database;
use App\Core\Flash;
use Throwable;

final class EveningDutyController
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
        $days = $this->database->all('SELECT id, name FROM week_days WHERE is_teaching_day = 1 ORDER BY sort_order');
        $rule = $this->database->one('SELECT configuration, is_active FROM timetable_rules WHERE rule_key = ?', ['evening_lieu']);
        $configuration = $rule ? json_decode((string) $rule['configuration'], true) : [];
        $slotIds = is_array($configuration) ? array_map('intval', $configuration['evening_slot_ids'] ?? []) : [];
        $slots = [];
        if ($slotIds !== []) {
            $placeholders = implode(',', array_fill(0, count($slotIds), '?'));
            $slots = $this->database->all("SELECT id, label, starts_at, ends_at FROM time_slots WHERE id IN ({$placeholders}) ORDER BY starts_at", $slotIds);
        }
        $duties = $this->database->all(
            'SELECT e.id, e.is_active, t.first_name, t.last_name, d.name AS day_name, s.label AS slot_label, s.starts_at, s.ends_at
             FROM teacher_evening_duties e JOIN teachers t ON t.id = e.teacher_id
             JOIN week_days d ON d.id = e.week_day_id JOIN time_slots s ON s.id = e.time_slot_id
             ORDER BY e.is_active DESC, d.sort_order, s.starts_at, t.last_name'
        );
        $ruleEnabled = (int) ($rule['is_active'] ?? 0) === 1;
        $flash = Flash::consume();
        $csrfToken = CsrfToken::value();
        require dirname(__DIR__) . '/Views/planning/evenings.php';
    }

    public function add(): void
    {
        if (!$this->database->isAvailable()) {
            $this->redirect('/evenings');
        }
        if (!CsrfToken::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Your session token has expired. Return to the form and try again.');
        }
        $teacherId = filter_var($_POST['teacher_id'] ?? null, FILTER_VALIDATE_INT);
        $dayId = filter_var($_POST['week_day_id'] ?? null, FILTER_VALIDATE_INT);
        $slotId = filter_var($_POST['time_slot_id'] ?? null, FILTER_VALIDATE_INT);
        $rule = $this->database->one('SELECT configuration, is_active FROM timetable_rules WHERE rule_key = ?', ['evening_lieu']);
        $configuration = $rule ? json_decode((string) $rule['configuration'], true) : [];
        $allowedSlots = is_array($configuration) ? array_map('intval', $configuration['evening_slot_ids'] ?? []) : [];
        if (!$teacherId || !$dayId || !$slotId || (int) ($rule['is_active'] ?? 0) !== 1
            || !in_array($slotId, $allowedSlots, true)
            || !$this->database->one('SELECT id FROM teachers WHERE id = ? AND is_active = 1', [$teacherId])
            || !$this->database->one('SELECT id FROM week_days WHERE id = ? AND is_teaching_day = 1', [$dayId])) {
            Flash::set('error', 'Enable the evening rule and choose an active teacher, teaching day, and configured evening period.');
            $this->redirect('/evenings');
        }
        try {
            $this->database->execute('INSERT INTO teacher_evening_duties (teacher_id, week_day_id, time_slot_id, is_active) VALUES (?, ?, ?, 1)', [$teacherId, $dayId, $slotId]);
            Flash::set('success', 'Recurring evening duty added.');
        } catch (Throwable $exception) {
            Flash::set('error', 'This teacher already has that evening duty, or the assignment could not be saved.');
        }
        $this->redirect('/evenings');
    }

    public function toggle(): void
    {
        if (!$this->database->isAvailable()) {
            $this->redirect('/evenings');
        }
        if (!CsrfToken::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Your session token has expired. Return to the form and try again.');
        }
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $this->database->execute('UPDATE teacher_evening_duties SET is_active = 1 - is_active WHERE id = ?', [$id]);
            Flash::set('success', 'Evening duty status updated.');
        }
        $this->redirect('/evenings');
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}

