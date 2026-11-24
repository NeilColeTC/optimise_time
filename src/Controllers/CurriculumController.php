<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfToken;
use App\Core\Database;
use App\Core\Flash;
use Throwable;

final class CurriculumController
{
    public function __construct(private Database $database) {}

    public function index(): void
    {
        if (!$this->database->isAvailable()) { $databaseMessage=$this->database->errorMessage(); require dirname(__DIR__).'/Views/database-unavailable.php'; return; }
        $courses=$this->database->all('SELECT * FROM courses ORDER BY name');
        $units=$this->database->all('SELECT * FROM units ORDER BY name');
        $years=$this->database->all('SELECT * FROM academic_years WHERE is_active=1 ORDER BY starts_on DESC');
        $terms=$this->database->all('SELECT * FROM academic_terms ORDER BY starts_on');
        $offerings=$this->database->all('SELECT o.*,c.name AS course_name,c.course_type,u.name AS unit_name,y.name AS year_name,t.name AS term_name FROM course_unit_offerings o JOIN courses c ON c.id=o.course_id JOIN units u ON u.id=o.unit_id JOIN academic_years y ON y.id=o.academic_year_id LEFT JOIN academic_terms t ON t.id=o.term_id ORDER BY o.starts_on,c.name,u.name');
        $flash=Flash::consume(); $csrfToken=CsrfToken::value();
        require dirname(__DIR__).'/Views/planning/curriculum.php';
    }

    public function save(): void
    {
        if (!CsrfToken::verify($_POST['_csrf']??null)) { http_response_code(419); exit('Your session token has expired. Return to the form and try again.'); }
        $kind=(string)($_POST['kind']??'');
        try {
            if ($kind==='course') {
                $name=trim((string)($_POST['name']??'')); $type=(string)($_POST['course_type']??'vocational');
                $hours=$this->number($_POST['total_hours']??null); $credits=$this->number($_POST['total_credits']??null);
                if ($name==='' || strlen($name)>160 || !in_array($type,['vocational','standalone'],true) || $hours===null || $credits===null) throw new \InvalidArgumentException();
                $this->database->execute('INSERT INTO courses (code,name,course_type,total_hours,total_credits,requires_english_maths) VALUES (?,?,?,?,?,?)',[$this->nullable($_POST['code']??''),$name,$type,$hours,$credits,isset($_POST['requires_english_maths'])?1:0]);
                Flash::set('success','Course added.');
            } elseif ($kind==='unit') {
                $name=trim((string)($_POST['name']??'')); $hours=$this->number($_POST['total_hours']??null); $credits=$this->number($_POST['total_credits']??null); $length=filter_var($_POST['default_session_length_minutes']??null,FILTER_VALIDATE_INT);
                if ($name==='' || strlen($name)>160 || $hours===null || $credits===null || !$length || $length<15 || $length>240 || $length%15!==0) throw new \InvalidArgumentException();
                $this->database->execute('INSERT INTO units (code,name,total_hours,total_credits,default_session_length_minutes) VALUES (?,?,?,?,?)',[$this->nullable($_POST['code']??''),$name,$hours,$credits,$length]);
                Flash::set('success','Unit added.');
            } elseif ($kind==='offering') {
                $course=(int)($_POST['course_id']??0); $unit=(int)($_POST['unit_id']??0); $year=(int)($_POST['academic_year_id']??0); $term=(int)($_POST['term_id']??0); $start=(string)($_POST['starts_on']??''); $end=(string)($_POST['ends_on']??''); $pattern=(string)($_POST['delivery_pattern']??''); $size=filter_var($_POST['cohort_size']??null,FILTER_VALIDATE_INT); $length=filter_var($_POST['session_length_minutes']??null,FILTER_VALIDATE_INT);
                $yearRow=$this->database->one('SELECT starts_on,ends_on FROM academic_years WHERE id=? AND is_active=1',[$year]);
                $termRow=$term?$this->database->one('SELECT academic_year_id,starts_on,ends_on FROM academic_terms WHERE id=?',[$term]):null;
                $valid=$course && $unit && $yearRow && in_array($pattern,['semesterised_fat','weekly_long'],true) && $this->validRange($start,$end) && $start >= $yearRow['starts_on'] && $end <= $yearRow['ends_on'] && $size!==false && $size>=0 && $size<=5000 && $length!==false && $length>=15 && $length<=240;
                if ($term && (!$termRow || (int)$termRow['academic_year_id']!==$year || $start<$termRow['starts_on'] || $end>$termRow['ends_on'])) $valid=false;
                if (!$valid) throw new \InvalidArgumentException();
                $this->database->execute('INSERT INTO course_unit_offerings (course_id,unit_id,academic_year_id,term_id,delivery_pattern,starts_on,ends_on,cohort_size,session_length_minutes,required_room_type) VALUES (?,?,?,?,?,?,?,?,?,?)',[$course,$unit,$year,$term?:null,$pattern,$start,$end,$size,$length,$this->nullable($_POST['required_room_type']??'')]);
                Flash::set('success','Course unit offering added.');
            } else { throw new \InvalidArgumentException(); }
        } catch (\InvalidArgumentException) { Flash::set('error','Check the required values, date boundaries, and delivery pattern. Offering dates must fit inside the academic year and selected term.'); }
        catch (Throwable) { Flash::set('error','The curriculum item could not be saved. Check for duplicate codes or names and confirm the schema is current.'); }
        header('Location: /demand',true,303); exit;
    }

    private function number(mixed $v): ?float { if (!is_numeric($v) || (float)$v<0 || (float)$v>100000) return null; return (float)$v; }
    private function nullable(mixed $v): ?string { $s=trim((string)$v); return $s===''?null:$s; }
    private function validRange(string $a,string $b): bool { $x=\DateTimeImmutable::createFromFormat('!Y-m-d',$a); $y=\DateTimeImmutable::createFromFormat('!Y-m-d',$b); return $x && $y && $x->format('Y-m-d')===$a && $y->format('Y-m-d')===$b && $a<=$b; }
}
