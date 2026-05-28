-- Migration: Add Projects + nullable projectId FK on TodoLists and Notes
-- Apply with: docker exec -i mydb mariadb -u root -p${DB_PASSWORD} ${DB_NAME} < backend/migrations/004_add_projects.sql

USE mydatabase;

CREATE TABLE IF NOT EXISTS `Projects` (
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

ALTER TABLE TodoLists
  ADD COLUMN projectId int(11) NULL AFTER userId,
  ADD CONSTRAINT fk_todolist_project FOREIGN KEY (projectId) REFERENCES Projects(id) ON DELETE SET NULL,
  ADD INDEX idx_project (projectId);

ALTER TABLE Notes
  ADD COLUMN projectId int(11) NULL AFTER userId,
  ADD CONSTRAINT fk_note_project FOREIGN KEY (projectId) REFERENCES Projects(id) ON DELETE SET NULL,
  ADD INDEX idx_project (projectId);
