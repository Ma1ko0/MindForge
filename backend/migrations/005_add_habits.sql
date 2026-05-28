-- Migration: Add Habits + HabitCompletions
-- Apply with: docker exec -i mydb mariadb -u root -p${DB_PASSWORD} ${DB_NAME} < backend/migrations/005_add_habits.sql

USE mydatabase;

CREATE TABLE IF NOT EXISTS `Habits` (
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

CREATE TABLE IF NOT EXISTS `HabitCompletions` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `habitId` int(11) NOT NULL,
  `completionDate` DATE NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  FOREIGN KEY (habitId) REFERENCES Habits(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_habit_date (habitId, completionDate),
  INDEX idx_date (completionDate)
);
