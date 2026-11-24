<?php

declare(strict_types=1);
namespace App\Controllers;
use App\Core\CsrfToken; use App\Core\Database; use App\Core\Flash;
final class ProtectedBlocksController
{
 public function __construct(private Database $database){}
 public function index():void { if(!$this->database->isAvailable()){$databaseMessage=$this->database->errorMessage();require dirname(__DIR__).'/Views/database-unavailable.php';return;} $days=$this->database->all('SELECT * FROM week_days ORDER BY sort_order');$slots=$this->database->all('SELECT * FROM time_slots ORDER BY starts_at');$blocks=$this->database->all('SELECT b.*,d.name AS day_name,s.label AS slot_label,s.starts_at,s.ends_at FROM protected_blocks b JOIN week_days d ON d.id=b.week_day_id JOIN time_slots s ON s.id=b.time_slot_id ORDER BY d.sort_order,s.starts_at');$flash=Flash::consume();$csrfToken=CsrfToken::value();require dirname(__DIR__).'/Views/planning/protected-blocks.php'; }
 public function save():void { if(!CsrfToken::verify($_POST['_csrf']??null)){http_response_code(419);exit('Your session token has expired.');} $name=trim((string)($_POST['name']??''));$type=(string)($_POST['block_type']??'custom');$scope=(string)($_POST['course_scope']??'all_courses');$day=filter_var($_POST['week_day_id']??null,FILTER_VALIDATE_INT);$slot=filter_var($_POST['time_slot_id']??null,FILTER_VALIDATE_INT);$valid=$name!==''&&strlen($name)<=120&&in_array($type,['english_maths','meetings_training','custom'],true)&&in_array($scope,['all_courses','vocational_courses'],true)&&$day&&$slot&&$this->database->one('SELECT id FROM week_days WHERE id=?',[$day])&&$this->database->one('SELECT id FROM time_slots WHERE id=?',[$slot]);if(!$valid){Flash::set('error','Complete the protected block details and choose a valid day and period.');$this->go();}try{$this->database->execute('INSERT INTO protected_blocks (name,block_type,course_scope,week_day_id,time_slot_id) VALUES (?,?,?,?,?)',[$name,$type,$scope,$day,$slot]);Flash::set('success','Protected block added.');}catch(\Throwable){Flash::set('error','This protected block already exists or could not be saved.');}$this->go(); }
 public function delete():void { if(!CsrfToken::verify($_POST['_csrf']??null)){http_response_code(419);exit('Your session token has expired.');}$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);if($id){$this->database->execute('DELETE FROM protected_blocks WHERE id=?',[$id]);Flash::set('success','Protected block removed.');}$this->go(); }
 private function go():never{header('Location: /rules',true,303);exit;}
}
