--
-- Update SQL for Moloch Component v1.0.1
-- Author: Lic. Joel Salazar Ramírez <joel@edugame.digital>
-- Copyright (C) 2025. All rights reserved.
-- License: GNU General Public License version 2 or later
--

-- Add indexes for better performance
ALTER TABLE `#__moloch_issues` 
ADD INDEX `idx_published_featured` (`published`, `featured`),
ADD INDEX `idx_created_published` (`created`, `published`),
ADD INDEX `idx_catid_published` (`catid`, `published`),
ADD INDEX `idx_stepid_published` (`stepid`, `published`);

-- Add indexes for votes table
ALTER TABLE `#__moloch_votes`
ADD INDEX `idx_created` (`created`);

-- Add indexes for comments table  
ALTER TABLE `#__moloch_comments`
ADD INDEX `idx_created_published` (`created`, `published`);

-- Add indexes for logs table
ALTER TABLE `#__moloch_logs`
ADD INDEX `idx_created_issue` (`created`, `issue_id`);

-- Add indexes for notifications table
ALTER TABLE `#__moloch_notifications`
ADD INDEX `idx_created_sent` (`created`, `sent`);

-- Optimize table structure for better performance
ALTER TABLE `#__moloch_issues` 
MODIFY COLUMN `description` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
MODIFY COLUMN `metadesc` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
MODIFY COLUMN `metakey` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Update categories table with better structure
ALTER TABLE `#__moloch_categories`
ADD COLUMN `image_alt` VARCHAR(255) DEFAULT NULL AFTER `image`,
ADD COLUMN `note` TEXT DEFAULT NULL AFTER `description`;

-- Update steps table with additional fields
ALTER TABLE `#__moloch_steps`
ADD COLUMN `css_class` VARCHAR(100) DEFAULT NULL AFTER `color`,
ADD COLUMN `email_template` TEXT DEFAULT NULL AFTER `description`;

-- Add user preferences table for notifications
CREATE TABLE IF NOT EXISTS `#__moloch_user_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `preference_key` varchar(100) NOT NULL,
  `preference_value` text,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_preference` (`user_id`, `preference_key`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add geographic boundaries table for location filtering
CREATE TABLE IF NOT EXISTS `#__moloch_boundaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `boundary_data` longtext NOT NULL COMMENT 'GeoJSON polygon data',
  `color` varchar(7) DEFAULT '#3498db',
  `published` tinyint(4) NOT NULL DEFAULT '1',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) NOT NULL DEFAULT '0',
  `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `modified_by` int(11) NOT NULL DEFAULT '0',
  `ordering` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_published` (`published`),
  KEY `idx_ordering` (`ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add saved searches table
CREATE TABLE IF NOT EXISTS `#__moloch_saved_searches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `search_params` text NOT NULL COMMENT 'JSON encoded search parameters',
  `notify_new_results` tinyint(4) NOT NULL DEFAULT '0',
  `last_notification` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_notifications` (`notify_new_results`, `last_notification`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key for user preferences
ALTER TABLE `#__moloch_user_preferences`
ADD CONSTRAINT `fk_user_preferences_user` FOREIGN KEY (`user_id`) REFERENCES `#__users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Add foreign key for saved searches
ALTER TABLE `#__moloch_saved_searches`
ADD CONSTRAINT `fk_saved_searches_user` FOREIGN KEY (`user_id`) REFERENCES `#__users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Insert default user preference options
INSERT IGNORE INTO `#__moloch_user_preferences` (`user_id`, `preference_key`, `preference_value`) 
SELECT DISTINCT `created_by`, 'email_notifications', '1' 
FROM `#__moloch_issues` 
WHERE `created_by` > 0;

-- Update existing data to ensure consistency
UPDATE `#__moloch_issues` SET `alias` = LOWER(REPLACE(REPLACE(`title`, ' ', '-'), '--', '-')) WHERE `alias` = '' OR `alias` IS NULL;

UPDATE `#__moloch_categories` SET `alias` = LOWER(REPLACE(REPLACE(`title`, ' ', '-'), '--', '-')) WHERE `alias` = '' OR `alias` IS NULL;

UPDATE `#__moloch_steps` SET `alias` = LOWER(REPLACE(REPLACE(`title`, ' ', '-'), '--', '-')) WHERE `alias` = '' OR `alias` IS NULL;

-- Clean up orphaned records
DELETE FROM `#__moloch_attachments` WHERE `issue_id` NOT IN (SELECT `id` FROM `#__moloch_issues`);

DELETE FROM `#__moloch_comments` WHERE `issue_id` NOT IN (SELECT `id` FROM `#__moloch_issues`);

DELETE FROM `#__moloch_votes` WHERE `issue_id` NOT IN (SELECT `id` FROM `#__moloch_issues`);

DELETE FROM `#__moloch_logs` WHERE `issue_id` NOT IN (SELECT `id` FROM `#__moloch_issues`);

DELETE FROM `#__moloch_notifications` WHERE `issue_id` NOT IN (SELECT `id` FROM `#__moloch_issues`);

DELETE FROM `#__moloch_subscriptions` WHERE `issue_id` NOT IN (SELECT `id` FROM `#__moloch_issues`);

-- Add version info to component
UPDATE `#__extensions` SET `manifest_cache` = JSON_SET(`manifest_cache`, '$.version', '1.0.1') WHERE `element` = 'com_moloch' AND `type` = 'component';