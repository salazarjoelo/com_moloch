-- 
-- Table structure for table `#__moloch_issues`
--

CREATE TABLE IF NOT EXISTS `#__moloch_issues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `alias` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `description` mediumtext NOT NULL,
  `address` varchar(500) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `catid` int(11) NOT NULL DEFAULT 0,
  `stepid` int(11) NOT NULL DEFAULT 1,
  `created` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_by_alias` varchar(255) NOT NULL DEFAULT '',
  `modified` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `modified_by` int(11) NOT NULL DEFAULT 0,
  `published` tinyint(4) NOT NULL DEFAULT 0,
  `checked_out` int(11) NOT NULL DEFAULT 0,
  `checked_out_time` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `publish_up` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `publish_down` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `votes_up` int(11) NOT NULL DEFAULT 0,
  `votes_down` int(11) NOT NULL DEFAULT 0,
  `hits` int(11) NOT NULL DEFAULT 0,
  `featured` tinyint(3) NOT NULL DEFAULT 0,
  `access` int(11) NOT NULL DEFAULT 1,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `metakey` text,
  `metadesc` text NOT NULL,
  `metadata` text NOT NULL,
  `params` text,
  PRIMARY KEY (`id`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_published` (`published`),
  KEY `idx_catid` (`catid`),
  KEY `idx_stepid` (`stepid`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_featured` (`featured`),
  KEY `idx_alias` (`alias`(191)),
  KEY `idx_location` (`latitude`,`longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 
-- Table structure for table `#__moloch_categories`
--

CREATE TABLE IF NOT EXISTS `#__moloch_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `alias` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `description` mediumtext,
  `color` varchar(7) DEFAULT '#3498db',
  `icon` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `published` tinyint(4) NOT NULL DEFAULT 1,
  `checked_out` int(11) NOT NULL DEFAULT 0,
  `checked_out_time` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `access` int(11) NOT NULL DEFAULT 1,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `created` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `created_by` int(11) NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `modified_by` int(11) NOT NULL DEFAULT 0,
  `params` text,
  PRIMARY KEY (`id`),
  KEY `idx_published` (`published`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_alias` (`alias`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 
-- Table structure for table `#__moloch_steps`
--

CREATE TABLE IF NOT EXISTS `#__moloch_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `alias` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `description` mediumtext,
  `color` varchar(7) DEFAULT '#3498db',
  `icon` varchar(100) DEFAULT NULL,
  `published` tinyint(4) NOT NULL DEFAULT 1,
  `checked_out` int(11) NOT NULL DEFAULT 0,
  `checked_out_time` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `access` int(11) NOT NULL DEFAULT 1,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `created` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `created_by` int(11) NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `modified_by` int(11) NOT NULL DEFAULT 0,
  `params` text,
  PRIMARY KEY (`id`),
  KEY `idx_published` (`published`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_ordering` (`ordering`),
  KEY `idx_alias` (`alias`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 
-- Table structure for table `#__moloch_attachments`
--

CREATE TABLE IF NOT EXISTS `#__moloch_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `issue_id` int(11) NOT NULL DEFAULT 0,
  `filename` varchar(255) NOT NULL DEFAULT '',
  `original_filename` varchar(255) NOT NULL DEFAULT '',
  `filepath` varchar(500) NOT NULL DEFAULT '',
  `filesize` bigint(20) NOT NULL DEFAULT 0,
  `mimetype` varchar(100) NOT NULL DEFAULT '',
  `file_type` enum('image','video','audio','document','other') NOT NULL DEFAULT 'other',
  `thumbnail` varchar(500) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `published` tinyint(4) NOT NULL DEFAULT 1,
  `created` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `created_by` int(11) NOT NULL DEFAULT 0,
  `access` int(11) NOT NULL DEFAULT 1,
  `hits` int(11) NOT NULL DEFAULT 0,
  `params` text,
  PRIMARY KEY (`id`),
  KEY `idx_issue_id` (`issue_id`),
  KEY `idx_published` (`published`),
  KEY `idx_file_type` (`file_type`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_access` (`access`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 
-- Table structure for table `#__moloch_comments`
--

CREATE TABLE IF NOT EXISTS `#__moloch_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `issue_id` int(11) NOT NULL DEFAULT 0,
  `parent_id` int(11) NOT NULL DEFAULT 0,
  `comment` mediumtext NOT NULL,
  `created` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_by_alias` varchar(255) NOT NULL DEFAULT '',
  `modified` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `modified_by` int(11) NOT NULL DEFAULT 0,
  `published` tinyint(4) NOT NULL DEFAULT 0,
  `checked_out` int(11) NOT NULL DEFAULT 0,
  `checked_out_time` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `access` int(11) NOT NULL DEFAULT 1,
  `params` text,
  PRIMARY KEY (`id`),
  KEY `idx_issue_id` (`issue_id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_published` (`published`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 
-- Table structure for table `#__moloch_votes`
--

CREATE TABLE IF NOT EXISTS `#__moloch_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `issue_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `user_ip` varchar(45) NOT NULL DEFAULT '',
  `vote` tinyint(4) NOT NULL DEFAULT 0 COMMENT '1 = up, -1 = down',
  `created` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_issue_user` (`issue_id`,`user_id`),
  UNIQUE KEY `idx_issue_ip` (`issue_id`,`user_ip`),
  KEY `idx_issue_id` (`issue_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 
-- Table structure for table `#__moloch_logs`
--

CREATE TABLE IF NOT EXISTS `#__moloch_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `issue_id` int(11) NOT NULL DEFAULT 0,
  `action` varchar(100) NOT NULL DEFAULT '',
  `description` mediumtext,
  `old_value` text,
  `new_value` text,
  `created` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_by_alias` varchar(255) NOT NULL DEFAULT '',
  `user_ip` varchar(45) NOT NULL DEFAULT '',
  `user_agent` text,
  `params` text,
  PRIMARY KEY (`id`),
  KEY `idx_issue_id` (`issue_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 
-- Table structure for table `#__moloch_notifications`
--

CREATE TABLE IF NOT EXISTS `#__moloch_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `issue_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `type` varchar(50) NOT NULL DEFAULT 'email',
  `recipient` varchar(255) NOT NULL DEFAULT '',
  `subject` varchar(255) NOT NULL DEFAULT '',
  `message` mediumtext,
  `sent` tinyint(4) NOT NULL DEFAULT 0,
  `sent_date` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `last_attempt` datetime NULL DEFAULT NULL,
  `error_message` text,
  `params` text,
  PRIMARY KEY (`id`),
  KEY `idx_issue_id` (`issue_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_sent` (`sent`),
  KEY `idx_type` (`type`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 
-- Table structure for table `#__moloch_subscriptions`
--

CREATE TABLE IF NOT EXISTS `#__moloch_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `issue_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'email',
  `created` datetime NOT NULL DEFAULT '1980-01-01 00:00:00',
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `token` varchar(32) NOT NULL DEFAULT '',
  `params` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_issue_user` (`issue_id`,`user_id`),
  UNIQUE KEY `idx_issue_email` (`issue_id`,`email`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_active` (`active`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Add foreign key constraints
--

ALTER TABLE `#__moloch_issues`
  ADD CONSTRAINT `fk_moloch_issues_category` FOREIGN KEY (`catid`) REFERENCES `#__moloch_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_moloch_issues_step` FOREIGN KEY (`stepid`) REFERENCES `#__moloch_steps` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `#__moloch_attachments`
  ADD CONSTRAINT `fk_moloch_attachments_issue` FOREIGN KEY (`issue_id`) REFERENCES `#__moloch_issues` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `#__moloch_comments`
  ADD CONSTRAINT `fk_moloch_comments_issue` FOREIGN KEY (`issue_id`) REFERENCES `#__moloch_issues` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `#__moloch_votes`
  ADD CONSTRAINT `fk_moloch_votes_issue` FOREIGN KEY (`issue_id`) REFERENCES `#__moloch_issues` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `#__moloch_logs`
  ADD CONSTRAINT `fk_moloch_logs_issue` FOREIGN KEY (`issue_id`) REFERENCES `#__moloch_issues` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `#__moloch_notifications`
  ADD CONSTRAINT `fk_moloch_notifications_issue` FOREIGN KEY (`issue_id`) REFERENCES `#__moloch_issues` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `#__moloch_subscriptions`
  ADD CONSTRAINT `fk_moloch_subscriptions_issue` FOREIGN KEY (`issue_id`) REFERENCES `#__moloch_issues` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;