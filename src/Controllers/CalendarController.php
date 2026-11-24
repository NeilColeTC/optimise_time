<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfToken;
use App\Core\Database;
use App\Core\Flash;
use Throwable;

final class CalendarController
{
    public function __construct(private Database $database) {}

    public function index(): void
    {
        if (!$this->database->isAvailable()) { $databaseMessage = $this->database->errorMessage(); require dirname(__DIR__) . '/Views/database-unavailable.php'; return; }
        $years = $this->database->all('SELECT * FROM academic_years ORDER BY starts_on DESC');
        $terms = $this->database->all('SELECT t.*, y.name AS year_name FROM academic_terms t JOIN academic_years y ON y.id=t.academic_year_id ORDER BY t.starts_on');
        $holidays = $this->database->all('SELECT * FROM bank_holidays ORDER BY holiday_date');
        $flash = Flash::consume(); $csrfToken = CsrfToken::value();
        require dirname(__DIR__) . '/Views/planning/calendar.php';
    }

    public function saveYear(): void
    {
        $this->guard(); $name = trim((string)($_POST['name'] ?? '')); $start = (string)($_POST['starts_on'] ?? ''); $end = (string)($_POST['ends_on'] ?? '');
        if ($name === '' || strlen($name)>80 || !$this->validDates($start,$end)) { Flash::set('error','Enter an academic year name and a valid start/end date range.'); $this->go(); }
        try { $this->database->execute('INSERT INTO academic_years (name,starts_on,ends_on,is_active) VALUES (?,?,?,?)',[$name,$start,$end,isset($_POST['is_active'])?1:0]); Flash::set('success','Academic year added.'); }
        catch (Throwable) { Flash::set('error','That academic year could not be saved. Check that its name is unique.'); }
        $this->go();
    }

    public function saveTerm(): void
    {
        $this->guard(); $yearId=filter_var($_POST['academic_year_id']??null,FILTER_VALIDATE_INT); $name=trim((string)($_POST['name']??'')); $start=(string)($_POST['starts_on']??''); $end=(string)($_POST['ends_on']??'');
        $year=$yearId?$this->database->one('SELECT starts_on,ends_on FROM academic_years WHERE id=?',[$yearId]):null;
        if (!$year || $name==='' || strlen($name)>80 || !$this->validDates($start,$end) || $start<$year['starts_on'] || $end>$year['ends_on']) { Flash::set('error','Choose a valid academic year and term dates contained within that year.'); $this->go(); }
        try { $this->database->execute('INSERT INTO academic_terms (academic_year_id,name,starts_on,ends_on,sort_order) VALUES (?,?,?,?,?)',[$yearId,$name,$start,$end,max(1,(int)($_POST['sort_order']??1))]); Flash::set('success','Term added.'); }
        catch (Throwable) { Flash::set('error','That term could not be saved. Check that its name is unique within the academic year.'); }
        $this->go();
    }

    public function saveHoliday(): void
    {
        $this->guard(); $date=(string)($_POST['holiday_date']??''); $name=trim((string)($_POST['name']??''));
        if (!$this->validDates($date,$date) || $name==='' || strlen($name)>120) { Flash::set('error','Enter a bank holiday name and valid date.'); $this->go(); }
        try { $this->database->execute('INSERT INTO bank_holidays (holiday_date,name) VALUES (?,?)',[$date,$name]); Flash::set('success','Bank holiday added.'); }
        catch (Throwable) { Flash::set('error','A bank holiday already exists on that date, or the entry could not be saved.'); }
        $this->go();
    }

    public function delete(): void
    {
        $this->guard(); $kind=(string)($_POST['kind']??''); $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
        $tables=['year'=>'academic_years','term'=>'academic_terms','holiday'=>'bank_holidays'];
        if ($id && isset($tables[$kind])) { try { $this->database->execute('DELETE FROM '.$tables[$kind].' WHERE id=?',[$id]); Flash::set('success','Calendar entry removed.'); } catch (Throwable) { Flash::set('error','This entry is in use and could not be removed.'); } }
        $this->go();
    }

    private function validDates(string $start,string $end): bool { $a=\DateTimeImmutable::createFromFormat('!Y-m-d',$start); $b=\DateTimeImmutable::createFromFormat('!Y-m-d',$end); return $a && $b && $a->format('Y-m-d')===$start && $b->format('Y-m-d')===$end && $end >= $start; }
    private function guard(): void { if (!CsrfToken::verify($_POST['_csrf']??null)) { http_response_code(419); exit('Your session token has expired. Return to the form and try again.'); } }
    private function go(): never { header('Location: /calendar',true,303); exit; }
}
