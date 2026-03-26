-- Create ratings table for user-to-user ratings
CREATE TABLE IF NOT EXISTS ratings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rater_id INT UNSIGNED NOT NULL,
    ratee_id INT UNSIGNED NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rater_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ratee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rater_ratee (rater_id, ratee_id),
    KEY (ratee_id),
    KEY (created_at)
);
