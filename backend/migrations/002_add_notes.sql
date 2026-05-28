-- Migration: Add Notes table
-- Apply with: docker exec -i mydb mariadb -u root -p${DB_PASSWORD} ${DB_NAME} < backend/migrations/002_add_notes.sql

USE mydatabase;

CREATE TABLE IF NOT EXISTS `Notes` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` MEDIUMTEXT,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (userId) REFERENCES Users(id) ON DELETE CASCADE,
  INDEX idx_user (userId),
  INDEX idx_user_updated (userId, updated_at)
);
