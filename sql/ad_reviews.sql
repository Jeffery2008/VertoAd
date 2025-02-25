-- Create ad_reviews table
CREATE TABLE IF NOT EXISTS `ad_reviews` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `ad_id` BIGINT UNSIGNED NOT NULL,
    `reviewer_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `comments` TEXT,
    `violation_type` VARCHAR(50),
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`ad_id`) REFERENCES `advertisements`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Create an index on ad_id for faster lookups
CREATE INDEX IF NOT EXISTS `idx_ad_reviews_ad_id` ON `ad_reviews` (`ad_id`);

-- Create a violation types table for standardized violation categories
CREATE TABLE IF NOT EXISTS `violation_types` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `description` TEXT,
    `severity` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default violation types
INSERT INTO `violation_types` (`name`, `description`, `severity`) VALUES
('inappropriate_content', 'Content that violates community standards or is inappropriate', 'high'),
('misleading', 'Misleading or false information in advertisement', 'medium'),
('trademark_violation', 'Unauthorized use of trademark or copyrighted material', 'high'),
('format_issue', 'Issues with ad format, sizing, or technical problems', 'low'),
('poor_quality', 'Low quality content, images, or messaging', 'low'),
('other', 'Other violation not covered by standard categories', 'medium');

-- Create review audit log table
CREATE TABLE IF NOT EXISTS `ad_review_logs` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `review_id` BIGINT UNSIGNED NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `old_status` ENUM('pending', 'approved', 'rejected'),
    `new_status` ENUM('pending', 'approved', 'rejected'),
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `comments` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`review_id`) REFERENCES `ad_reviews`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`actor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
); 