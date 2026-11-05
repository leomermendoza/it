-- TrackED FINAL FRESH DATABASE
-- Generated from the user's current MariaDB 10.4 schema.
-- Purpose: clean installation with zero student and historical operational records.
-- WARNING: this script DROPS and recreates tracked_db.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS `tracked_db`;
CREATE DATABASE `tracked_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `tracked_db`;

CREATE TABLE `admin_audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category` varchar(50) NOT NULL,
  `action_type` varchar(80) NOT NULL,
  `entity_type` varchar(60) NOT NULL DEFAULT '',
  `entity_id` varchar(80) NOT NULL DEFAULT '',
  `description` varchar(255) NOT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `performed_by` varchar(120) NOT NULL DEFAULT 'SPMO Admin',
  `ip_address` varchar(64) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `attendance_source` varchar(20) NOT NULL DEFAULT 'LEGACY',
  `scan_time` datetime NOT NULL DEFAULT current_timestamp(),
  `lunch_out` datetime DEFAULT NULL,
  `lunch_in` datetime DEFAULT NULL,
  `status` enum('On Time','Late','Absent') NOT NULL DEFAULT 'On Time',
  `scan_type` enum('IN','OUT') NOT NULL DEFAULT 'IN',
  `time_out` datetime DEFAULT NULL,
  `sms_sent_in` tinyint(1) DEFAULT 0,
  `sms_sent_out` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `daily_monitoring_exceptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` int(11) NOT NULL,
  `exception_date` date NOT NULL,
  `exception_type` varchar(40) NOT NULL DEFAULT 'NO_OUT',
  `last_movement_id` bigint(20) UNSIGNED DEFAULT NULL,
  `last_movement_at` datetime DEFAULT NULL,
  `detected_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_resolved` tinyint(1) NOT NULL DEFAULT 0,
  `resolved_at` datetime DEFAULT NULL,
  `resolution_note` varchar(255) NOT NULL DEFAULT '',
  `source` varchar(30) NOT NULL DEFAULT 'AUTO_EOD'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `emergency_muster_members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `session_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` int(11) NOT NULL,
  `student_id_no_snapshot` varchar(30) NOT NULL DEFAULT '',
  `full_name_snapshot` varchar(150) NOT NULL,
  `education_level_snapshot` varchar(40) NOT NULL DEFAULT 'College',
  `academic_group_snapshot` varchar(100) NOT NULL DEFAULT '',
  `year_level_snapshot` varchar(30) NOT NULL DEFAULT '',
  `section_snapshot` varchar(50) NOT NULL DEFAULT '',
  `accounted_status` enum('UNCONFIRMED','SAFE','NOT_FOUND','EXCUSED') NOT NULL DEFAULT 'UNCONFIRMED',
  `accounted_at` datetime DEFAULT NULL,
  `accounted_by` varchar(120) DEFAULT NULL,
  `note` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `emergency_muster_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(160) NOT NULL,
  `status` enum('ACTIVE','CLOSED') NOT NULL DEFAULT 'ACTIVE',
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `started_by` varchar(120) NOT NULL DEFAULT 'SPMO Admin',
  `closed_at` datetime DEFAULT NULL,
  `closed_by` varchar(120) DEFAULT NULL,
  `notes` varchar(255) NOT NULL DEFAULT '',
  `total_snapshot` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `id_templates` (
  `id` int(11) NOT NULL,
  `template_name` varchar(120) NOT NULL,
  `front_image` varchar(255) NOT NULL,
  `back_image` varchar(255) DEFAULT NULL,
  `config_json` longtext DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `intramural_clusters` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `division` enum('College','High School') NOT NULL,
  `name` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `intramural_cluster_members` (
  `id` int(11) NOT NULL,
  `cluster_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `assigned_method` enum('random','manual') NOT NULL DEFAULT 'random',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `intramural_events` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `school_year` varchar(30) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `override_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `college_time_in_start` time NOT NULL DEFAULT '06:00:00',
  `college_ontime_cutoff` time NOT NULL DEFAULT '07:30:00',
  `college_late_cutoff` time NOT NULL DEFAULT '08:30:00',
  `college_lunch_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `college_lunch_out_start` time NOT NULL DEFAULT '11:30:00',
  `college_lunch_out_end` time NOT NULL DEFAULT '12:15:00',
  `college_lunch_in_start` time NOT NULL DEFAULT '12:15:00',
  `college_lunch_in_end` time NOT NULL DEFAULT '13:15:00',
  `college_time_out_start` time NOT NULL DEFAULT '16:00:00',
  `college_time_out_end` time NOT NULL DEFAULT '18:00:00',
  `hs_time_in_start` time NOT NULL DEFAULT '06:30:00',
  `hs_ontime_cutoff` time NOT NULL DEFAULT '07:30:00',
  `hs_late_cutoff` time NOT NULL DEFAULT '08:30:00',
  `hs_lunch_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `hs_lunch_out_start` time NOT NULL DEFAULT '11:30:00',
  `hs_lunch_out_end` time NOT NULL DEFAULT '12:15:00',
  `hs_lunch_in_start` time NOT NULL DEFAULT '12:15:00',
  `hs_lunch_in_end` time NOT NULL DEFAULT '13:15:00',
  `hs_time_out_start` time NOT NULL DEFAULT '15:30:00',
  `hs_time_out_end` time NOT NULL DEFAULT '17:30:00',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `intramural_generations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `division` enum('College','High School') NOT NULL,
  `student_count` int(11) NOT NULL DEFAULT 0,
  `cluster_count` int(11) NOT NULL DEFAULT 0,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `log_backup_runs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `run_type` enum('SCHEDULED','MANUAL') NOT NULL DEFAULT 'SCHEDULED',
  `status` enum('RUNNING','SUCCESS','FAILED') NOT NULL DEFAULT 'RUNNING',
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `backup_path` varchar(255) NOT NULL DEFAULT '',
  `message` text DEFAULT NULL,
  `archived_records` int(11) NOT NULL DEFAULT 0,
  `purged_records` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `log_retention_holds` (
  `id` int(11) NOT NULL,
  `case_name` varchar(140) NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `note` varchar(255) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `movement_policy` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `hs_controlled_exit_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `hs_dismissal_start` time NOT NULL DEFAULT '15:30:00',
  `hs_early_exit_requires_reason` tinyint(1) NOT NULL DEFAULT 1,
  `hs_allow_reentry` tinyint(1) NOT NULL DEFAULT 1,
  `sms_every_movement` tinyint(1) NOT NULL DEFAULT 1,
  `sms_early_exit` tinyint(1) NOT NULL DEFAULT 1,
  `same_card_cooldown_seconds` tinyint(3) UNSIGNED NOT NULL DEFAULT 4,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rejected_scans` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `rfid_uid` varchar(100) NOT NULL,
  `scan_type` enum('IN','LUNCH_OUT','LUNCH_IN','OUT') NOT NULL,
  `movement_context` varchar(40) NOT NULL DEFAULT 'NORMAL',
  `rejection_reason` varchar(255) NOT NULL,
  `rejected_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `education_level` varchar(40) NOT NULL DEFAULT 'College',
  `course` varchar(30) NOT NULL,
  `track` varchar(80) NOT NULL DEFAULT '',
  `strand` varchar(80) NOT NULL DEFAULT '',
  `year_level` varchar(20) NOT NULL,
  `section_scope` varchar(80) NOT NULL DEFAULT '',
  `days_json` text NOT NULL DEFAULT '[]',
  `time_in_start` time NOT NULL DEFAULT '06:00:00',
  `ontime_cutoff` time NOT NULL DEFAULT '07:30:00',
  `late_cutoff` time NOT NULL DEFAULT '08:30:00',
  `lunch_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `lunch_out_start` time NOT NULL DEFAULT '11:30:00',
  `lunch_out_end` time NOT NULL DEFAULT '12:15:00',
  `lunch_in_start` time NOT NULL DEFAULT '12:15:00',
  `lunch_in_end` time NOT NULL DEFAULT '13:15:00',
  `time_out_start` time NOT NULL DEFAULT '15:00:00',
  `time_out_end` time NOT NULL DEFAULT '18:00:00',
  `timeout_cutoff` time DEFAULT '15:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `sms_queue` (
  `id` int(11) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `attempts` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `rfid_uid` varchar(50) NOT NULL,
  `student_id_no` varchar(30) NOT NULL DEFAULT '',
  `course` varchar(30) NOT NULL DEFAULT 'BSIT',
  `track` varchar(80) NOT NULL DEFAULT '',
  `strand` varchar(80) NOT NULL DEFAULT '',
  `full_name` varchar(150) NOT NULL,
  `education_level` varchar(40) NOT NULL DEFAULT 'College',
  `year_level` varchar(30) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `parent_mobile` varchar(20) DEFAULT NULL,
  `guardian_name` varchar(150) NOT NULL DEFAULT '',
  `guardian_address` varchar(255) NOT NULL DEFAULT '',
  `photo_path` varchar(255) DEFAULT '',
  `signature_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_irregular` tinyint(1) DEFAULT 0,
  `irregular_days_json` text DEFAULT '[]',
  `is_active` tinyint(1) DEFAULT 1,
  `scan_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `denied_attempt_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `scan_locked_at` datetime DEFAULT NULL,
  `scan_lock_reason` varchar(255) DEFAULT NULL,
  `scan_unlocked_at` datetime DEFAULT NULL,
  `scan_access_updated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `student_movement_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` int(11) NOT NULL,
  `rfid_uid` varchar(50) NOT NULL,
  `movement_type` enum('IN','OUT') NOT NULL,
  `movement_context` varchar(40) NOT NULL DEFAULT 'NORMAL',
  `authorization_reason` varchar(255) DEFAULT NULL,
  `verified_at` datetime NOT NULL,
  `verified_by` varchar(80) NOT NULL DEFAULT 'Guard Kiosk',
  `source` varchar(30) NOT NULL DEFAULT 'KIOSK',
  `event_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `student_scan_access_audit` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` int(11) NOT NULL,
  `action` enum('LOCKED','UNLOCKED') NOT NULL,
  `attempt_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `reason` varchar(255) DEFAULT NULL,
  `performed_by` varchar(80) NOT NULL DEFAULT 'System',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `subject_code` varchar(20) DEFAULT '',
  `instructor` varchar(100) DEFAULT '',
  `room` varchar(50) DEFAULT '',
  `day` varchar(20) DEFAULT '',
  `time_start` time DEFAULT NULL,
  `time_end` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `system_config` (
  `config_key` varchar(50) NOT NULL,
  `config_value` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `system_heartbeats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source_key` varchar(80) NOT NULL,
  `display_name` varchar(120) NOT NULL DEFAULT '',
  `source_type` varchar(40) NOT NULL DEFAULT 'SERVICE',
  `host` varchar(120) NOT NULL DEFAULT '',
  `last_seen` datetime DEFAULT NULL,
  `last_sync_success` datetime DEFAULT NULL,
  `last_sync_error` text DEFAULT NULL,
  `local_db_ok` tinyint(1) NOT NULL DEFAULT 1,
  `server_latency_ms` decimal(10,2) DEFAULT NULL,
  `unsynced_movements` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `unsynced_rejections` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pending_event_queue` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `version` varchar(40) NOT NULL DEFAULT '',
  `meta_json` longtext DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `admin_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_audit_time` (`created_at`),
  ADD KEY `idx_admin_audit_category` (`category`,`created_at`),
  ADD KEY `idx_admin_audit_entity` (`entity_type`,`entity_id`);

ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scan_date` (`scan_time`),
  ADD KEY `idx_student` (`student_id`);

ALTER TABLE `daily_monitoring_exceptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_daily_exception` (`student_id`,`exception_date`,`exception_type`),
  ADD KEY `idx_daily_exception_date` (`exception_date`,`exception_type`,`is_resolved`),
  ADD KEY `idx_daily_exception_student` (`student_id`,`exception_date`);

ALTER TABLE `emergency_muster_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_muster_student` (`session_id`,`student_id`),
  ADD KEY `idx_muster_member_status` (`session_id`,`accounted_status`),
  ADD KEY `idx_muster_member_student` (`student_id`);

ALTER TABLE `emergency_muster_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_muster_session_status` (`status`,`started_at`);

ALTER TABLE `id_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_id_templates_active` (`is_active`);

ALTER TABLE `intramural_clusters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_event_division_name` (`event_id`,`division`,`name`),
  ADD KEY `idx_cluster_event_division` (`event_id`,`division`);

ALTER TABLE `intramural_cluster_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cluster_student` (`cluster_id`,`student_id`),
  ADD KEY `idx_member_student` (`student_id`);

ALTER TABLE `intramural_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_intramural_event_active` (`is_active`,`school_year`);

ALTER TABLE `intramural_generations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_generation_event_division` (`event_id`,`division`,`generated_at`);

ALTER TABLE `log_backup_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_backup_run_time` (`started_at`,`status`);

ALTER TABLE `log_retention_holds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hold_active_dates` (`is_active`,`date_from`,`date_to`);

ALTER TABLE `movement_policy`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `rejected_scans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `rfid_uid` (`rfid_uid`),
  ADD KEY `rejected_at` (`rejected_at`);

ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_schedule_scope` (`education_level`,`course`,`track`,`strand`,`year_level`,`section_scope`),
  ADD KEY `idx_schedules_academic` (`education_level`,`course`,`track`,`strand`,`year_level`,`section_scope`);

ALTER TABLE `sms_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`);

ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rfid_uid` (`rfid_uid`),
  ADD KEY `idx_students_academic` (`education_level`,`course`,`track`,`strand`,`year_level`,`section`);

ALTER TABLE `student_movement_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_movement_student_time` (`student_id`,`verified_at`),
  ADD KEY `idx_movement_date_type` (`verified_at`,`movement_type`),
  ADD KEY `idx_movement_level_event` (`event_id`,`verified_at`),
  ADD KEY `idx_movement_rfid_time` (`rfid_uid`,`verified_at`);

ALTER TABLE `student_scan_access_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scan_access_student_time` (`student_id`,`created_at`),
  ADD KEY `idx_scan_access_action_time` (`action`,`created_at`);

ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_schedule` (`schedule_id`);

ALTER TABLE `system_config`
  ADD PRIMARY KEY (`config_key`);

ALTER TABLE `system_heartbeats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_system_heartbeat_source` (`source_key`),
  ADD KEY `idx_system_heartbeat_seen` (`last_seen`);

ALTER TABLE `admin_audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `daily_monitoring_exceptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `emergency_muster_members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `emergency_muster_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `id_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `intramural_clusters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `intramural_cluster_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `intramural_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `intramural_generations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `log_backup_runs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `log_retention_holds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `rejected_scans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `sms_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `student_movement_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `student_scan_access_audit`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `system_heartbeats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

ALTER TABLE `emergency_muster_members`
  ADD CONSTRAINT `fk_muster_member_session` FOREIGN KEY (`session_id`) REFERENCES `emergency_muster_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_muster_member_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

ALTER TABLE `intramural_clusters`
  ADD CONSTRAINT `fk_cluster_event` FOREIGN KEY (`event_id`) REFERENCES `intramural_events` (`id`) ON DELETE CASCADE;

ALTER TABLE `intramural_cluster_members`
  ADD CONSTRAINT `fk_member_cluster` FOREIGN KEY (`cluster_id`) REFERENCES `intramural_clusters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_member_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

ALTER TABLE `intramural_generations`
  ADD CONSTRAINT `fk_generation_event` FOREIGN KEY (`event_id`) REFERENCES `intramural_events` (`id`) ON DELETE CASCADE;

ALTER TABLE `student_scan_access_audit`
  ADD CONSTRAINT `fk_scan_access_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE;

-- Essential baseline configuration only.
-- These are system defaults, not historical/student records.
INSERT INTO `movement_policy`
(`id`,`hs_controlled_exit_enabled`,`hs_dismissal_start`,`hs_early_exit_requires_reason`,`hs_allow_reentry`,`sms_every_movement`,`sms_early_exit`,`same_card_cooldown_seconds`) VALUES
(1,1,'15:30:00',1,1,1,1,4);

INSERT INTO `system_config` (`config_key`,`config_value`) VALUES
('archive_retention_months','12'),
('auto_purge_enabled','1'),
('daily_backup_enabled','1'),
('log_retention_days','30'),
('monthly_archive_enabled','1'),
('school_name','Southwestern Institute of Business and Technology'),
('sms_enabled','1');

INSERT INTO `id_templates`
(`id`,`template_name`,`front_image`,`back_image`,`config_json`,`is_active`) VALUES
(1,'Official Student ID','__builtin_portrait__','', '{"version":2,"card":"CR80-portrait","photo":"1x1-inch","layout":{"photo":{"x":26.5,"y":21.5,"w":47.05,"h":29.67},"full_name":{"x":16.256410256410255,"y":53.39792824658919,"w":68,"h":7.5},"student_id_no":{"x":21.23076923076923,"y":62.05962607377463,"w":56,"h":5.5},"course_year":{"x":15,"y":80.51035876705407,"w":70,"h":5},"section":{"x":26.48717948717949,"y":84.70414350682162,"w":46,"h":4.8},"signature":{"x":24.743589743589745,"y":69.59585649317837,"w":50,"h":10}}}',1);

SET FOREIGN_KEY_CHECKS = 1;

-- Fresh-state expectations:
-- students / movement logs / rejected scans / attendance / SMS / audit / muster / intramurals / backups / heartbeats = EMPTY
-- only movement_policy, system_config, and the built-in ID template contain baseline configuration rows.
