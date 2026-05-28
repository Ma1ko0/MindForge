CREATE DATABASE IF NOT EXISTS mydatabase;

USE mydatabase;

CREATE TABLE `Users` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
);

CREATE TABLE `Projects` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` TEXT,
  `color` varchar(20) DEFAULT 'blue',
  `icon` varchar(50) DEFAULT 'folder',
  `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (userId) REFERENCES Users(id) ON DELETE CASCADE,
  INDEX idx_user (userId)
);

CREATE TABLE `TodoLists` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `userId` int(11) NOT NULL,
  `projectId` int(11) NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (userId) REFERENCES Users(id) ON DELETE CASCADE,
  FOREIGN KEY (projectId) REFERENCES Projects(id) ON DELETE SET NULL,
  INDEX idx_project (projectId)
);

CREATE TABLE `TodoItems` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `content` varchar(255),
  `due_date` DATETIME NULL,
  `isChecked` TINYINT(1) NOT NULL,
  `listId` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (listId) REFERENCES TodoLists(id) ON DELETE CASCADE,
  INDEX idx_due_date (due_date)
);

CREATE TABLE `Notes` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `projectId` int(11) NULL,
  `title` varchar(200) NOT NULL,
  `content` MEDIUMTEXT,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (userId) REFERENCES Users(id) ON DELETE CASCADE,
  FOREIGN KEY (projectId) REFERENCES Projects(id) ON DELETE SET NULL,
  INDEX idx_user (userId),
  INDEX idx_user_updated (userId, updated_at),
  INDEX idx_project (projectId)
);

CREATE TABLE `WorkflowBlocks` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `blockDate` DATE NOT NULL,
  `startTime` TIME NOT NULL,
  `endTime` TIME NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` TEXT,
  `color` varchar(20) DEFAULT 'blue',
  `isDone` TINYINT(1) NOT NULL DEFAULT 0,
  `linkedTodoItemId` int(11) NULL,
  `linkedNoteId` int(11) NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (userId) REFERENCES Users(id) ON DELETE CASCADE,
  FOREIGN KEY (linkedTodoItemId) REFERENCES TodoItems(id) ON DELETE SET NULL,
  FOREIGN KEY (linkedNoteId) REFERENCES Notes(id) ON DELETE SET NULL,
  INDEX idx_user_date (userId, blockDate)
);

CREATE TABLE `WorkflowTemplates` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` TEXT,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (userId) REFERENCES Users(id) ON DELETE CASCADE,
  INDEX idx_user (userId)
);

CREATE TABLE `Habits` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` TEXT,
  `frequency` varchar(20) NOT NULL DEFAULT 'daily',
  `weekdays` varchar(20),
  `color` varchar(20) DEFAULT 'blue',
  `icon` varchar(50) DEFAULT 'repeat',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (userId) REFERENCES Users(id) ON DELETE CASCADE,
  INDEX idx_user (userId)
);

CREATE TABLE `HabitCompletions` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `habitId` int(11) NOT NULL,
  `completionDate` DATE NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  FOREIGN KEY (habitId) REFERENCES Habits(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_habit_date (habitId, completionDate),
  INDEX idx_date (completionDate)
);

CREATE TABLE `WorkflowTemplateBlocks` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `templateId` int(11) NOT NULL,
  `startTime` TIME NOT NULL,
  `endTime` TIME NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` TEXT,
  `color` varchar(20) DEFAULT 'blue',
  `sortOrder` int(11) NOT NULL DEFAULT 0,
  FOREIGN KEY (templateId) REFERENCES WorkflowTemplates(id) ON DELETE CASCADE,
  INDEX idx_template (templateId, sortOrder)
);

-- Insert some sample data
INSERT INTO Users (username, email, password_hash) VALUES ('TestUser', 'test@mail.com', "somehash1");
