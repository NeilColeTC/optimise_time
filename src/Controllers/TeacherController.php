<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfToken;
use App\Core\Database;
use App\Core\Flash;
use Throwable;

final class TeacherController
{
    public function __construct(private Database $database)
    {
    }

    public function index(): void
    {
        $databaseMessage = $this->database->errorMessage();
        $teachers = $this->database->isAvailable() ? $this->database->all(
            'SELECT t.*,
                (SELECT COALESCE(SUM(r.weekly_minutes), 0) FROM teacher_remissions r WHERE r.teacher_id = t.id) AS remission_minutes,
                GREATEST(t.allocated_teaching_minutes - t.reduced_timetable_minutes -
                    (SELECT COALESCE(SUM(r.weekly_minutes), 0) FROM teacher_remissions r WHERE r.teacher_id = t.id), 0) AS expected_teaching_minutes
             FROM teachers t ORDER BY t.last_name, t.first_name'
        ) : [];
        $flash = Flash::consume();
        require dirname(__DIR__) . '/Views/teachers/index.php';
    }

    public function create(): void
    {
        if (!$this->database->isAvailable()) {
            $this->showDatabaseUnavailable();
            return;
        }
        $teacher = $this->emptyTeacher();
        $remissions = [];
        $this->showForm($teacher, $remissions, [], 'Add teacher', '/teachers');
    }

    public function edit(): void
    {
        if (!$this->database->isAvailable()) {
            $this->showDatabaseUnavailable();
            return;
        }
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $teacher = $id ? $this->database->one('SELECT * FROM teachers WHERE id = ?', [$id]) : null;
        if (!$teacher) {
            Flash::set('error', 'The requested teacher could not be found.');
            $this->redirect('/teachers');
        }
        $teacher['allocated_hours'] = number_format(((int) $teacher['allocated_teaching_minutes']) / 60, 2, '.', '');
        $teacher['reduced_hours'] = number_format(((int) $teacher['reduced_timetable_minutes']) / 60, 2, '.', '');
        $teacher['max_in_class_hours'] = number_format(((int) $teacher['max_in_class_minutes']) / 60, 2, '.', '');
        $remissions = $this->database->all(
            'SELECT remission_type_id, weekly_minutes FROM teacher_remissions WHERE teacher_id = ? ORDER BY id',
            [$id]
        );
        foreach ($remissions as &$remission) {
            $remission['hours'] = number_format(((int) $remission['weekly_minutes']) / 60, 2, '.', '');
        }
        unset($remission);
        $this->showForm($teacher, $remissions, [], 'Edit teacher', '/teachers/update?id=' . $id);
    }

    public function store(): void
    {
        if (!$this->database->isAvailable()) {
            $this->redirect('/teachers');
        }
        $this->save(null);
    }

    public function update(): void
    {
        if (!$this->database->isAvailable()) {
            $this->redirect('/teachers');
        }
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id || !$this->database->one('SELECT id FROM teachers WHERE id = ?', [$id])) {
            Flash::set('error', 'The requested teacher could not be found.');
            $this->redirect('/teachers');
        }
        $this->save($id);
    }

    public function delete(): void
    {
        if (!$this->database->isAvailable()) {
            $this->redirect('/teachers');
        }
        $this->requireCsrf();
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            Flash::set('error', 'The teacher could not be identified.');
            $this->redirect('/teachers');
        }
        $this->database->execute('UPDATE teachers SET is_active = 0 WHERE id = ?', [$id]);
        Flash::set('success', 'Teacher deactivated. Their records are retained for timetable history.');
        $this->redirect('/teachers');
    }

    private function save(?int $id): void
    {
        $this->requireCsrf();
        $teacher = [
            'staff_code' => trim((string) ($_POST['staff_code'] ?? '')),
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'allocated_teaching_minutes' => null,
            'reduced_timetable_minutes' => null,
            'max_in_class_minutes' => null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        $errors = [];
        if ($teacher['first_name'] === '') {
            $errors[] = 'Enter the teacher's first name.';
        }
        if ($teacher['last_name'] === '') {
            $errors[] = 'Enter the teacher's last name.';
        }

        foreach ([
            'allocated_hours' => 'allocated_teaching_minutes',
            'reduced_hours' => 'reduced_timetable_minutes',
            'max_in_class_hours' => 'max_in_class_minutes',
        ] as $input => $column) {
            $teacher[$column] = $this->hoursToMinutes($_POST[$input] ?? null, $input, $errors);
        }

        $remissions = $this->readRemissions($errors);
        $totalRemissions = array_sum(array_column($remissions, 'weekly_minutes'));
        if ($teacher['allocated_teaching_minutes'] !== null && $teacher['reduced_timetable_minutes'] !== null
            && $totalRemissions + $teacher['reduced_timetable_minutes'] > $teacher['allocated_teaching_minutes']) {
            $errors[] = 'Remission and reduced-timetable hours cannot exceed the allocated teaching hours.';
        }

        if ($errors !== []) {
            $teacher = array_merge($teacher, [
                'allocated_hours' => $_POST['allocated_hours'] ?? '',
                'reduced_hours' => $_POST['reduced_hours'] ?? '',
                'max_in_class_hours' => $_POST['max_in_class_hours'] ?? '',
            ]);
            $this->showForm($teacher, $this->postedRemissions(), $errors, $id ? 'Edit teacher' : 'Add teacher',
                $id ? '/teachers/update?id=' . $id : '/teachers');
            return;
        }

        $values = [
            $teacher['staff_code'] !== '' ? $teacher['staff_code'] : null,
            $teacher['first_name'],
            $teacher['last_name'],
            $teacher['allocated_teaching_minutes'],
            $teacher['reduced_timetable_minutes'],
            $teacher['max_in_class_minutes'],
            $teacher['is_active'],
        ];

        try {
            $this->database->transaction(function (Database $database) use ($id, $values, $remissions): void {
                if ($id === null) {
                    $database->execute(
                        'INSERT INTO teachers (staff_code, first_name, last_name, allocated_teaching_minutes, reduced_timetable_minutes, max_in_class_minutes, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?)',
                        $values
                    );
                    $teacherId = $database->insertId();
                } else {
                    $database->execute(
                        'UPDATE teachers SET staff_code = ?, first_name = ?, last_name = ?, allocated_teaching_minutes = ?, reduced_timetable_minutes = ?, max_in_class_minutes = ?, is_active = ? WHERE id = ?',
                        [...$values, $id]
                    );
                    $teacherId = $id;
                    $database->execute('DELETE FROM teacher_remissions WHERE teacher_id = ?', [$teacherId]);
                }
                foreach ($remissions as $remission) {
                    $database->execute(
                        'INSERT INTO teacher_remissions (teacher_id, remission_type_id, weekly_minutes) VALUES (?, ?, ?)',
                        [$teacherId, $remission['remission_type_id'], $remission['weekly_minutes']]
                    );
                }
            });
        } catch (Throwable $exception) {
            $errors[] = 'The teacher could not be saved. Check that the staff code is unique and try again.';
            $teacher['allocated_hours'] = $_POST['allocated_hours'] ?? '';
            $teacher['reduced_hours'] = $_POST['reduced_hours'] ?? '';
            $teacher['max_in_class_hours'] = $_POST['max_in_class_hours'] ?? '';
            $this->showForm($teacher, $this->postedRemissions(), $errors, $id ? 'Edit teacher' : 'Add teacher',
                $id ? '/teachers/update?id=' . $id : '/teachers');
            return;
        }

        Flash::set('success', $id ? 'Teacher details updated.' : 'Teacher added.');
        $this->redirect('/teachers');
    }

    /** @param list<string> $errors */
    private function showForm(array $teacher, array $remissions, array $errors, string $title, string $action): void
    {
        $remissionTypes = $this->database->all('SELECT id, name FROM remission_types WHERE is_active = 1 ORDER BY name');
        $csrfToken = CsrfToken::value();
        $flash = Flash::consume();
        require dirname(__DIR__) . '/Views/teachers/form.php';
    }

    /** @param list<string> $errors */
    private function hoursToMinutes(mixed $value, string $field, array &$errors): ?int
    {
        $number = trim((string) $value);
        if ($number === '' || !is_numeric($number) || (float) $number < 0 || (float) $number > 168) {
            $errors[] = 'Enter a number of hours from 0 to 168 for ' . str_replace('_', ' ', $field) . '.';
            return null;
        }
        return (int) round((float) $number * 60);
    }

    /** @param list<string> $errors @return list<array{remission_type_id:int, weekly_minutes:int}> */
    private function readRemissions(array &$errors): array
    {
        $types = $_POST['remission_type_id'] ?? [];
        $hours = $_POST['remission_hours'] ?? [];
        if (!is_array($types) || !is_array($hours)) {
            $errors[] = 'Remission details were not submitted correctly.';
            return [];
        }
        $validTypes = array_column($this->database->all('SELECT id FROM remission_types WHERE is_active = 1'), 'id');
        $rows = [];
        foreach ($types as $index => $typeValue) {
            $hourValue = $hours[$index] ?? '';
            if (trim((string) $typeValue) === '' && trim((string) $hourValue) === '') {
                continue;
            }
            $typeId = filter_var($typeValue, FILTER_VALIDATE_INT);
            if (!$typeId || !in_array((string) $typeId, array_map('strval', $validTypes), true)) {
                $errors[] = 'Choose a valid remission type for each remission entry.';
                continue;
            }
            $minutes = $this->hoursToMinutes($hourValue, 'remission_hours', $errors);
            if ($minutes !== null && $minutes > 0) {
                $rows[] = ['remission_type_id' => $typeId, 'weekly_minutes' => $minutes];
            }
        }
        return $rows;
    }

    private function postedRemissions(): array
    {
        $rows = [];
        $types = $_POST['remission_type_id'] ?? [];
        $hours = $_POST['remission_hours'] ?? [];
        if (is_array($types) && is_array($hours)) {
            foreach ($types as $index => $typeId) {
                $rows[] = ['remission_type_id' => $typeId, 'hours' => $hours[$index] ?? ''];
            }
        }
        return $rows;
    }

    private function emptyTeacher(): array
    {
        return [
            'staff_code' => '', 'first_name' => '', 'last_name' => '', 'allocated_hours' => '',
            'reduced_hours' => '0', 'max_in_class_hours' => '', 'is_active' => 1,
        ];
    }

    private function showDatabaseUnavailable(): void
    {
        $databaseMessage = $this->database->errorMessage();
        require dirname(__DIR__) . '/Views/database-unavailable.php';
    }

    private function requireCsrf(): void
    {
        if (!CsrfToken::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Your session token has expired. Return to the form and try again.');
        }
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}

