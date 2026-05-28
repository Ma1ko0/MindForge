-- Migration: Add Workflow blocks + templates
-- Apply with: docker exec -i mydb mariadb -u root -p${DB_PASSWORD} ${DB_NAME} < backend/migrations/003_add_workflow.sql

USE mydatabase;

CREATE TABLE IF NOT EXISTS `WorkflowBlocks` (
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

CREATE TABLE IF NOT EXISTS `WorkflowTemplates` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` TEXT,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (userId) REFERENCES Users(id) ON DELETE CASCADE,
  INDEX idx_user (userId)
);

CREATE TABLE IF NOT EXISTS `WorkflowTemplateBlocks` (
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
