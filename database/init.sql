CREATE TABLE IF NOT EXISTS `internship_documents` (
  `document_id` int NOT NULL AUTO_INCREMENT,
  `internship_id` int NOT NULL,
  `document_type` enum('weekly_report','final_report','evaluation_form') COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `feedback` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`document_id`),
  KEY `internship_id` (`internship_id`),
  CONSTRAINT `internship_documents_ibfk_1` FOREIGN KEY (`internship_id`) REFERENCES `internship_details` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Make sure lecturers table exists and has correct columns
CREATE TABLE IF NOT EXISTS `lecturers` (
    `lecturer_id` int NOT NULL AUTO_INCREMENT,
    `user_id` int NOT NULL,
    `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
    `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
    `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`lecturer_id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `lecturers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adjust internship_courses table if needed
ALTER TABLE `internship_courses` 
    ADD CONSTRAINT `internship_courses_ibfk_1` 
    FOREIGN KEY (`lecturer_id`) 
    REFERENCES `lecturers` (`lecturer_id`) 
    ON DELETE RESTRICT;
