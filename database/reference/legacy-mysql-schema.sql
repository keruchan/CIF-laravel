/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `access_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `access_attempts` (
  `bucket_key` char(64) NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `window_started` datetime NOT NULL,
  PRIMARY KEY (`bucket_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(254) NOT NULL,
  `name` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `applicant_id_counters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `applicant_id_counters` (
  `prefix` varchar(6) NOT NULL,
  `last_number` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_activities` (
  `candidate_id` bigint(20) unsigned NOT NULL,
  `record_number` tinyint(3) unsigned NOT NULL,
  `organization` text DEFAULT NULL,
  `position_held` text DEFAULT NULL,
  `inclusive_years` text DEFAULT NULL,
  PRIMARY KEY (`candidate_id`,`record_number`),
  CONSTRAINT `candidate_activities_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_admin_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_admin_revisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `candidate_id` bigint(20) unsigned NOT NULL,
  `admin_id` bigint(20) unsigned NOT NULL,
  `form_data` longtext NOT NULL,
  `hr_data` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_candidate_revision` (`candidate_id`,`id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `candidate_admin_revisions_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `candidate_admin_revisions_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_assessment_requirement_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_assessment_requirement_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `candidate_id` bigint(20) unsigned NOT NULL,
  `department_required` tinyint(1) NOT NULL,
  `ldt_required` tinyint(1) NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `revision` int(10) unsigned NOT NULL,
  `admin_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assessment_requirement_revision` (`candidate_id`,`revision`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `candidate_assessment_requirement_history_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `candidate_assessment_requirement_history_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_assessment_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_assessment_requirements` (
  `candidate_id` bigint(20) unsigned NOT NULL,
  `department_required` tinyint(1) NOT NULL DEFAULT 1,
  `ldt_required` tinyint(1) NOT NULL DEFAULT 1,
  `reason` varchar(1000) NOT NULL,
  `revision` int(10) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`candidate_id`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `candidate_assessment_requirements_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `candidate_assessment_requirements_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_awards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_awards` (
  `candidate_id` bigint(20) unsigned NOT NULL,
  `record_number` tinyint(3) unsigned NOT NULL,
  `award` text DEFAULT NULL,
  `year_given` smallint(5) unsigned DEFAULT NULL,
  `given_by` text DEFAULT NULL,
  PRIMARY KEY (`candidate_id`,`record_number`),
  CONSTRAINT `candidate_awards_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_connections` (
  `candidate_id` bigint(20) unsigned NOT NULL,
  `record_number` tinyint(3) unsigned NOT NULL,
  `name` text DEFAULT NULL,
  `relationship` text DEFAULT NULL,
  PRIMARY KEY (`candidate_id`,`record_number`),
  CONSTRAINT `candidate_connections_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_deployment_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_deployment_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `candidate_id` bigint(20) unsigned NOT NULL,
  `request_id` bigint(20) unsigned NOT NULL,
  `date_hired` date DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `position_title` varchar(150) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `revision` int(10) unsigned NOT NULL,
  `admin_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_deployment_revision` (`candidate_id`,`revision`),
  KEY `request_id` (`request_id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `candidate_deployment_history_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `candidate_deployment_history_ibfk_2` FOREIGN KEY (`request_id`) REFERENCES `manpower_requests` (`id`),
  CONSTRAINT `candidate_deployment_history_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_deployments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_deployments` (
  `candidate_id` bigint(20) unsigned NOT NULL,
  `request_id` bigint(20) unsigned NOT NULL,
  `date_hired` date DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `position_title` varchar(150) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `revision` int(10) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`candidate_id`),
  UNIQUE KEY `uq_deployment_employee_id` (`employee_id`),
  KEY `request_id` (`request_id`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `candidate_deployments_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `candidate_deployments_ibfk_2` FOREIGN KEY (`request_id`) REFERENCES `manpower_requests` (`id`),
  CONSTRAINT `candidate_deployments_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_details` (
  `candidate_id` bigint(20) unsigned NOT NULL,
  `expected_salary` decimal(12,2) DEFAULT NULL,
  `applied_via` text DEFAULT NULL,
  `fn` varchar(100) DEFAULT NULL,
  `mn` varchar(100) DEFAULT NULL,
  `ln` varchar(100) DEFAULT NULL,
  `sx` varchar(30) DEFAULT NULL,
  `nickname` varchar(100) DEFAULT NULL,
  `place_of_birth` text DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `height_cm` decimal(6,2) DEFAULT NULL,
  `weight_kg` decimal(6,2) DEFAULT NULL,
  `religion` varchar(150) DEFAULT NULL,
  `cs` text DEFAULT NULL,
  `sex` text DEFAULT NULL,
  `nat` varchar(100) DEFAULT NULL,
  `mob` varchar(50) DEFAULT NULL,
  `adr` text DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `city` text DEFAULT NULL,
  `prov` text DEFAULT NULL,
  `zip` varchar(20) DEFAULT NULL,
  `ecn` varchar(150) DEFAULT NULL,
  `emergency_address` text DEFAULT NULL,
  `ecp` varchar(50) DEFAULT NULL,
  `ecr` varchar(150) DEFAULT NULL,
  `edu` text DEFAULT NULL,
  `sch` text DEFAULT NULL,
  `crs` text DEFAULT NULL,
  `education_years` text DEFAULT NULL,
  `yr` smallint(5) unsigned DEFAULT NULL,
  `distinctions` text DEFAULT NULL,
  `licensure_exam` text DEFAULT NULL,
  `licensure_month` char(7) DEFAULT NULL,
  `board_rating` decimal(6,2) DEFAULT NULL,
  `emp` text DEFAULT NULL,
  `certification_name` text DEFAULT NULL,
  `background_check_name` text DEFAULT NULL,
  `certification_accepted` tinyint(1) NOT NULL,
  `background_consent_accepted` tinyint(1) NOT NULL,
  `accepted_at` datetime NOT NULL,
  `form_version` varchar(50) NOT NULL,
  `desired_position` varchar(150) DEFAULT NULL,
  `contact_email` text DEFAULT NULL,
  `facebook_account` text DEFAULT NULL,
  `sss_number` varchar(50) DEFAULT NULL,
  `philhealth_number` varchar(50) DEFAULT NULL,
  `pagibig_number` varchar(50) DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL,
  `blood_type` text DEFAULT NULL,
  `id_photo` mediumtext DEFAULT NULL,
  PRIMARY KEY (`candidate_id`),
  CONSTRAINT `candidate_details_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_employment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_employment` (
  `candidate_id` bigint(20) unsigned NOT NULL,
  `record_number` tinyint(3) unsigned NOT NULL,
  `company_name` text DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `position_title` text DEFAULT NULL,
  `employment_period` text DEFAULT NULL,
  `previous_salary` decimal(12,2) DEFAULT NULL,
  `reason_for_separation` text DEFAULT NULL,
  `immediate_superior` text DEFAULT NULL,
  `superior_contact` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`candidate_id`,`record_number`),
  CONSTRAINT `candidate_employment_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_hr_activity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_hr_activity` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `candidate_id` bigint(20) unsigned NOT NULL,
  `stage` varchar(30) NOT NULL,
  `action` varchar(12) NOT NULL,
  `admin_id` bigint(20) unsigned NOT NULL,
  `decision` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `hr_activity_candidate` (`candidate_id`,`stage`,`id`),
  KEY `hr_activity_staff` (`admin_id`,`created_at`),
  CONSTRAINT `candidate_hr_activity_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `candidate_hr_activity_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_hr_phases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_hr_phases` (
  `candidate_id` bigint(20) unsigned NOT NULL,
  `stage` varchar(30) NOT NULL,
  `started_by` bigint(20) unsigned NOT NULL,
  `started_at` datetime NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `updated_at` datetime NOT NULL,
  `completed_by` bigint(20) unsigned DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `decision` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`candidate_id`,`stage`),
  KEY `hr_phase_started` (`started_by`,`completed_at`),
  KEY `hr_phase_finished` (`completed_by`,`completed_at`),
  KEY `hr_phase_updated` (`updated_at`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `candidate_hr_phases_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `candidate_hr_phases_ibfk_2` FOREIGN KEY (`started_by`) REFERENCES `admins` (`id`),
  CONSTRAINT `candidate_hr_phases_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `admins` (`id`),
  CONSTRAINT `candidate_hr_phases_ibfk_4` FOREIGN KEY (`completed_by`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_interview_stage_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_interview_stage_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `candidate_id` bigint(20) unsigned NOT NULL,
  `stage` varchar(40) NOT NULL,
  `decision` varchar(30) NOT NULL,
  `remarks` text NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `revision` int(10) unsigned NOT NULL,
  `admin_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_candidate_stage_revision` (`candidate_id`,`stage`,`revision`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `candidate_interview_stage_history_ibfk_1` FOREIGN KEY (`candidate_id`, `stage`) REFERENCES `candidate_interview_stages` (`candidate_id`, `stage`) ON DELETE CASCADE,
  CONSTRAINT `candidate_interview_stage_history_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_interview_stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_interview_stages` (
  `candidate_id` bigint(20) unsigned NOT NULL,
  `stage` varchar(40) NOT NULL,
  `decision` varchar(30) NOT NULL DEFAULT 'Pending Decision',
  `remarks` text NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `revision` int(10) unsigned NOT NULL DEFAULT 1,
  `updated_by` bigint(20) unsigned NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`candidate_id`,`stage`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `candidate_interview_stages_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `candidate_interview_stages_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_mrf_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_mrf_assignments` (
  `revision_id` bigint(20) unsigned NOT NULL,
  `candidate_id` bigint(20) unsigned NOT NULL,
  `request_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`revision_id`),
  KEY `idx_candidate_mrf` (`candidate_id`,`revision_id`),
  KEY `idx_mrf_candidate` (`request_id`,`candidate_id`),
  CONSTRAINT `fk_candidate_mrf_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_candidate_mrf_request` FOREIGN KEY (`request_id`) REFERENCES `manpower_requests` (`id`),
  CONSTRAINT `fk_candidate_mrf_revision` FOREIGN KEY (`revision_id`) REFERENCES `candidate_admin_revisions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_public_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_public_state` (
  `candidate_id` bigint(20) unsigned NOT NULL,
  `state_json` longtext NOT NULL,
  `revision` bigint(20) unsigned NOT NULL DEFAULT 0,
  `published_by` bigint(20) unsigned DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  PRIMARY KEY (`candidate_id`),
  KEY `published_by` (`published_by`),
  CONSTRAINT `candidate_public_state_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `candidate_public_state_ibfk_2` FOREIGN KEY (`published_by`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_public_updates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_public_updates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `candidate_id` bigint(20) unsigned NOT NULL,
  `stage` varchar(40) NOT NULL,
  `public_revision` bigint(20) unsigned NOT NULL,
  `progress_json` longtext NOT NULL,
  `email_message_id` bigint(20) unsigned NOT NULL,
  `request_key` char(64) NOT NULL,
  `request_hash` char(64) NOT NULL,
  `published_by` bigint(20) unsigned NOT NULL,
  `published_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_message_id` (`email_message_id`),
  UNIQUE KEY `request_key` (`request_key`),
  KEY `candidate_id` (`candidate_id`),
  KEY `published_by` (`published_by`),
  CONSTRAINT `candidate_public_updates_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `candidate_public_updates_ibfk_2` FOREIGN KEY (`published_by`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidate_references`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidate_references` (
  `candidate_id` bigint(20) unsigned NOT NULL,
  `record_number` tinyint(3) unsigned NOT NULL,
  `name` text DEFAULT NULL,
  `company_position` text DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `relationship` text DEFAULT NULL,
  PRIMARY KEY (`candidate_id`,`record_number`),
  CONSTRAINT `candidate_references_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `candidates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `candidate_number` varchar(32) NOT NULL,
  `invitation_id` bigint(20) unsigned NOT NULL,
  `email` varchar(254) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `position` varchar(150) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'Screening',
  `legacy_status` varchar(30) DEFAULT NULL,
  `workflow_legacy_status` varchar(40) DEFAULT NULL,
  `form_data` longtext NOT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) unsigned DEFAULT NULL,
  `legacy_candidate_number` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `candidate_number` (`candidate_number`),
  UNIQUE KEY `invitation_id` (`invitation_id`),
  KEY `idx_candidate_name` (`last_name`,`first_name`),
  KEY `idx_candidate_status` (`status`),
  KEY `idx_candidate_archive` (`archived_at`),
  KEY `idx_candidate_submitted` (`submitted_at`),
  KEY `fk_candidate_archiver` (`archived_by`),
  CONSTRAINT `fk_candidate_archiver` FOREIGN KEY (`archived_by`) REFERENCES `admins` (`id`),
  CONSTRAINT `fk_candidate_invitation` FOREIGN KEY (`invitation_id`) REFERENCES `invitations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `department_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `department_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(150) NOT NULL,
  `name` varchar(150) NOT NULL,
  `department_name` varchar(150) NOT NULL DEFAULT '',
  `position` varchar(150) NOT NULL DEFAULT '',
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `group_name` varchar(150) NOT NULL DEFAULT '',
  `account_status` varchar(20) NOT NULL DEFAULT 'Active',
  `archived_at` datetime DEFAULT NULL,
  `access_version` bigint(20) unsigned NOT NULL DEFAULT 0,
  `revision` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hr_email_archives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_email_archives` (
  `message_id` bigint(20) unsigned NOT NULL,
  `archived_by` bigint(20) unsigned NOT NULL,
  `archived_at` datetime NOT NULL,
  PRIMARY KEY (`message_id`),
  KEY `archived_at` (`archived_at`),
  KEY `archived_by` (`archived_by`),
  CONSTRAINT `hr_email_archives_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `hr_email_outbox` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hr_email_archives_ibfk_2` FOREIGN KEY (`archived_by`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hr_email_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_email_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` bigint(20) unsigned NOT NULL,
  `status` enum('Sent','Failed') NOT NULL,
  `provider_message_id` varchar(255) DEFAULT NULL,
  `error_message` varchar(1000) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `message_id` (`message_id`),
  CONSTRAINT `hr_email_attempts_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `hr_email_outbox` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hr_email_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_email_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` bigint(20) unsigned NOT NULL,
  `event` varchar(32) NOT NULL,
  `admin_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `message_id` (`message_id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `hr_email_events_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `hr_email_outbox` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hr_email_events_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hr_email_outbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_email_outbox` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint(20) unsigned DEFAULT NULL,
  `template_name` varchar(150) NOT NULL,
  `template_revision` bigint(20) unsigned NOT NULL,
  `candidate_id` bigint(20) unsigned DEFAULT NULL,
  `invitation_id` bigint(20) unsigned DEFAULT NULL,
  `recipient_name` varchar(400) NOT NULL,
  `recipient_email` varchar(254) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `status` enum('Prepared','Sent','Failed','Cancelled') NOT NULL DEFAULT 'Prepared',
  `is_preview` tinyint(1) NOT NULL DEFAULT 0,
  `preparation_key` char(64) NOT NULL,
  `preparation_hash` char(64) NOT NULL,
  `revision` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `last_error` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `preparation_key` (`preparation_key`),
  KEY `status` (`status`,`created_at`),
  KEY `template_id` (`template_id`),
  KEY `candidate_id` (`candidate_id`),
  KEY `invitation_id` (`invitation_id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `hr_email_outbox_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `hr_email_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hr_email_outbox_ibfk_2` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hr_email_outbox_ibfk_3` FOREIGN KEY (`invitation_id`) REFERENCES `invitations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hr_email_outbox_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`),
  CONSTRAINT `hr_email_outbox_ibfk_5` FOREIGN KEY (`updated_by`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hr_email_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_email_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` varchar(32) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `revision` bigint(20) unsigned NOT NULL DEFAULT 0,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `hr_email_templates_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hr_staff_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_staff_profiles` (
  `admin_id` bigint(20) unsigned NOT NULL,
  `photo` mediumtext NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`admin_id`),
  CONSTRAINT `hr_staff_profiles_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hr_tracking_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_tracking_settings` (
  `id` tinyint(3) unsigned NOT NULL,
  `started_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invitations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(254) NOT NULL,
  `code_hash` char(64) DEFAULT NULL,
  `access_code` varchar(8) DEFAULT NULL,
  `position` varchar(150) DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `redeemed_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) unsigned DEFAULT NULL,
  `applicant_id` varchar(32) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `draft_form` longtext DEFAULT NULL,
  `draft_step` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `draft_revision` bigint(20) unsigned NOT NULL DEFAULT 0,
  `draft_saved_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_hash` (`code_hash`),
  UNIQUE KEY `uq_invitation_applicant_id` (`applicant_id`),
  KEY `idx_invitation_email` (`email`),
  KEY `fk_invitation_admin` (`created_by`),
  CONSTRAINT `fk_invitation_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manpower_request_approval_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `manpower_request_approval_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_number` varchar(32) NOT NULL,
  `admin_id` bigint(20) unsigned NOT NULL,
  `previous_decision` varchar(12) NOT NULL,
  `decision` varchar(12) NOT NULL,
  `notes` text DEFAULT NULL,
  `revision` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `date_approved` date DEFAULT NULL,
  `position_category` varchar(3) DEFAULT NULL,
  `lead_time_days` smallint(5) unsigned DEFAULT NULL,
  `hiring_due_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mrf_approval_revision` (`batch_number`,`revision`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `manpower_request_approval_history_ibfk_1` FOREIGN KEY (`batch_number`) REFERENCES `manpower_request_approvals` (`batch_number`) ON DELETE CASCADE,
  CONSTRAINT `manpower_request_approval_history_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manpower_request_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `manpower_request_approvals` (
  `batch_number` varchar(32) NOT NULL,
  `request_id` bigint(20) unsigned NOT NULL,
  `decision` enum('Pending','Approved','Denied') NOT NULL DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `revision` bigint(20) unsigned NOT NULL DEFAULT 0,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `date_approved` date DEFAULT NULL,
  `position_category` varchar(3) DEFAULT NULL,
  `lead_time_days` smallint(5) unsigned DEFAULT NULL,
  `hiring_due_date` date DEFAULT NULL,
  PRIMARY KEY (`batch_number`),
  UNIQUE KEY `request_id` (`request_id`),
  KEY `idx_mrf_approval_decision` (`decision`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `manpower_request_approvals_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `manpower_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `manpower_request_approvals_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manpower_request_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `manpower_request_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned NOT NULL,
  `admin_id` bigint(20) unsigned NOT NULL,
  `previous_status` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL,
  `hr_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_mrf_history_request` (`request_id`),
  KEY `fk_mrf_history_admin` (`admin_id`),
  CONSTRAINT `fk_mrf_history_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `fk_mrf_history_request` FOREIGN KEY (`request_id`) REFERENCES `manpower_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manpower_request_imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `manpower_request_imports` (
  `request_id` bigint(20) unsigned NOT NULL,
  `admin_id` bigint(20) unsigned NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `mime_type` varchar(150) NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `file_sha256` char(64) NOT NULL,
  `original_file` mediumblob NOT NULL,
  `extracted_text` mediumtext NOT NULL,
  `imported_at` datetime NOT NULL,
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `file_sha256` (`file_sha256`),
  KEY `fk_mrf_import_admin` (`admin_id`),
  CONSTRAINT `fk_mrf_import_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `fk_mrf_import_request` FOREIGN KEY (`request_id`) REFERENCES `manpower_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manpower_request_slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `manpower_request_slots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned NOT NULL,
  `slot_number` smallint(5) unsigned NOT NULL,
  `reference_number` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_number` (`reference_number`),
  UNIQUE KEY `uq_mrf_slot` (`request_id`,`slot_number`),
  CONSTRAINT `fk_mrf_slot_request` FOREIGN KEY (`request_id`) REFERENCES `manpower_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manpower_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `manpower_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mrf_number` varchar(40) NOT NULL,
  `department_user_id` bigint(20) unsigned NOT NULL,
  `requestor_name` varchar(150) NOT NULL,
  `requestor_position` varchar(150) NOT NULL,
  `position_title` varchar(150) NOT NULL,
  `department_name` varchar(150) NOT NULL,
  `site_location` varchar(250) NOT NULL,
  `headcount` smallint(5) unsigned NOT NULL,
  `form_data` longtext NOT NULL,
  `status` enum('Pending','In Progress','Completed','Rejected','On Hold','Cancelled') NOT NULL DEFAULT 'Pending',
  `hr_notes` text DEFAULT NULL,
  `revision` bigint(20) unsigned NOT NULL DEFAULT 0,
  `submission_key` char(64) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `batch_number` varchar(32) DEFAULT NULL,
  `headcount_index` smallint(5) unsigned NOT NULL DEFAULT 1,
  `legacy_mrf_number` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mrf_number` (`mrf_number`),
  UNIQUE KEY `uq_mrf_submission` (`department_user_id`,`submission_key`),
  UNIQUE KEY `uq_mrf_batch_headcount` (`batch_number`,`headcount_index`),
  KEY `idx_mrf_status_created` (`status`,`created_at`),
  KEY `idx_mrf_owner_created` (`department_user_id`,`created_at`),
  KEY `fk_mrf_admin` (`updated_by`),
  CONSTRAINT `fk_mrf_admin` FOREIGN KEY (`updated_by`) REFERENCES `admins` (`id`),
  CONSTRAINT `fk_mrf_department` FOREIGN KEY (`department_user_id`) REFERENCES `department_users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mrf_counters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mrf_counters` (
  `request_year` smallint(5) unsigned NOT NULL,
  `last_number` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`request_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mrf_hr_assignment_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mrf_hr_assignment_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned NOT NULL,
  `previous_hr_id` bigint(20) unsigned DEFAULT NULL,
  `hr_admin_id` bigint(20) unsigned DEFAULT NULL,
  `admin_id` bigint(20) unsigned NOT NULL,
  `revision` bigint(20) unsigned NOT NULL,
  `source` varchar(24) NOT NULL,
  `reason` varchar(2000) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mrf_hr_history` (`request_id`,`id`),
  KEY `previous_hr_id` (`previous_hr_id`),
  KEY `hr_admin_id` (`hr_admin_id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `mrf_hr_assignment_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `manpower_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mrf_hr_assignment_history_ibfk_2` FOREIGN KEY (`previous_hr_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `mrf_hr_assignment_history_ibfk_3` FOREIGN KEY (`hr_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `mrf_hr_assignment_history_ibfk_4` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mrf_hr_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mrf_hr_assignments` (
  `request_id` bigint(20) unsigned NOT NULL,
  `hr_admin_id` bigint(20) unsigned DEFAULT NULL,
  `revision` bigint(20) unsigned NOT NULL DEFAULT 0,
  `assigned_by` bigint(20) unsigned NOT NULL,
  `assigned_at` datetime NOT NULL,
  `source` varchar(24) NOT NULL,
  PRIMARY KEY (`request_id`),
  KEY `idx_mrf_hr_owner` (`hr_admin_id`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `mrf_hr_assignments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `manpower_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mrf_hr_assignments_ibfk_2` FOREIGN KEY (`hr_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `mrf_hr_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mrf_requestor_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mrf_requestor_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `requestor_id` bigint(20) unsigned NOT NULL,
  `admin_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(40) NOT NULL,
  `details_json` text NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `requestor_id` (`requestor_id`,`id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `mrf_requestor_events_ibfk_1` FOREIGN KEY (`requestor_id`) REFERENCES `department_users` (`id`),
  CONSTRAINT `mrf_requestor_events_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
