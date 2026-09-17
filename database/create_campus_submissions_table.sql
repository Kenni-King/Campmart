-- Table to store campus submissions from users
CREATE TABLE IF NOT EXISTS campus_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    university_id INT NOT NULL,
    submitter_name VARCHAR(255) NOT NULL,
    submitter_email VARCHAR(255) NOT NULL,
    additional_info TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    submitted_at DATETIME NOT NULL,
    FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add website column to universities table if it doesn't exist
ALTER TABLE universities 
ADD COLUMN IF NOT EXISTS website VARCHAR(255) NULL AFTER logo_url;
