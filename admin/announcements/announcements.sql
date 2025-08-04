-- Create announcements table
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    event_date DATE NOT NULL,
    topic VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    duration_days INT DEFAULT 30,
    expiry_date DATETIME NOT NULL,
    status ENUM('active', 'expired', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_event_date (event_date),
    INDEX idx_expiry_date (expiry_date),
    INDEX idx_created_at (created_at)
);

-- Create a view for announcement statistics
CREATE OR REPLACE VIEW announcement_stats AS
SELECT 
    COUNT(*) as total_announcements,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_announcements,
    COUNT(CASE WHEN status = 'expired' THEN 1 END) as expired_announcements,
    COUNT(CASE WHEN priority = 'high' AND status = 'active' THEN 1 END) as high_priority_active,
    COUNT(CASE WHEN priority = 'normal' AND status = 'active' THEN 1 END) as normal_priority_active,
    COUNT(CASE WHEN priority = 'low' AND status = 'active' THEN 1 END) as low_priority_active,
    COUNT(CASE WHEN event_date >= CURDATE() THEN 1 END) as upcoming_events,
    COUNT(CASE WHEN event_date < CURDATE() THEN 1 END) as past_events
FROM announcements;

-- Insert sample announcements (optional)
INSERT INTO announcements (member_id, event_date, topic, description, priority, duration_days, expiry_date) VALUES
(1, '2025-01-15', 'Community Meeting', 'Monthly community meeting will be held on January 15th at 6:00 PM. All members are encouraged to attend.', 'high', 30, DATE_ADD(NOW(), INTERVAL 30 DAY)),
(1, '2025-01-20', 'Youth Program', 'New youth program starting January 20th. Registration is now open for all interested young members.', 'normal', 60, DATE_ADD(NOW(), INTERVAL 60 DAY)),
(1, '2025-01-25', 'Bible Study', 'Weekly Bible study sessions will resume on January 25th. New participants are welcome.', 'normal', 45, DATE_ADD(NOW(), INTERVAL 45 DAY));

-- Create a stored procedure to clean up expired announcements
DELIMITER //
CREATE PROCEDURE CleanupExpiredAnnouncements()
BEGIN
    UPDATE announcements 
    SET status = 'expired' 
    WHERE expiry_date < NOW() 
    AND status = 'active';
END //
DELIMITER ;

-- Create an event to automatically clean up expired announcements daily
CREATE EVENT IF NOT EXISTS cleanup_announcements_event
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO CALL CleanupExpiredAnnouncements(); 