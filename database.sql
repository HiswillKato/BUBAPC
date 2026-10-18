-- ============================================================
-- BUBAPC - Database schema
--
-- Structure only: there is no seed data in this file. The installer
-- creates the first administrator, and everything else is typed in
-- through the admin panel.
--
-- Safe to run more than once. It drops the tables and makes them
-- again, so it also ERASES anything already in the website. Take a
-- copy first with mysqldump if the data matters:
--
--   mysqldump -u USER -p bubapc > backup-before-schema-reset.sql
--
-- The file makes the database itself if it is not there yet, so the
-- only command needed is:
--
--   sudo mysql < database/bubapc.sql
--
-- The installer does exactly this for you, so most days you will
-- never run the command by hand.
--
-- Every column here is one the website code already asks for. The
-- SQL in app/functions.php and app/auth.php is the source of truth;
-- if you add a column, add it to the form that fills it in as well.
--
-- Two conventions worth knowing before you edit anything:
--
--   * Tables that hold something the public reads use
--     status ENUM('published','draft') and are filtered on
--     "WHERE status = 'published'".
--   * Tables that hold something you could simply switch off (a
--     programme, a department, a family) use active TINYINT(1)
--     instead, and are filtered on "WHERE active = 1".
--   * The one exception is people.status, which is a TINYINT and
--     NOT a status ENUM, because the code compares it to the number
--     1. See the comment on that table.
--
-- On PICTURES, so that a picture is not added to a table by accident:
-- only three tables have an `image` column. An event has a picture
-- because an event is something you look at, the gallery is the
-- photo album, and a person has one so the church can see who they
-- are. A person with an empty image is shown by their initials, which
-- is what initials() draws inside the brand-mark circle on
-- people.php and leadership.php.
--
-- Devotionals, sermons, announcements, departments and families do
-- NOT have the column, by the decision recorded in
-- database/migrate-events-gallery-images.php. If a picture is ever
-- wanted back on one of those, the column, the form, the list and the
-- public page all have to come back together, because the database
-- and the code are read as a pair.
--
-- A third convention is on the NAMES, and it is a hard rule:
--
--   * Every table's own key is a column called `id`.
--   * A column that points at another table's id is named after the
--     THING it points at, with no suffix: people.family points at
--     families.id, people.department at departments.id,
--     departments.leader at people.id. There is no `_id` anywhere.
--   * A timestamp column is the bare word for when it happened:
--     created, login, attempted, expires, used, seen. There is no
--     `_at` anywhere.
--   * A qualifier is dropped when the column name already says it:
--     setting_key is `name`, setting_value is `value`,
--     ip_address is `ip`, is_read is `seen`.
--
-- The grep that must print nothing after a rename:
--
--   grep -rnE '\b(family_id|department_id|leader_id|created_at|
--   last_login|attempted_at|is_read|ip_address|setting_key|
--   setting_value)\b' --include='*.php' --include='*.sql' .
--
-- Note the one place the rule bites twice. departments and families
-- each point at a leader in people AND keep a typed name for a
-- leader who has no page of their own. The reference is `leader`
-- and the typed name is `typed`, so the two can never be confused.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- The name has to be written out here as well as in app/config.php. If
-- you change one, change the other, or the installer will build a
-- database the website never looks at.
CREATE DATABASE IF NOT EXISTS `bubapc`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `bubapc`;

-- There used to be a second table, `users`, holding every account that
-- could sign in. It has been folded into `people`, so one row is now
-- the whole of a person: where the church lists them AND what they may
-- do on the website. An account is a person row with a `password`; a
-- row with no password is a directory page with no sign-in. Nothing
-- joins the two tables any more because there is nothing to join.

-- ------------------------------------------------------------
-- tokens - one-time links, currently only password resets.
--
-- A request for a reset makes a random string, shows the link and
-- stores nothing but its SHA-256 hash here, so a row that is copied
-- out of the database by somebody else is worthless. reset.php
-- hashes whatever arrives in the address and looks for a match.
--
-- type is what the token may be used for, so a future "confirm my
-- email" token cannot be spent on a password reset.
--
-- used is set to the moment the token was spent, and a token that
-- has been used is refused. expires is when it stops working at
-- all. Both are checked in the same place, token_row() in
-- app/auth.php.
--
-- This table is safe to empty by hand at any time: every row in it
-- is either already used or waiting to expire.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `tokens`;
CREATE TABLE `tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user` INT UNSIGNED NOT NULL COMMENT 'A row in people',
  `type` ENUM('password_reset') NOT NULL DEFAULT 'password_reset',
  `hash` CHAR(64) NOT NULL COMMENT 'SHA-256 of the token, never the token itself',
  `expires` DATETIME NOT NULL COMMENT 'Hours after issue, from TOKEN_RESET_HOURS',
  `used` DATETIME DEFAULT NULL COMMENT 'The moment it was spent, or empty',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`),
  KEY `user` (`user`),
  KEY `expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- logs - who did what, for the admin activity screen.
--
-- One row per thing somebody changed, written by log_action() in
-- app/functions.php. actor is the account that did it and is 0 for
-- a visitor who has never signed in, so the column never lies about
-- being a person.
--
-- action is the short machine name of the event (event_created,
-- user_signed_in, contact_received) and is what the screen groups
-- by. details is FINISHED English text with the detail already in
-- it, built with sprintf() at the call site, because it is printed
-- as it stands and there is no catalog to look a key up in.
--
-- ip is the address the change came from, empty when unknown.
--
-- The table grows on its own. Deleting the oldest rows is safe at
-- any time; nothing reads the log to make a decision.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'A row in people, or 0 for a visitor',
  `action` VARCHAR(60) NOT NULL COMMENT 'The event name, for example event_created',
  `details` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Finished English text, ready to print',
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `actor` (`actor`),
  KEY `action` (`action`),
  KEY `created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- visitors - one row per browser that has reached the site, for
-- the admin visitor screen and the dashboard numbers.
--
-- This is a rough count and nothing more. It exists so the church
-- can see whether anybody is looking at the site, not to follow
-- anybody around: there is no cookie, no fingerprint and no
-- cross-site identifier, and the rows cannot be tied back to a
-- person.
--
-- ip is hashed by visitor_key() in app/functions.php before it is
-- stored, so the address itself is never kept. The same visitor
-- with the same browser and the same day gets the same key, which
-- is why `seen` is the last visit rather than the first.
--
-- seen is the last time this visitor was here, visits counts how
-- many separate days that has been, and `path` is where they were
-- last time, so the screen has something to show.
--
-- Old rows are reaped by visitor_sweep(), which deletes anything
-- older than VISITOR_KEEP_DAYS so the table cannot grow forever.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `visitors`;
CREATE TABLE `visitors` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` CHAR(64) NOT NULL COMMENT 'The hashed address, never the address itself',
  `agent` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'The browser string, for the screen only',
  `path` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'Where the visitor was last seen',
  `referer` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Where they came from, when the browser said',
  `visits` SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'How many separate days seen',
  `seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`),
  KEY `seen` (`seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- login_attempts - used only to slow down repeated wrong passwords.
--
-- One row per try, good or bad. app/auth.php counts the failures for
-- a username OR an IP address over the last LOGIN_LOCK_MINUTES
-- minutes and locks both out at LOGIN_MAX_ATTEMPTS failures.
--
-- This table grows on its own. Old rows can be deleted safely at any
-- time; the throttle only ever looks at the recent window.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(191) NOT NULL DEFAULT '' COMMENT 'What was typed, even if no such user exists',
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `success` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 signed in, 0 wrong password',
  `attempted` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `attempted` (`attempted`),
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- settings - small key and value rows the admin can change from the
-- browser, so that no file ever has to be edited to close the site.
--
-- id is the ordinary key every table has. name is the setting's own
-- name and is what setting_get() looks for, so the two are not the
-- same column and renaming a setting in the code is one edit.
--
-- Only two keys exist today:
--   maintenance_mode    '1' closes the site to everyone but admins
--   maintenance_message the sentence shown on the "back soon" page
--
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `value` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- programs - the weekly programme, one row per meeting.
--
-- day_of_week follows PHP's date('w'): 0 is Sunday, 6 is Saturday.
-- The helper day_name() in app/functions.php turns that number into
-- "Sunday", so the names only ever live in one place.
--
-- title is not read by any public page. It is here so the admin form
-- can label the row in the list instead of showing a bare time.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `programs`;
CREATE TABLE `programs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(160) NOT NULL DEFAULT '',
  `day_of_week` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 Sunday .. 6 Saturday',
  `start_time` TIME NOT NULL,
  `end_time` TIME DEFAULT NULL,
  `times` VARCHAR(190) DEFAULT NULL COMMENT 'One start-end per team, separated by a semicolon. Empty when the whole meeting runs together',
  `venue` VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'One place per team, separated by a semicolon. Empty when no venue is fixed',
  `description` TEXT COMMENT 'One line saying what the meeting is for',
  `sort_order` SMALLINT NOT NULL DEFAULT 0 COMMENT 'Lower numbers are shown first inside one day',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `day_and_time` (`day_of_week`,`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The church's official week is not seeded here: it lives in
-- database/migrate-programs-official.php, which writes the same twelve
-- meetings into any copy of this table without making duplicates. Run that
-- script once after loading this file and the program page is correct.
-- One row holds one meeting on one day, so Morning Glory appears on Monday,
-- Wednesday and Friday as three rows. A meeting run in two teams is still
-- one row: the teams are named in the venue, one place each, and when those
-- teams meet at different hours those times go in `times`, both separated by
-- a semicolon. `times` is empty for every meeting that runs start to end.

-- ------------------------------------------------------------
-- events - anything with a date and a place: retreats, services,
-- conventions. slug is what makes the short address work, so it is
-- produced by unique_slug() whenever a row is created or renamed.
--
-- end_date and end_time are for the two-day and multi-slot cases.
-- Leave them empty for a normal single afternoon event.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL,
  `theme` VARCHAR(200) NOT NULL DEFAULT '',
  `description` TEXT,
  `venue` VARCHAR(200) NOT NULL DEFAULT '',
  `start_date` DATE NOT NULL,
  `start_time` TIME DEFAULT NULL,
  `end_date` DATE DEFAULT NULL,
  `end_time` TIME DEFAULT NULL,
  `image` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Relative to uploads/, for example events/abc.jpg',
  `status` ENUM('published','draft') NOT NULL DEFAULT 'published',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `published_and_date` (`status`,`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- devotionals - one short message per day.
--
-- further_study is a plain list, one reference per line, rendered
-- with line_list() rather than stored as separate rows.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `devotionals`;
CREATE TABLE `devotionals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL,
  `message` LONGTEXT,
  `scripture_reference` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'For example "Psalm 34:1"',
  `scripture_text` TEXT,
  `further_study` TEXT,
  `devotional_date` DATE NOT NULL COMMENT 'The day this devotional is FOR, not the day it was typed',
  `status` ENUM('published','draft') NOT NULL DEFAULT 'published',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `published_and_date` (`status`,`devotional_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- sermons - a written summary of a past message.
--
-- video_url takes any YouTube address. youtube_id() in
-- app/functions.php pulls the eleven character id out of the long
-- link, the short youtu.be link or an /embed/ link, so paste
-- whichever one you copied from the address bar.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `sermons`;
CREATE TABLE `sermons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL,
  `preacher` VARCHAR(160) NOT NULL DEFAULT '',
  `description` TEXT,
  `scripture` VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'For example "Mark 4:1-20"',
  `sermon_date` DATE NOT NULL,
  `video_url` VARCHAR(500) NOT NULL DEFAULT '',
  `status` ENUM('published','draft') NOT NULL DEFAULT 'published',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `published_and_date` (`status`,`sermon_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- announcements - short notices, newest first.
--
-- The public announcements page links to each one with an anchor
-- (#announcement-<id>) rather than a page of its own, so slug is
-- kept for search results and future use.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL,
  `content` TEXT,
  `publish_date` DATE NOT NULL COMMENT 'The day the notice appears on the site',
  `status` ENUM('published','draft') NOT NULL DEFAULT 'published',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `published_and_date` (`status`,`publish_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- departments and families
--
-- These two are the same shape and are written side by side in
-- app/functions.php, so keep them in step when you change either.
--
-- There is no status ENUM here: a department or family is either
-- listed (active = 1) or hidden (active = 0).
--
-- leader points at the person in the people table who leads
-- this group, so the public page can link straight to them and the
-- name follows them if it is ever changed. The typed text column
-- is still here as a safety net: not every leader wants a page of
-- their own, so a name may be typed in instead. When leader is
-- 0 the text is what the website shows.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `slug` VARCHAR(160) NOT NULL,
  `leader` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'A row in people. 0 means use the typed name below',
  `typed` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'Typed by hand, for a leader with no page of their own',
  `description` TEXT,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `active` (`active`),
  KEY `leader` (`leader`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `families`;
CREATE TABLE `families` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `slug` VARCHAR(160) NOT NULL,
  `leader` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'A row in people. 0 means use the typed name below',
  `typed` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'Typed by hand, for a leader with no page of their own',
  `description` TEXT,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `active` (`active`),
  KEY `leader` (`leader`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- people - one row for every person, whether the church lists them
-- on the website, signs them in to the panel, or both.
--
-- This table used to be called "leaders" and held only the people
-- who hold an office. It grew into one table for the five listed
-- groups, and it is now also the accounts table: the old `users`
-- table was folded in here. One row is the whole of a person - the
-- directory page AND the sign-in - so an account is a row with a
-- password and a row with no password is a page with no sign-in.
-- Nothing joins the two tables any more because there is nothing to
-- join. See people_categories() in app/functions.php for the list of
-- groups, which is the only place the names live.
--
-- category decides the group, and so which page a person appears on:
--
--   pastor, church_admin, elder, department_leader, family_leader
--       go on the leadership page
--   student
--       goes in the students list
--   alumnus
--       goes in the alumni list
--   member
--       signed themselves up on register.php; no directory page
--
-- A person has exactly one category, on purpose. It is one dropdown
-- in the admin form and a beginner maintaining the site cannot get it
-- wrong. If somebody is both a pastor and an alumnus, choose the
-- group they should be listed under and put the year in graduated_year.
--
-- position is free text for the exact title, so "Bishop", "Head
-- Teacher" or "House Coordinator" can all be shown as typed. It is
-- not used to work out the group - that is what category is for.
--
-- family and department say where a student belongs. They are
-- 0 when we do not know or the person is not a student. There is no
-- real FOREIGN KEY on purpose: deleting a person must not silently
-- take a family or a department with it, and the admin screens set
-- the column back to 0 when that happens.
--
-- graduated_year is only extra detail - "Class of 2019". It does not
-- decide the group; category does.
--
-- CAREFUL: status here is a TINYINT (1 = shown, 0 = hidden) and NOT
-- a 'published' / 'draft' ENUM like the tables above. The public
-- pages and the site search all run "WHERE status = 1". If you change
-- this column to the ENUM, those queries stop finding anything and
-- every page comes back empty. A member account has status 0
-- because it belongs to no directory page.
--
-- The sign-in columns below are the old users table, folded in:
--
-- username   the sign-in name, which is the email address. NULL when
--            the person has no sign-in, and unique only among the
--            rows that have one, so a directory person and an account
--            may share an email without a clash. Sign-in looks up
--            username = email so changing an email here and in the
--            account happen in the same UPDATE.
-- password   a password_hash() value, or NULL for no sign-in
-- role       the website job, not the church job. Roles in staff_roles()
--            may access the admin panel as appropriate. NULL when there is no sign-in.
-- standing   what a self-registered person chose; a 'leader' waits
--            for an administrator to switch the account on
-- active     1 may sign in, 0 switched off. The login form checks
--            this, so a member waiting for approval simply cannot
--            sign in yet.
-- login      the last successful sign-in, or empty
--
-- display_order decides who is listed first inside a group. Pastors
-- near the top looks right, so give them a low number.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `people`;
CREATE TABLE `people` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `slug` VARCHAR(160) NOT NULL COMMENT 'Makes /people/bro-daniel-waire work',
  `category` ENUM('pastor','church_admin','elder','minister','department_leader','family_leader','student','alumnus','member') NOT NULL DEFAULT 'student',
  `position` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'The exact title, for example "Bishop"',
  `phone` VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'Left empty when the church does not publish it',
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `username` VARCHAR(190) DEFAULT NULL COMMENT 'The sign-in name, which is the email. NULL when there is no sign-in',
  `password` VARCHAR(255) DEFAULT NULL COMMENT 'A password_hash() value, or NULL for no sign-in',
  `role` ENUM('admin','church_admin','pastor','elder','minister','editor','leader','member') DEFAULT NULL COMMENT 'The website job; NULL when there is no sign-in',
  `standing` ENUM('member','alumnus','leader') NOT NULL DEFAULT 'member' COMMENT 'What a self-registered person chose; a leader waits for approval',
  `active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 may sign in, 0 is switched off',
  `login` DATETIME DEFAULT NULL COMMENT 'The last successful sign-in, or empty',
  `family` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'A row in families, or 0',
  `department` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'A row in departments, or 0',
  `joined_year` SMALLINT DEFAULT NULL COMMENT 'The year the person started, for example 2019',
  `graduated_year` SMALLINT DEFAULT NULL COMMENT 'Extra detail only. The category decides the group',
  `bio` TEXT,
  `testimony` TEXT COMMENT 'Only shown on the alumni pages',
  `image` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Relative to uploads/. Empty means show their initials',
  `display_order` SMALLINT NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 is shown on the website, 0 is hidden',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `username` (`username`),
  KEY `status` (`status`),
  KEY `category` (`category`,`display_order`),
  KEY `active` (`active`),
  KEY `family` (`family`),
  KEY `department` (`department`),
  KEY `display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- gallery - the photo album on the homepage.
--
-- The album is a simple ordered list. A photo with an empty title
-- is still shown, so a straight upload needs only the image.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `gallery`;
CREATE TABLE `gallery` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL DEFAULT '',
  `caption` VARCHAR(300) NOT NULL DEFAULT '',
  `image` VARCHAR(255) NOT NULL COMMENT 'Relative to uploads/, for example gallery/abc.jpg',
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 is shown, 0 is hidden',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- messages - what arrives from the contact form.
--
-- Every submission is saved here, which is the record the admin reads in
-- admin/messages. A copy also goes to the mailbox set by MAIL_ADMIN in
-- app/config.php, so the church is told without opening the panel; the
-- saving happens first and never depends on the email arriving.
--
-- seen is 0 for anything unread so the admin list can find the new ones
-- without opening each row.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL DEFAULT '',
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `phone` VARCHAR(40) NOT NULL DEFAULT '',
  `subject` VARCHAR(200) NOT NULL DEFAULT '',
  `message` TEXT,
  `seen` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = unread, 1 = marked read',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `seen` (`seen`),
  KEY `created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- subscribers - the addresses on the daily devotional list.
--
-- The form posts to devotional-subscribe.php, which returns JSON
-- because assets/js/main.js sends it with fetch() rather than
-- letting the page jump.
--
-- There is no confirmation email, so an address is only ever added
-- after the visitor has proved they can receive mail at it. The
-- unique key on email is what stops the same address filling the
-- list twice.
--
-- status is 1 for a live address and 0 for one that bounced or was
-- unsubscribed. Never delete the row, switch it off instead, so the
-- address cannot be added a second time by mistake.
--
-- People who tick the daily devotional box while registering on
-- register.php are added here too, from the same place as the
-- footer form, so a church member is only ever on this list once.
--
-- Every morning, bin/send-devotional.php emails the devotional
-- dated for that day to the addresses where status = 1.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `subscribers`;
CREATE TABLE `subscribers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 sends mail, 0 is switched off',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `settings` (`name`, `value`) VALUES
  ('maintenance_mode', '0'),
  ('maintenance_message', 'We are doing a little work on the website. Please come back shortly.');

SET FOREIGN_KEY_CHECKS = 1;
