--
-- Uninstall SQL for Moloch Component
-- Author: Lic. Joel Salazar Ramírez <joel@edugame.digital>
-- Copyright (C) 2025. All rights reserved.
-- License: GNU General Public License version 2 or later
--

-- Drop foreign key constraints first to avoid dependency issues
SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables in reverse dependency order
DROP TABLE IF EXISTS `#__moloch_subscriptions`;
DROP TABLE IF EXISTS `#__moloch_notifications`;
DROP TABLE IF EXISTS `#__moloch_logs`;
DROP TABLE IF EXISTS `#__moloch_votes`;
DROP TABLE IF EXISTS `#__moloch_comments`;
DROP TABLE IF EXISTS `#__moloch_attachments`;
DROP TABLE IF EXISTS `#__moloch_issues`;
DROP TABLE IF EXISTS `#__moloch_steps`;
DROP TABLE IF EXISTS `#__moloch_categories`;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;