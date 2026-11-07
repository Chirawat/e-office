  -- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
  --
  -- Host: localhost    Database: yii2advanced
  -- ------------------------------------------------------
  -- Server version	10.4.28-MariaDB

  /*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
  /*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
  /*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
  /*!40101 SET NAMES utf8mb4 */;
  /*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
  /*!40103 SET TIME_ZONE='+00:00' */;
  /*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
  /*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
  /*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
  /*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

  --
  -- Table structure for table `audit_log`
  --

  DROP TABLE IF EXISTS `audit_log`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `audit_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `document_id` int(11) DEFAULT NULL,
    `user_id` int(11) DEFAULT NULL,
    `event` varchar(120) NOT NULL,
    `detail` text DEFAULT NULL,
    `ip_address` varchar(64) DEFAULT NULL,
    `created_at` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx-audit_log-document_id` (`document_id`),
    KEY `fk-audit_log-user_id` (`user_id`),
    CONSTRAINT `fk-audit_log-document_id` FOREIGN KEY (`document_id`) REFERENCES `document` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk-audit_log-user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
  ) ENGINE=InnoDB AUTO_INCREMENT=712 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `department`
  --

  DROP TABLE IF EXISTS `department`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `department` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(120) NOT NULL,
    `code` varchar(40) DEFAULT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `sort_order` int(11) NOT NULL DEFAULT 100,
    `created_at` int(11) NOT NULL,
    `updated_at` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx-department-is_active` (`is_active`)
  ) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `document`
  --

  DROP TABLE IF EXISTS `document`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `document` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `receive_no` varchar(50) NOT NULL,
    `receive_date` date NOT NULL,
    `receive_time` time DEFAULT NULL,
    `external_no` varchar(120) DEFAULT NULL,
    `external_date` date DEFAULT NULL,
    `sender` varchar(255) NOT NULL,
    `subject` varchar(500) NOT NULL,
    `summary` text DEFAULT NULL,
    `urgency` varchar(30) NOT NULL DEFAULT 'normal',
    `confidentiality` varchar(30) NOT NULL DEFAULT 'normal',
    `department_id` int(11) DEFAULT NULL,
    `approver_id` int(11) DEFAULT NULL,
    `due_date` date DEFAULT NULL,
    `status` varchar(40) NOT NULL DEFAULT 'registered',
    `note` text DEFAULT NULL,
    `created_by` int(11) DEFAULT NULL,
    `updated_by` int(11) DEFAULT NULL,
    `created_at` int(11) NOT NULL,
    `updated_at` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx-document-receive_no` (`receive_no`),
    KEY `idx-document-status` (`status`),
    KEY `idx-document-department_id` (`department_id`),
    KEY `idx-document-receive_date` (`receive_date`),
    KEY `fk-document-created_by` (`created_by`),
    KEY `fk-document-updated_by` (`updated_by`),
    KEY `idx-document-approver_id` (`approver_id`),
    CONSTRAINT `fk-document-approver_id` FOREIGN KEY (`approver_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk-document-created_by` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk-document-department_id` FOREIGN KEY (`department_id`) REFERENCES `department` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk-document-updated_by` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
  ) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `document_assignment`
  --

  DROP TABLE IF EXISTS `document_assignment`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `document_assignment` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `document_id` int(11) NOT NULL,
    `department_id` int(11) DEFAULT NULL,
    `assigned_to` int(11) DEFAULT NULL,
    `assigned_by` int(11) DEFAULT NULL,
    `instruction` text DEFAULT NULL,
    `status` varchar(40) NOT NULL DEFAULT 'pending',
    `due_date` date DEFAULT NULL,
    `created_at` int(11) NOT NULL,
    `updated_at` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx-document_assignment-document_id` (`document_id`),
    KEY `fk-document_assignment-department_id` (`department_id`),
    KEY `fk-document_assignment-assigned_to` (`assigned_to`),
    KEY `fk-document_assignment-assigned_by` (`assigned_by`),
    CONSTRAINT `fk-document_assignment-assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk-document_assignment-assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk-document_assignment-department_id` FOREIGN KEY (`department_id`) REFERENCES `department` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk-document_assignment-document_id` FOREIGN KEY (`document_id`) REFERENCES `document` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `document_file`
  --

  DROP TABLE IF EXISTS `document_file`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `document_file` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `document_id` int(11) NOT NULL,
    `file_type` varchar(40) NOT NULL DEFAULT 'original',
    `original_name` varchar(255) NOT NULL,
    `storage_path` varchar(500) NOT NULL,
    `mime_type` varchar(120) DEFAULT NULL,
    `file_size` int(11) DEFAULT NULL,
    `created_by` int(11) DEFAULT NULL,
    `created_at` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx-document_file-document_id` (`document_id`),
    KEY `idx-document_file-file_type` (`file_type`),
    KEY `fk-document_file-created_by` (`created_by`),
    CONSTRAINT `fk-document_file-created_by` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk-document_file-document_id` FOREIGN KEY (`document_id`) REFERENCES `document` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `document_read`
  --

  DROP TABLE IF EXISTS `document_read`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `document_read` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `document_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `read_at` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx-doc_read-user_doc` (`user_id`,`document_id`),
    KEY `idx-doc_read-doc` (`document_id`),
    KEY `idx-doc_read-user` (`user_id`),
    CONSTRAINT `fk-doc_read-doc` FOREIGN KEY (`document_id`) REFERENCES `document` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk-doc_read-user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `document_stamp`
  --

  DROP TABLE IF EXISTS `document_stamp`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `document_stamp` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `document_id` int(11) NOT NULL,
    `stamp_type` varchar(60) NOT NULL,
    `page_no` int(11) NOT NULL DEFAULT 1,
    `position_x` decimal(8,2) DEFAULT NULL,
    `position_y` decimal(8,2) DEFAULT NULL,
    `content` text DEFAULT NULL,
    `created_by` int(11) DEFAULT NULL,
    `created_at` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx-document_stamp-document_id` (`document_id`),
    KEY `fk-document_stamp-created_by` (`created_by`),
    CONSTRAINT `fk-document_stamp-created_by` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk-document_stamp-document_id` FOREIGN KEY (`document_id`) REFERENCES `document` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB AUTO_INCREMENT=191 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `line_login_session`
  --

  DROP TABLE IF EXISTS `line_login_session`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `line_login_session` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `session_token` varchar(64) NOT NULL,
    `login_code` varchar(20) NOT NULL,
    `user_id` int(11) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` varchar(255) DEFAULT NULL,
    `status` varchar(20) NOT NULL DEFAULT 'pending',
    `expires_at` int(11) NOT NULL,
    `created_at` int(11) NOT NULL,
    `updated_at` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx-line_login_session-session_token` (`session_token`),
    UNIQUE KEY `idx-line_login_session-login_code` (`login_code`),
    KEY `idx-line_login_session-status` (`status`),
    KEY `idx-line_login_session-expires_at` (`expires_at`),
    KEY `fk-line_login_session-user_id` (`user_id`),
    CONSTRAINT `fk-line_login_session-user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB AUTO_INCREMENT=310 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `memo`
  --

  DROP TABLE IF EXISTS `memo`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `memo` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `memo_no` varchar(50) DEFAULT NULL,
    `memo_date` date NOT NULL,
    `department_id` int(11) DEFAULT NULL,
    `subject` varchar(500) NOT NULL,
    `to_text` varchar(255) NOT NULL DEFAULT 'ผู้อำนวยการโรงเรียน',
    `background` text DEFAULT NULL,
    `facts` text DEFAULT NULL,
    `legal` text DEFAULT NULL,
    `consideration` text DEFAULT NULL,
    `proposal` text DEFAULT NULL,
    `closing_text` varchar(255) DEFAULT NULL,
    `status` varchar(40) NOT NULL DEFAULT 'draft',
    `current_actor_id` int(11) DEFAULT NULL,
    `created_by` int(11) DEFAULT NULL,
    `updated_by` int(11) DEFAULT NULL,
    `created_at` int(11) NOT NULL,
    `updated_at` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx-memo-memo_no` (`memo_no`),
    KEY `idx-memo-status` (`status`),
    KEY `idx-memo-department_id` (`department_id`),
    KEY `idx-memo-current_actor_id` (`current_actor_id`),
    KEY `fk-memo-created_by` (`created_by`),
    KEY `fk-memo-updated_by` (`updated_by`),
    CONSTRAINT `fk-memo-created_by` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk-memo-current_actor_id` FOREIGN KEY (`current_actor_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk-memo-department_id` FOREIGN KEY (`department_id`) REFERENCES `department` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk-memo-updated_by` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
  ) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `memo_action`
  --

  DROP TABLE IF EXISTS `memo_action`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `memo_action` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `memo_id` int(11) NOT NULL,
    `actor_id` int(11) DEFAULT NULL,
    `action` varchar(60) NOT NULL,
    `from_status` varchar(40) DEFAULT NULL,
    `to_status` varchar(40) DEFAULT NULL,
    `comment` text DEFAULT NULL,
    `created_at` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx-memo_action-memo_id` (`memo_id`),
    KEY `fk-memo_action-actor_id` (`actor_id`),
    CONSTRAINT `fk-memo_action-actor_id` FOREIGN KEY (`actor_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk-memo_action-memo_id` FOREIGN KEY (`memo_id`) REFERENCES `memo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `memo_file`
  --

  DROP TABLE IF EXISTS `memo_file`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `memo_file` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `memo_id` int(11) NOT NULL,
    `original_name` varchar(255) NOT NULL,
    `storage_path` varchar(500) NOT NULL,
    `mime_type` varchar(120) DEFAULT NULL,
    `file_size` int(11) DEFAULT NULL,
    `description` varchar(255) DEFAULT NULL,
    `created_by` int(11) DEFAULT NULL,
    `created_at` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx-memo_file-memo_id` (`memo_id`),
    KEY `fk-memo_file-created_by` (`created_by`),
    CONSTRAINT `fk-memo_file-created_by` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk-memo_file-memo_id` FOREIGN KEY (`memo_id`) REFERENCES `memo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `memo_read`
  --

  DROP TABLE IF EXISTS `memo_read`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `memo_read` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `memo_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `read_at` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx-memo_read-user_memo` (`user_id`,`memo_id`),
    KEY `idx-memo_read-memo` (`memo_id`),
    KEY `idx-memo_read-user` (`user_id`),
    CONSTRAINT `fk-memo_read-memo` FOREIGN KEY (`memo_id`) REFERENCES `memo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk-memo_read-user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `memo_stamp_position`
  --

  DROP TABLE IF EXISTS `memo_stamp_position`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `memo_stamp_position` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `memo_id` int(11) NOT NULL,
    `stamp_type` varchar(60) NOT NULL,
    `position_x` decimal(8,2) DEFAULT NULL,
    `position_y` decimal(8,2) DEFAULT NULL,
    `created_by` int(11) DEFAULT NULL,
    `created_at` int(11) DEFAULT NULL,
    `updated_at` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx-memo_stamp_position-memo_id-stamp_type` (`memo_id`,`stamp_type`),
    CONSTRAINT `fk-memo_stamp_position-memo_id` FOREIGN KEY (`memo_id`) REFERENCES `memo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `migration`
  --

  DROP TABLE IF EXISTS `migration`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `migration` (
    `version` varchar(180) NOT NULL,
    `apply_time` int(11) DEFAULT NULL,
    PRIMARY KEY (`version`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `system_setting`
  --

  DROP TABLE IF EXISTS `system_setting`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `system_setting` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(64) NOT NULL,
    `setting_value` text DEFAULT NULL,
    `setting_group` varchar(32) DEFAULT 'general',
    `description` varchar(255) DEFAULT NULL,
    `updated_at` int(11) DEFAULT NULL,
    `updated_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`),
    UNIQUE KEY `idx-system_setting-key` (`setting_key`),
    KEY `idx-system_setting-group` (`setting_group`)
  ) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `user`
  --

  DROP TABLE IF EXISTS `user`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `user` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(255) NOT NULL,
    `fullname` varchar(150) DEFAULT NULL,
    `phone` varchar(30) DEFAULT NULL,
    `id_card` varchar(20) DEFAULT NULL,
    `auth_key` varchar(32) NOT NULL,
    `password_hash` varchar(255) NOT NULL,
    `password_reset_token` varchar(255) DEFAULT NULL,
    `email` varchar(255) NOT NULL,
    `role` varchar(40) NOT NULL DEFAULT 'officer',
    `department_id` int(11) DEFAULT NULL,
    `signature_path` varchar(500) DEFAULT NULL,
    `line_user_id` varchar(80) DEFAULT NULL,
    `line_display_name` varchar(120) DEFAULT NULL,
    `line_link_token` varchar(40) DEFAULT NULL,
    `line_link_token_expires_at` int(11) DEFAULT NULL,
    `line_linked_at` int(11) DEFAULT NULL,
    `status` smallint(6) NOT NULL DEFAULT 10,
    `created_at` int(11) NOT NULL,
    `updated_at` int(11) NOT NULL,
    `verification_token` varchar(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`),
    UNIQUE KEY `password_reset_token` (`password_reset_token`),
    UNIQUE KEY `idx-user-line_user_id` (`line_user_id`),
    UNIQUE KEY `idx-user-line_link_token` (`line_link_token`),
    KEY `idx-user-role` (`role`),
    KEY `idx-user-department_id` (`department_id`),
    KEY `idx-user-id_card` (`id_card`),
    KEY `idx-user-phone` (`phone`),
    CONSTRAINT `fk-user-department_id` FOREIGN KEY (`department_id`) REFERENCES `department` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
  ) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `user_department`
  --

  DROP TABLE IF EXISTS `user_department`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `user_department` (
    `user_id` int(11) NOT NULL,
    `department_id` int(11) NOT NULL,
    `created_at` int(11) NOT NULL,
    PRIMARY KEY (`user_id`,`department_id`),
    KEY `fk-user_department-department_id` (`department_id`),
    CONSTRAINT `fk-user_department-department_id` FOREIGN KEY (`department_id`) REFERENCES `department` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk-user_department-user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;

  --
  -- Table structure for table `workflow_action`
  --

  DROP TABLE IF EXISTS `workflow_action`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `workflow_action` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `document_id` int(11) NOT NULL,
    `actor_id` int(11) DEFAULT NULL,
    `action` varchar(60) NOT NULL,
    `from_status` varchar(40) DEFAULT NULL,
    `to_status` varchar(40) DEFAULT NULL,
    `comment` text DEFAULT NULL,
    `created_at` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx-workflow_action-document_id` (`document_id`),
    KEY `fk-workflow_action-actor_id` (`actor_id`),
    CONSTRAINT `fk-workflow_action-actor_id` FOREIGN KEY (`actor_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk-workflow_action-document_id` FOREIGN KEY (`document_id`) REFERENCES `document` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB AUTO_INCREMENT=176 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  /*!40101 SET character_set_client = @saved_cs_client */;
  /*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

  /*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
  /*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
  /*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
  /*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
  /*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
  /*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
  /*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

  -- Dump completed on 2026-11-06 10:15:18
  -- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
  --
  -- Host: localhost    Database: yii2advanced
  -- ------------------------------------------------------
  -- Server version	10.4.28-MariaDB

  /*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
  /*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
  /*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
  /*!40101 SET NAMES utf8mb4 */;
  /*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
  /*!40103 SET TIME_ZONE='+00:00' */;
  /*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
  /*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
  /*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

  --
  -- Dumping data for table `department`
  --

  LOCK TABLES `department` WRITE;
  /*!40000 ALTER TABLE `department` DISABLE KEYS */;
  INSERT INTO `department` VALUES (1,'วิชาการ','academic',1,10,1781843964,1784016050),(2,'บุคคล','personnel',1,20,1781843964,1784016050),(3,'งบประมาณ','budget',1,30,1781843964,1784016050),(4,'บริหารทั่วไป','general',1,40,1781843964,1784016050),(5,'กิจการนักเรียน','student_affairs',1,50,1781843964,1784016050);
  /*!40000 ALTER TABLE `department` ENABLE KEYS */;
  UNLOCK TABLES;

  --
  -- Dumping data for table `system_setting`
  --

  LOCK TABLES `system_setting` WRITE;
  /*!40000 ALTER TABLE `system_setting` DISABLE KEYS */;
  INSERT INTO `system_setting` VALUES (1,'school_name','โรงเรียนกุดชุมวิทยาคม','general','ชื่อสถานศึกษา',1793806650,1),(2,'director_name','นายมนตรี  แจ่มศรี','executive','ชื่อผู้อำนวยการโรงเรียน',1793806650,1),(3,'director_position','ผู้อำนวยการโรงเรียนกุดชุมวิทยาคม','executive','ตำแหน่งผู้อำนวยการโรงเรียน',1793806650,1),(4,'acting_director_name','นายจิราวัฒน์  นางาม','executive','ชื่อรองผู้อำนวยการ รักษาการในตำแหน่งผู้อำนวยการโรงเรียน',1793806650,1),(5,'acting_director_position','รองผู้อำนวยการ รักษาการในตำแหน่งผู้อำนวยการโรงเรียนกุดชุมวิทยาคม','executive','ตำแหน่งรักษาการแทนผู้อำนวยการโรงเรียน',1793806650,1),(6,'is_acting_director','0','executive','ใช้ชื่อรักษาการแทนผู้อำนวยการ (0=ไม่ใช้, 1=ใช้)',1793806650,1),(7,'doc_receive_start_no','100','document','เลขรับหนังสือเริ่มต้น',1793806650,1),(8,'doc_receive_year','2569','document','ปี พ.ศ. ของเลขรับหนังสือ',1793806650,1),(9,'acting_director_user_id','111','executive','User ID ผู้รักษาการแทนผู้อำนวยการ',1793806650,1),(10,'auto_approve_signup','1','user','อนุมัติการสมัครสมาชิกใหม่อัตโนมัติ (1 = เปิดใช้งาน, 0 = ปิดใช้งาน/ต้องรอ Admin อนุมัติ)',1793806993,1),(11,'document_approver_id','1','document','ผู้ตรวจสอบ',1793858878,NULL);
  /*!40000 ALTER TABLE `system_setting` ENABLE KEYS */;
  UNLOCK TABLES;

  --
  -- Dumping data for table `migration`
  --

  LOCK TABLES `migration` WRITE;
  /*!40000 ALTER TABLE `migration` DISABLE KEYS */;
  INSERT INTO `migration` VALUES ('m000000_000000_base',1781843964),('m130524_201442_init',1781843964),('m190124_110200_add_verification_token_column_to_user_table',1781843964),('m260619_000001_create_eoffice_tables',1781843965),('m260619_000002_add_role_to_user',1781853530),('m260619_000003_add_signature_to_user',1781857945),('m260620_000004_add_department_to_user',1781920649),('m260620_000005_create_user_department',1781921058),('m260620_000006_add_line_fields_to_user',1781941451),('m260620_000007_create_memo_tables',1781973141),('m260621_000008_create_memo_stamp_position',1782036472),('m261030_000009_add_profile_fields_and_line_login_session',1793334182),('m261103_000010_create_system_setting',1793683737),('m261104_000011_create_document_and_memo_read',1793748509),('m261104_000012_convert_user_table_to_utf8mb4',1793804363),('m261104_000013_add_auto_approve_setting',1793806376),('m261105_000014_add_summary_to_document_table',1793853207),('m261105_000015_add_approver_id_to_document_table',1793855136);
  /*!40000 ALTER TABLE `migration` ENABLE KEYS */;
  UNLOCK TABLES;

  --
  -- Dumping data for table `user_department`
  --

  LOCK TABLES `user_department` WRITE;
  /*!40000 ALTER TABLE `user_department` DISABLE KEYS */;
  INSERT INTO `user_department` VALUES (109,4,1793352548),(110,4,1793352989),(111,4,1793696429),(112,4,1793696634),(113,4,1793697364),(114,4,1793697382),(115,2,1793715757),(116,1,1793782975),(117,1,1793782995),(118,1,1793783058),(119,2,1793783186),(120,1,1793783189),(121,1,1793783203),(122,1,1793783208),(123,5,1793783213),(124,4,1793783224),(125,1,1793783240),(126,4,1793783327),(127,5,1793783444),(128,4,1793783602),(129,5,1793783621),(130,1,1793783839),(134,4,1793807114),(135,4,1793807458),(136,4,1793807794),(137,4,1793852152);
  /*!40000 ALTER TABLE `user_department` ENABLE KEYS */;
  UNLOCK TABLES;
  /*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

  /*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
  /*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
  /*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
  /*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
  /*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
  /*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

  -- Dump completed on 2026-11-06 10:15:18
  -- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
  --
  -- Host: localhost    Database: yii2advanced
  -- ------------------------------------------------------
  -- Server version	10.4.28-MariaDB

  /*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
  /*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
  /*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
  /*!40101 SET NAMES utf8mb4 */;
  /*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
  /*!40103 SET TIME_ZONE='+00:00' */;
  /*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
  /*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
  /*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

  --
  -- Dumping data for table `user`
  --
  -- WHERE:  status != 0

  LOCK TABLES `user` WRITE;
  /*!40000 ALTER TABLE `user` DISABLE KEYS */;
  INSERT INTO `user` VALUES (1,'admin',NULL,NULL,NULL,'BaQVoHw58k5grs2GrJuyByPuw5N_NniU','$2y$13$9HpjCYF4F6SOgqmmmzeEMe6YAX9eHTxZmBMpkA7RrI6USuI.Kzwbi',NULL,'admin@example.local','admin',NULL,NULL,NULL,NULL,NULL,NULL,NULL,10,1781844001,1793351545,'s9vgTeXJOrqDMd22ugKUAb1R0lDRSxBn'),(112,'1319900528218','ขวัญจิรา ประกอบสุข','0821536531',NULL,'Zk6ANh8V3XrzMnRDG_PqA0-m1B9jyKyY','$2y$13$MORXMQETbKaxo1WpEqF0YukdPfBDo0yfG.zStwAfpyxGSWK69vNc.',NULL,'keawbenity@gmail.com','officer',4,NULL,'Ubba65493212b862485b17ad5d870e97a','kaew Khanjira',NULL,NULL,1793696634,10,1793696634,1793782099,'5qFlwkAmVFDMJvlgQWl6Qibz_TG1kwow'),(113,'Supakorn101','Mr.Samart-Cool','0624509216',NULL,'TXE-lcW7Yw2FWbl0Z-577V7H5SsXynj0','$2y$13$EoZiIP2aC7yuBJxcS0I1C.aTgwwj1V3XM9SveEdkbLfu1VJHrwjHy',NULL,'Supakara604@gmail.com','general_user',4,NULL,'Uc2e50376d48a1e6640893994a2dbdbbe','Mr.Samart-Cool',NULL,NULL,1793697363,10,1793697364,1793697406,'D6IgpfNaJO1aqT7V2i9ojRMpM7eKddX8'),(114,'1350300045404','รัตนาพร เวฬุวนาธร','0833693804',NULL,'5nRZKZQ12yHcKhc_T2quZB0r6xj_ROGU','$2y$13$9gs5N8qt5cbiGWiCd6aGUO4osZ54Dd6Qkr3j.dZETjV0OFET5me0e',NULL,'rattanaporn.kei2529@gmail.com','officer',4,NULL,'U333b3ffc47ce42e18c753132edc2c991','รัตนาพร เวฬุวนาธร',NULL,NULL,1793697381,10,1793697382,1793697481,'xi06Gx4hnRoMxiIu4dX8mprIIomXT-m3'),(115,'1350300113965','นางสาววิชุดา หงษ์คำ','0979185993',NULL,'QtKdlpDfIYBHXDDmYzA0e7zAsUDFlZcm','$2y$13$1wCwwG71XdHo92KUimgZj.ioekd8MXP3.eDJTzOb2NfOi4ZtO862G',NULL,'yuiikho256@gmail.com','general_user',2,NULL,'Ud0e6a99d837fad0377b0aaed5347fa29','WichudaWichudee',NULL,NULL,1793715756,10,1793715757,1793715779,'oYmNr_nSTMX03JQQXXvuhk9HnMMVW3ZP'),(116,'1350900007821','นายรชา องอาจ','0956623216',NULL,'PbvbEPeKLQABOLONC4obY9aHe_Gej3er','$2y$13$JtAtZOWyB97GXij3HS7aXuj0SQaZ6gAE4ALGUAupTKsVUnTokrDqy',NULL,'racha.edsci@gmail.com','general_user',1,NULL,'Ucb33b21c615b1ac14f52da9388350808','Palm M.',NULL,NULL,1793782974,10,1793782975,1793782984,'ef0jOH1k3qovODRTnVH977hUHsW2tw4E'),(117,'1103702541788','Amonthep Lummana','0981826081',NULL,'rsdhOBysImotIeQVwegX54rGk9HdESrB','$2y$13$tJ/wJpqgZgZ1zG8GRCtz6e73llgG9jWtslrSOdUpcaX24NuxBAlXC',NULL,'aeyamon1998@gmail.com','general_user',1,NULL,'U8be2ca67905a73f0f2a3f095a5bbd1fd','Amonthep Lummana',NULL,NULL,1793782995,10,1793782995,1793783058,'0hL1R3mI08yBMuk8E_ClMx8VUxr8CwfK'),(118,'Arisaraporn MP','นางสาวอริศราภรณ์ มูลเพ็ญ','0982081641',NULL,'ckMQK25V1MBANq31JToTpSCpwCqnDGvr','$2y$13$QNpMO8yMraF/gJ5TQZQf.OOLFCoTQsffPscuncsBK4WrtFhVYrYMW',NULL,'arisarapornmoonpen@gmail.com','general_user',1,NULL,'Uf4a89e13cf5249aa2a05bb5d2cf8ec4f','Arisaraporn',NULL,NULL,1793783058,10,1793783058,1793783067,'sYiR8cmlyQRzaATjDk3-gvVHyQBVoA__'),(119,'1119901884435','Arisarapa Tenpaksee','0984683126',NULL,'wnK3sWKH2kHXRDXzealFDcQLI51-m9Uj','$2y$13$CTxF5x62LxCckondk3oVGOT3bJc6yMYq9uY4R4RrnlM3o26tj19ga',NULL,'arisbeam23@hotmail.com','general_user',2,NULL,'U413e4d6885719a3f26cdd27e69c2067e','Arisa',NULL,NULL,1793783185,10,1793783186,1793783219,'nbWoqFmwKXkAUwCFGUvuoSthgEmISzM1'),(120,'Janya','จรรยา','0619491445',NULL,'QVj1pNaSLlUHFMvbc4XYoHbXt4kyZ9-G','$2y$13$MdxAVGqQJq3zPj5ws5IQaOIW6KiIp8P1uTEi2VvyQvMmEmJIh2hwy',NULL,'janya.pookpig@gmail.com','general_user',1,NULL,'Ua496718e247b3704871be65c8edb16d1','จรรยา',NULL,NULL,1793783188,10,1793783189,1793783257,'tMYk4xIq-yQfU--SVHDHJXqAzttNN7ZW'),(121,'Pattanakorn','พัฒนากร กำหอม','0999568647',NULL,'aZUn5U_P7vnhnAosJ7wr6odQY7IZ4gMl','$2y$13$RJfWRjSbbjeH2Vf6Yos7YO5Tuw9H9iwBlzhoXUr3M3Da2jq/tEsuS',NULL,'Pattanakorn6765@gmail.com','general_user',1,NULL,'Ueccfc9fd88a471a577cb7f9ffeb40a4c','KXXT',NULL,NULL,1793783202,10,1793783203,1793783263,'APgyHWKNvxbzl-gPHhsCtnNVQ9RKizOz'),(122,'1459900663432','Chonadda_PG','0979265141',NULL,'fokKifJT0zLeK9q1DAV1HiuGLzpCohIX','$2y$13$IZsdGWQ3xbGKORmVz5D5wOf5IzQkoPnzYTTaLMzNQiKYll6a7i1Nu',NULL,'chonaddap@schoolkc.ac.th','general_user',1,NULL,'Uadffa29f21d41001174bdc7357a62fa8','Chonadda_PG',NULL,NULL,1793783207,10,1793783208,1793783259,'ERQWfQf0KMxguw2kuSmuHwhscGnsqjZn'),(123,'1440500123009','worawoot.kc','0910135960',NULL,'7QMVGjhdYJXdOwDJFVeqgtEstJeQCVn0','$2y$13$H1XaetXjnTHmOGk0LB50cueK/qhFO6wwrjuchEobumdwMUk/mowIu',NULL,'Voravut07@schoolkc.ac.th','general_user',5,NULL,'U1aa544fe208140f272c792ee8b13065f','worawoot  mk519',NULL,NULL,1793783213,10,1793783213,1793783233,'oYRH7pCUAWSPApTJzXIo7np61jXiCfKn'),(124,'1350100128411','นายปรีดีวัฒน์ น้อยมาลา','0963964246',NULL,'N45IiC-hnsGgssAjWfNfUQ2ixFDYAcQo','$2y$13$Rx2UNLPrydw6gSYxmalVRewyUR3If61R5DbtD5f3VPg9.FQ4rH08C',NULL,'preedeewatnoimala@gmail.com','general_user',4,NULL,'U8c0be8ad5fca2e1e4dfa7da5c44492d4','แทนไท',NULL,NULL,1793783224,10,1793783224,1793783253,'nEC4aXtCh9wJXRZR9JiiB3NF7IQoQOPF'),(125,'Pukky','ณัฐวดี จันเก','0973209602',NULL,'FpXp4HbwM61A7HPbO_9spE-JIMr6UUd4','$2y$13$Y/.DniG9o5wb1TPd/ta0Meq/YuJugeRfDzAXNc4KG8f2o0UIR5J9K',NULL,'nuttapk2540@gmail.com','general_user',1,NULL,'U588e65304de0782227be2e77a99a838f','nuttapk',NULL,NULL,1793783239,10,1793783240,1793783266,'1BLeD3B__fcM4sNhJSVBnzRzkxg3d83U'),(126,'1350300105784','ไพศาล  เครือทอง','0653981535',NULL,'D0YcYfi-cvzWwpifXSvD4wxr-_gKtI7T','$2y$13$JexP66vTqF4G/ImJdBEfmu2fb0as5WI2BQNxPjh5Y47HxlFi6FEqG',NULL,'Pskhruaethong@gmail.com','general_user',4,NULL,'U51560700f85513ccebad872d226be7e0','ไพศาล  เครือทอง',NULL,NULL,1793783326,10,1793783327,1793783387,'I69jyvx-oa42dYrOPa-NapP-z05-z6w-'),(127,'1350300091571','TOP X-1','0828743071',NULL,'fR9Nuc9gb33KUoMhxWYRsmNsHf82lkoS','$2y$13$7YMV/MPQBPjddHGfigDWO.53waKrxv2NOWCbNkPQLobg6i8ddFQcG',NULL,'sansee307@gmail.com','general_user',5,NULL,'U4cf58d7c1ced80aa56282bd99a7fdf8a','TOP X-1',NULL,NULL,1793783443,10,1793783444,1793804066,'w8ywhdPoPgkDuSfQKuf__ky2M1tXuCg-'),(128,'1451100027178','Newtumbiogy','0611599065',NULL,'1rzGK2rVK61bczlx_698L4jpuLcLyseD','$2y$13$cxXIb9uWf0iLPIpw7Snqu.oL5mu7vVMHYHLlUulrRjIUuRLYvv8yy',NULL,'Biology999@gmail.com','general_user',4,NULL,'U0843e690918ebabc73e250d9eb8254eb','Newtumbiogy',NULL,NULL,1793783602,10,1793783602,1793804071,'4wcuORD5p5WtAzhhMbTgtlYX94JhkodF'),(129,'3350300194406','Sunead Sriwasut','0952655741',NULL,'1GMtlYNiOkOetuZ5H02lBNd3idp-5pW0','$2y$13$KLrCCf.szG64fK9u2dBLxuDEVZ6FrHNGXp/4Z8.PVZj4jd5jmezPG',NULL,'Sucy366299@gmail.com','general_user',5,NULL,'Udc4b49a1ec47644e8e98daf8d2b05bee','Sunead Sriwasut',NULL,NULL,1793783620,10,1793783621,1793804079,'4Apw7ieADEBjeoj9qMbNN-FGQAbtpZTO'),(130,'3101701143589','ครูปุ๊ก6395','0621628266',NULL,'TYNYg9myBaCLu5Q-VFkffB8k1eZp83cw','$2y$13$07olB28G5y.PHMRAWAnsuewb7wDcbUevjwyajPdr8bIw.UhOljzeK',NULL,'pooksriwarom@gmail.com','general_user',1,NULL,'U6c1c03ee3ec0914dc5668cd77dedb6da','ครูปุ๊ก6395',NULL,NULL,1793783839,10,1793783839,1793804075,'uGnnLSvZ9qMHSuNcYi4_9bxc5ZsoC7JO'),(136,'1350100186888','นายจิราวัฒน์  นางาม','0848313126',NULL,'od-6zjpIt8fMfMMm1W05P8uI4902XwGE','$2y$13$81WvgmkGEHUmr7yWMHUKx.g22ciys2uVpJggR8Mb1v.Uwuuyqb1DC',NULL,'chirawat@schoolkc.ac.th','officer',4,'uploads/signatures/136/signature.png','U204782e6fb13a3a26a7521f6f44c8505','Chirawat Nangam',NULL,NULL,1793807793,10,1793807794,1793860796,'zrSNDHTAE39gngTtmdVAKOovYyIUmVFV'),(137,'3350100143395','นายพิสุทธิ์ เศิกศิริ','0813607520',NULL,'D0aRaAUViDgA1ZfMTRl9ULyJOX37GLTw','$2y$13$gYltuBYGHGc1eeMAbUAyj.ZseWc0qWgnWxjg1YMCYrRb4VTZsobcq',NULL,'crutommy@gmail.com','general_user',4,NULL,'U4db0af91022f1918ba17c266796321bf','Crutommy(HS3LQL)',NULL,NULL,1793852152,10,1793852152,1793852152,'EyFTlAAIUV2IiYYO5nFfC7o2EANeawU-');
  /*!40000 ALTER TABLE `user` ENABLE KEYS */;
  UNLOCK TABLES;
  /*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

  /*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
  /*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
  /*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
  /*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
  /*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
  /*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

  -- Dump completed on 2026-11-06 10:15:18
