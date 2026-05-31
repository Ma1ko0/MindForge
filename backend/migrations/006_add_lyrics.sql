-- Migration: Add Lyrics table (songs with sections + rhyme analysis)
-- Apply with: docker exec -i mydb mariadb -u root -p${DB_PASSWORD} ${DB_NAME} < backend/migrations/006_add_lyrics.sql

USE mydatabase;

CREATE TABLE IF NOT EXISTS `Lyrics` (
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
