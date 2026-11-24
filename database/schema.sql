CREATE DATABASE IF NOT EXISTS academic_timetable
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE academic_timetable;

CREATE TABLE academic_weeks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE week_days (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(20) NOT NULL UNIQUE,
  sort_order TINYINT UNSIGNED NOT NULL UNIQUE,
  is_teaching_day TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE time_slots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(80) NOT NULL,
  starts_at TIME NOT NULL,
  ends_at TIME NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL,
  is_teaching_slot TINYINT(1) NOT NULL DEFAULT 1,
  CHECK (ends_at > starts_at),
  UNIQUE KEY uq_time_slots_order (sort_order)
);

CREATE TABLE teachers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_code VARCHAR(40) NULL UNIQUE,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  allocated_teaching_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  reduced_timetable_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_in_class_minutes SMALLINT UNSIGNED NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE remission_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

INSERT IGNORE INTO remission_types (name, description) VALUES
  ('Course leadership', 'Leadership or coordination responsibility'),
  ('Programme coordination', 'Programme or curriculum coordination'),
  ('Quality assurance', 'Quality assurance and improvement work'),
  ('Tutorial and learner support', 'Tutorial, pastoral, or learner support'),
  ('Other', 'Other agreed remission responsibility');

CREATE TABLE teacher_remissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id BIGINT UNSIGNED NOT NULL,
  remission_type_id BIGINT UNSIGNED NOT NULL,
  weekly_minutes SMALLINT UNSIGNED NOT NULL,
  notes VARCHAR(255) NULL,
  CONSTRAINT fk_teacher_remission_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  CONSTRAINT fk_teacher_remission_type FOREIGN KEY (remission_type_id) REFERENCES remission_types(id)
);

CREATE TABLE teacher_availability (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id BIGINT UNSIGNED NOT NULL,
  week_day_id TINYINT UNSIGNED NOT NULL,
  time_slot_id BIGINT UNSIGNED NOT NULL,
  availability ENUM('available', 'unavailable', 'preferred') NOT NULL DEFAULT 'available',
  reason VARCHAR(255) NULL,
  UNIQUE KEY uq_teacher_availability (teacher_id, week_day_id, time_slot_id),
  CONSTRAINT fk_teacher_availability_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  CONSTRAINT fk_teacher_availability_day FOREIGN KEY (week_day_id) REFERENCES week_days(id),
  CONSTRAINT fk_teacher_availability_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots(id)
);

CREATE TABLE subjects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL UNIQUE,
  code VARCHAR(40) NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE teaching_groups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  cohort_size SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE teacher_subjects (
  teacher_id BIGINT UNSIGNED NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (teacher_id, subject_id),
  CONSTRAINT fk_teacher_subject_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  CONSTRAINT fk_teacher_subject_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

CREATE TABLE group_subject_requirements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teaching_group_id BIGINT UNSIGNED NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  weekly_minutes SMALLINT UNSIGNED NOT NULL,
  required_room_type VARCHAR(100) NULL,
  UNIQUE KEY uq_group_subject (teaching_group_id, subject_id),
  CONSTRAINT fk_group_requirement_group FOREIGN KEY (teaching_group_id) REFERENCES teaching_groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_group_requirement_subject FOREIGN KEY (subject_id) REFERENCES subjects(id)
);

CREATE TABLE rooms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  capacity SMALLINT UNSIGNED NOT NULL,
  room_type VARCHAR(100) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE timetable_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rule_key VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  configuration JSON NOT NULL,
  severity ENUM('hard', 'soft') NOT NULL DEFAULT 'hard',
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE scheduled_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_week_id BIGINT UNSIGNED NOT NULL,
  teaching_group_id BIGINT UNSIGNED NULL,
  subject_id BIGINT UNSIGNED NULL,
  teacher_id BIGINT UNSIGNED NOT NULL,
  room_id BIGINT UNSIGNED NULL,
  week_day_id TINYINT UNSIGNED NOT NULL,
  time_slot_id BIGINT UNSIGNED NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL,
  session_kind ENUM('teaching', 'evening_duty', 'lieu_time') NOT NULL DEFAULT 'teaching',
  source ENUM('manual', 'optimiser') NOT NULL DEFAULT 'manual',
  status ENUM('draft', 'valid', 'conflict') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_session_week FOREIGN KEY (academic_week_id) REFERENCES academic_weeks(id) ON DELETE CASCADE,
  CONSTRAINT fk_session_group FOREIGN KEY (teaching_group_id) REFERENCES teaching_groups(id),
  CONSTRAINT fk_session_subject FOREIGN KEY (subject_id) REFERENCES subjects(id),
  CONSTRAINT fk_session_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id),
  CONSTRAINT fk_session_room FOREIGN KEY (room_id) REFERENCES rooms(id),
  CONSTRAINT fk_session_day FOREIGN KEY (week_day_id) REFERENCES week_days(id),
  CONSTRAINT fk_session_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots(id),
  CONSTRAINT ck_teaching_session_resources CHECK (
    (session_kind = 'teaching' AND teaching_group_id IS NOT NULL AND subject_id IS NOT NULL AND room_id IS NOT NULL)
    OR (session_kind <> 'teaching' AND teaching_group_id IS NULL AND subject_id IS NULL AND room_id IS NULL)
  ),
  UNIQUE KEY uq_session_teacher_time (academic_week_id, teacher_id, week_day_id, time_slot_id),
  UNIQUE KEY uq_session_room_time (academic_week_id, room_id, week_day_id, time_slot_id),
  UNIQUE KEY uq_session_group_time (academic_week_id, teaching_group_id, week_day_id, time_slot_id)
);

