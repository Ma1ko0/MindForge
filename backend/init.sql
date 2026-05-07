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

CREATE TABLE `TodoItems` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `content` varchar(255),
  `isChecked` boolean NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
)

CREATE TABLE `TodoItemToUser` (
  `userId` int(11) NOT NULL PRIMARY KEY
  `todoItemId` int(11) NOT NULL PRIMARY KEY
)

-- Insert some sample data
INSERT INTO Users (username, email, password_hash) VALUES ('TestUser', 'test@mail.com', "somehash1");
