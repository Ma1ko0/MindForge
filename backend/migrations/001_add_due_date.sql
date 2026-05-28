-- Migration: Add due_date to TodoItems
-- Apply with: docker exec -i mydb mariadb -u root -p${DB_PASSWORD} ${DB_NAME} < backend/migrations/001_add_due_date.sql

USE mydatabase;

ALTER TABLE TodoItems
  ADD COLUMN due_date DATETIME NULL AFTER content,
  ADD INDEX idx_due_date (due_date);
