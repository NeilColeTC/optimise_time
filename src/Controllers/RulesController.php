<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfToken;
use App\Core\Database;
use App\Core\Flash;
use Throwable;

final class RulesController
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
        $days = $this->database->all('SELECT id, name FROM week_days WHERE is_teaching_day = 1 ORDER BY sort_order');
        $slots = $this->database->all('SELECT id, label, starts_at, ends_at, is_teaching_slot FROM time_slots ORDER BY starts_at');
        $rule = $this->database->one('SELECT configuration, is_active FROM timetable_rules WHERE rule_key = ?', ['evening_lieu']);
        $configuration = $rule ? json_decode((string) $rule['configuration'], true) : [];
        if (!is_array($configuration)) {
            $configuration = [];
        }
        $active = (int) ($rule['is_active'] ?? 0) === 1;
        $eveningSlotIds = array_map('intval', $configuration['evening_slot_ids'] ?? []);
        $lieuDayIds = array_map('intval', $configuration['lieu_day_ids'] ?? []);
        $lieuHours = isset($configuration['lieu_minutes']) ? number_format(((int) $configuration['lieu_minutes']) / 60, 2, '.', '') : '';
        $flash = Flash::consume();
        $csrfToken = CsrfToken::value();
        require dirname(__DIR__) . '/Views/planning/rules.php';
    }

    public function save(): void
    {
        if (!$this->database->isAvailable()) {
            $this->redirect('/rules');
        }
        if (!CsrfToken::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Your session token has expired. Return to the form and try again.');
        }
        $active = isset($_POST['is_active']);
        $hours = filter_var($_POST['lieu_hours'] ?? null, FILTER_VALIDATE_FLOAT);
        $slotValues = $_POST['evening_slot_ids'] ?? [];
        $dayValues = $_POST['lieu_day_ids'] ?? [];
        if (!is_array($slotValues) || !is_array($dayValues)) {
            Flash::set('error', 'Choose valid evening periods and in-lieu days.');
            $this->redirect('/rules');
        }
        $validSlots = array_map('intval', array_column($this->database->all('SELECT id FROM time_slots'), 'id'));
        $validDays = array_map('intval', array_column($this->database->all('SELECT id FROM week_days WHERE is_teaching_day = 1'), 'id'));
        $slotIds = $this->validatedIds($slotValues, $validSlots);
        $dayIds = $this->validatedIds($dayValues, $validDays);
        if ($slotIds === null || $dayIds === null
            || ($active && (!$slotIds || !$dayIds || $hours === false || $hours <= 0 || $hours > 12))) {
            Flash::set('error', 'To enable this rule, choose at least one evening time, one possible in-lieu day, and a half-day duration from 0.25 to 12 hours.');
            $this->redirect('/rules');
        }
        $configuration = json_encode([
            'evening_slot_ids' => $slotIds,
            'lieu_day_ids' => $dayIds,
            'lieu_minutes' => (int) round((float) ($hours === false ? 0 : $hours) * 60),
            'lieu_must_be_contiguous' => true,
            'lieu_must_be_different_day_from_evening' => true,
        ], JSON_THROW_ON_ERROR);
        try {
            $this->database->execute(
                'INSERT INTO timetable_rules (rule_key, name, configuration, severity, is_active)
                 VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE configuration = VALUES(configuration), severity = VALUES(severity), is_active = VALUES(is_active)',
                ['evening_lieu', 'Evening duty and time in lieu', $configuration, 'hard', $active ? 1 : 0]
            );
            Flash::set('success', $active ? 'Evening and time-in-lieu rule enabled.' : 'Evening and time-in-lieu rule disabled.');
        } catch (Throwable $exception) {
            Flash::set('error', 'The rule could not be saved. Check the database schema and try again.');
        }
        $this->redirect('/rules');
    }

    /** @param array<mixed> $values @param list<int> $validIds @return list<int>|null */
    private function validatedIds(array $values, array $validIds): ?array
    {
        $ids = [];
        foreach ($values as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if (!$id || !in_array($id, $validIds, true)) {
                return null;
            }
            $ids[$id] = $id;
        }
        return array_values($ids);
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}

