-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 05, 2025 at 09:53 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gang_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CleanupAbandonedSessions` ()   BEGIN
    UPDATE recording_sessions 
    SET status = 'abandoned' 
    WHERE status = 'active' 
    AND last_activity_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
    
    SELECT ROW_COUNT() as sessions_cleaned;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetBookCompletionStatus` (IN `member_id` INT, IN `book_name` VARCHAR(50))   BEGIN
    SELECT 
        bb.name as book,
        bb.chapters as total_chapters,
        bb.testament,
        COUNT(bp.chapter) as chapters_read,
        (bb.chapters - COUNT(bp.chapter)) as chapters_remaining,
        ROUND((COUNT(bp.chapter) / bb.chapters) * 100, 2) as completion_percent,
        CASE 
            WHEN COUNT(bp.chapter) = bb.chapters THEN 'completed'
            WHEN COUNT(bp.chapter) > 0 THEN 'in_progress'
            ELSE 'not_started'
        END as status
    FROM bible_books bb
    LEFT JOIN bible_progress bp ON bb.name = bp.book AND bp.member_id = member_id
    WHERE bb.name = book_name
    GROUP BY bb.name, bb.chapters, bb.testament;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetMemberProgress` (IN `member_id` INT)   BEGIN
    SELECT 
        COUNT(*) as total_read,
        COUNT(CASE WHEN bb.testament = 'OT' THEN 1 END) as ot_read,
        COUNT(CASE WHEN bb.testament = 'NT' THEN 1 END) as nt_read,
        929 as ot_total,
        260 as nt_total,
        1189 as overall_total,
        ROUND((COUNT(*) / 1189) * 100, 2) as overall_percent,
        ROUND((COUNT(CASE WHEN bb.testament = 'OT' THEN 1 END) / 929) * 100, 2) as ot_percent,
        ROUND((COUNT(CASE WHEN bb.testament = 'NT' THEN 1 END) / 260) * 100, 2) as nt_percent
    FROM bible_progress bp
    JOIN bible_books bb ON bp.book = bb.name
    WHERE bp.member_id = member_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetMemberReadingStreak` (IN `member_id` INT)   BEGIN
    SELECT 
        COUNT(DISTINCT DATE(read_at)) as total_reading_days,
        MAX(DATE(read_at)) as last_read_date,
        MIN(DATE(read_at)) as first_read_date,
        DATEDIFF(CURDATE(), MAX(DATE(read_at))) as days_since_last_read
    FROM bible_progress 
    WHERE member_id = member_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetMemberSermonSummary` (IN `member_id` INT)   BEGIN
    SELECT 
        COUNT(*) as total_sermons,
        COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed_sermons,
        COUNT(CASE WHEN completed_at IS NULL THEN 1 END) as incomplete_sermons,
        SUM(total_duration_seconds) as total_duration,
        SUM(total_size_bytes) as total_size,
        AVG(total_duration_seconds) as avg_duration,
        MAX(created_at) as last_sermon_date
    FROM sermons 
    WHERE member_id = member_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetSermonStats` (IN `sermon_id` INT)   BEGIN
    SELECT 
        s.*,
        COUNT(DISTINCT sc.id) as chunk_count,
        COUNT(DISTINCT ss.id) as slide_count,
        SUM(DISTINCT sc.file_size) as total_audio_size,
        SUM(DISTINCT ss.file_size) as total_slide_size,
        COUNT(DISTINCT dl.id) as download_count
    FROM sermons s
    LEFT JOIN sermon_chunks sc ON s.id = sc.sermon_id
    LEFT JOIN sermon_slides ss ON s.id = ss.sermon_id
    LEFT JOIN download_logs dl ON s.id = dl.sermon_id
    WHERE s.id = sermon_id
    GROUP BY s.id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `MarkChapterRead` (IN `p_member_id` INT, IN `p_book` VARCHAR(50), IN `p_chapter` INT)   BEGIN
    DECLARE chapter_exists INT DEFAULT 0;
    DECLARE book_exists INT DEFAULT 0;
    
    -- Check if book exists
    SELECT COUNT(*) INTO book_exists FROM bible_books WHERE name = p_book;
    
    IF book_exists = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Book does not exist';
    END IF;
    
    -- Check if chapter number is valid for the book
    SELECT COUNT(*) INTO chapter_exists 
    FROM bible_books 
    WHERE name = p_book AND p_chapter <= chapters AND p_chapter > 0;
    
    IF chapter_exists = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid chapter number for this book';
    END IF;
    
    -- Insert the progress (will be ignored if already exists due to UNIQUE constraint)
    INSERT IGNORE INTO bible_progress (member_id, book, chapter) 
    VALUES (p_member_id, p_book, p_chapter);
    
    SELECT ROW_COUNT() as rows_affected;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `RecalculateSermonTotals` (IN `sermon_id` INT)   BEGIN
    DECLARE chunk_count INT DEFAULT 0;
    DECLARE chunk_size BIGINT DEFAULT 0;
    DECLARE slide_size BIGINT DEFAULT 0;
    DECLARE total_duration INT DEFAULT 0;
    
    -- Get chunk statistics
    SELECT 
        COUNT(*), 
        COALESCE(SUM(file_size), 0),
        COALESCE(SUM(duration_seconds), 0)
    INTO chunk_count, chunk_size, total_duration
    FROM sermon_chunks 
    WHERE sermon_id = sermon_id;
    
    -- Get slide size
    SELECT COALESCE(SUM(file_size), 0)
    INTO slide_size
    FROM sermon_slides 
    WHERE sermon_id = sermon_id;
    
    -- Update sermon record
    UPDATE sermons 
    SET 
        total_chunks = chunk_count,
        total_duration_seconds = total_duration,
        total_size_bytes = chunk_size + slide_size,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = sermon_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateSermonTotalsAfterChunk` (IN `sermon_id` INT)   BEGIN
    UPDATE sermons s
    SET 
        total_chunks = (SELECT COUNT(*) FROM sermon_chunks WHERE sermon_id = sermon_id),
        total_size_bytes = (
            SELECT COALESCE(SUM(file_size), 0) 
            FROM sermon_chunks 
            WHERE sermon_id = sermon_id
        ) + (
            SELECT COALESCE(SUM(file_size), 0) 
            FROM sermon_slides 
            WHERE sermon_id = sermon_id
        ),
        updated_at = CURRENT_TIMESTAMP
    WHERE s.id = sermon_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateSermonTotalsAfterSlide` (IN `sermon_id` INT)   BEGIN
    UPDATE sermons s
    SET 
        total_size_bytes = (
            SELECT COALESCE(SUM(file_size), 0) 
            FROM sermon_chunks 
            WHERE sermon_id = sermon_id
        ) + (
            SELECT COALESCE(SUM(file_size), 0) 
            FROM sermon_slides 
            WHERE sermon_id = sermon_id
        ),
        updated_at = CURRENT_TIMESTAMP
    WHERE s.id = sermon_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `event_date` date NOT NULL,
  `topic` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `duration_days` int(11) NOT NULL DEFAULT 30,
  `expiry_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','expired') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `member_id`, `event_date`, `topic`, `description`, `priority`, `duration_days`, `expiry_date`, `created_at`, `status`) VALUES
(1, 1, '2025-08-06', 'Day 8/10', 'its day 8/10 of the FF', 'high', 7, '2025-08-12 21:41:17', '2025-08-05 19:41:17', 'active');

-- --------------------------------------------------------

--
-- Stand-in structure for view `announcement_stats`
-- (See below for the actual view)
--
CREATE TABLE `announcement_stats` (
`total_announcements` bigint(21)
,`active_announcements` bigint(21)
,`expired_announcements` bigint(21)
,`high_priority_active` bigint(21)
,`normal_priority_active` bigint(21)
,`low_priority_active` bigint(21)
,`upcoming_events` bigint(21)
,`past_events` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `bible_books`
--

CREATE TABLE `bible_books` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `chapters` int(11) NOT NULL,
  `testament` enum('OT','NT') NOT NULL,
  `book_order` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bible_books`
--

INSERT INTO `bible_books` (`id`, `name`, `chapters`, `testament`, `book_order`, `created_at`) VALUES
(1, 'Genesis', 50, 'OT', 1, '2025-08-05 19:19:50'),
(2, 'Exodus', 40, 'OT', 2, '2025-08-05 19:19:50'),
(3, 'Leviticus', 27, 'OT', 3, '2025-08-05 19:19:50'),
(4, 'Numbers', 36, 'OT', 4, '2025-08-05 19:19:50'),
(5, 'Deuteronomy', 34, 'OT', 5, '2025-08-05 19:19:50'),
(6, 'Joshua', 24, 'OT', 6, '2025-08-05 19:19:50'),
(7, 'Judges', 21, 'OT', 7, '2025-08-05 19:19:50'),
(8, 'Ruth', 4, 'OT', 8, '2025-08-05 19:19:50'),
(9, '1 Samuel', 31, 'OT', 9, '2025-08-05 19:19:50'),
(10, '2 Samuel', 24, 'OT', 10, '2025-08-05 19:19:50'),
(11, '1 Kings', 22, 'OT', 11, '2025-08-05 19:19:50'),
(12, '2 Kings', 25, 'OT', 12, '2025-08-05 19:19:50'),
(13, '1 Chronicles', 29, 'OT', 13, '2025-08-05 19:19:50'),
(14, '2 Chronicles', 36, 'OT', 14, '2025-08-05 19:19:50'),
(15, 'Ezra', 10, 'OT', 15, '2025-08-05 19:19:50'),
(16, 'Nehemiah', 13, 'OT', 16, '2025-08-05 19:19:50'),
(17, 'Esther', 10, 'OT', 17, '2025-08-05 19:19:50'),
(18, 'Job', 42, 'OT', 18, '2025-08-05 19:19:50'),
(19, 'Psalms', 150, 'OT', 19, '2025-08-05 19:19:50'),
(20, 'Proverbs', 31, 'OT', 20, '2025-08-05 19:19:50'),
(21, 'Ecclesiastes', 12, 'OT', 21, '2025-08-05 19:19:50'),
(22, 'Song of Solomon', 8, 'OT', 22, '2025-08-05 19:19:50'),
(23, 'Isaiah', 66, 'OT', 23, '2025-08-05 19:19:50'),
(24, 'Jeremiah', 52, 'OT', 24, '2025-08-05 19:19:50'),
(25, 'Lamentations', 5, 'OT', 25, '2025-08-05 19:19:50'),
(26, 'Ezekiel', 48, 'OT', 26, '2025-08-05 19:19:50'),
(27, 'Daniel', 12, 'OT', 27, '2025-08-05 19:19:50'),
(28, 'Hosea', 14, 'OT', 28, '2025-08-05 19:19:50'),
(29, 'Joel', 3, 'OT', 29, '2025-08-05 19:19:50'),
(30, 'Amos', 9, 'OT', 30, '2025-08-05 19:19:50'),
(31, 'Obadiah', 1, 'OT', 31, '2025-08-05 19:19:50'),
(32, 'Jonah', 4, 'OT', 32, '2025-08-05 19:19:50'),
(33, 'Micah', 7, 'OT', 33, '2025-08-05 19:19:50'),
(34, 'Nahum', 3, 'OT', 34, '2025-08-05 19:19:50'),
(35, 'Habakkuk', 3, 'OT', 35, '2025-08-05 19:19:50'),
(36, 'Zephaniah', 3, 'OT', 36, '2025-08-05 19:19:50'),
(37, 'Haggai', 2, 'OT', 37, '2025-08-05 19:19:50'),
(38, 'Zechariah', 14, 'OT', 38, '2025-08-05 19:19:50'),
(39, 'Malachi', 4, 'OT', 39, '2025-08-05 19:19:50'),
(40, 'Matthew', 28, 'NT', 40, '2025-08-05 19:19:50'),
(41, 'Mark', 16, 'NT', 41, '2025-08-05 19:19:50'),
(42, 'Luke', 24, 'NT', 42, '2025-08-05 19:19:50'),
(43, 'John', 21, 'NT', 43, '2025-08-05 19:19:50'),
(44, 'Acts', 28, 'NT', 44, '2025-08-05 19:19:50'),
(45, 'Romans', 16, 'NT', 45, '2025-08-05 19:19:50'),
(46, '1 Corinthians', 16, 'NT', 46, '2025-08-05 19:19:50'),
(47, '2 Corinthians', 13, 'NT', 47, '2025-08-05 19:19:50'),
(48, 'Galatians', 6, 'NT', 48, '2025-08-05 19:19:50'),
(49, 'Ephesians', 6, 'NT', 49, '2025-08-05 19:19:50'),
(50, 'Philippians', 4, 'NT', 50, '2025-08-05 19:19:50'),
(51, 'Colossians', 4, 'NT', 51, '2025-08-05 19:19:50'),
(52, '1 Thessalonians', 5, 'NT', 52, '2025-08-05 19:19:50'),
(53, '2 Thessalonians', 3, 'NT', 53, '2025-08-05 19:19:50'),
(54, '1 Timothy', 6, 'NT', 54, '2025-08-05 19:19:50'),
(55, '2 Timothy', 4, 'NT', 55, '2025-08-05 19:19:50'),
(56, 'Titus', 3, 'NT', 56, '2025-08-05 19:19:50'),
(57, 'Philemon', 1, 'NT', 57, '2025-08-05 19:19:50'),
(58, 'Hebrews', 13, 'NT', 58, '2025-08-05 19:19:50'),
(59, 'James', 5, 'NT', 59, '2025-08-05 19:19:50'),
(60, '1 Peter', 5, 'NT', 60, '2025-08-05 19:19:50'),
(61, '2 Peter', 3, 'NT', 61, '2025-08-05 19:19:50'),
(62, '1 John', 5, 'NT', 62, '2025-08-05 19:19:50'),
(63, '2 John', 1, 'NT', 63, '2025-08-05 19:19:50'),
(64, '3 John', 1, 'NT', 64, '2025-08-05 19:19:50'),
(65, 'Jude', 1, 'NT', 65, '2025-08-05 19:19:50'),
(66, 'Revelation', 22, 'NT', 66, '2025-08-05 19:19:50');

-- --------------------------------------------------------

--
-- Table structure for table `bible_notes`
--

CREATE TABLE `bible_notes` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `chapter_number` int(11) NOT NULL,
  `verse_number` int(11) DEFAULT NULL,
  `note_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bible_progress`
--

CREATE TABLE `bible_progress` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `book` varchar(50) NOT NULL,
  `chapter` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bible_progress`
--

INSERT INTO `bible_progress` (`id`, `member_id`, `book`, `chapter`, `read_at`, `notes`) VALUES
(1, 1, 'Genesis', 1, '2025-08-05 19:20:11', NULL),
(2, 1, 'Jude', 1, '2025-08-05 19:20:37', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_reading_stats`
-- (See below for the actual view)
--
CREATE TABLE `daily_reading_stats` (
`read_date` date
,`active_readers` bigint(21)
,`total_chapters_read` bigint(21)
,`ot_chapters_read` bigint(21)
,`nt_chapters_read` bigint(21)
,`unique_books_read` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `discussions`
--

CREATE TABLE `discussions` (
  `id` int(11) NOT NULL,
  `topic` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','closed','archived') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `discussions`
--

INSERT INTO `discussions` (`id`, `topic`, `created_by`, `created_at`, `updated_at`, `status`) VALUES
(1, 'Baptism', 1, '2025-08-05 19:35:42', '2025-08-05 19:35:42', 'active');

-- --------------------------------------------------------

--
-- Stand-in structure for view `discussion_list`
-- (See below for the actual view)
--
CREATE TABLE `discussion_list` (
`id` int(11)
,`topic` varchar(255)
,`created_by` int(11)
,`created_at` timestamp
,`updated_at` timestamp
,`status` enum('active','closed','archived')
,`first_name` varchar(255)
,`second_name` varchar(255)
,`third_name` varchar(255)
,`picture` varchar(255)
,`username` varchar(255)
,`role_id` int(11)
,`message_count` bigint(21)
,`last_message_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `download_logs`
--

CREATE TABLE `download_logs` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `sermon_id` int(11) NOT NULL,
  `download_type` enum('audio','slides','complete') NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `download_ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `downloaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `download_stats_view`
-- (See below for the actual view)
--
CREATE TABLE `download_stats_view` (
`sermon_id` int(11)
,`title` varchar(500)
,`speaker` varchar(200)
,`folder_name` varchar(255)
,`total_downloads` bigint(21)
,`audio_downloads` bigint(21)
,`slides_downloads` bigint(21)
,`complete_downloads` bigint(21)
,`total_downloaded_bytes` decimal(41,0)
,`last_download_at` timestamp
,`unique_downloaders` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `enquiries`
--

CREATE TABLE `enquiries` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','resolved') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `second_name` varchar(255) NOT NULL,
  `third_name` varchar(255) DEFAULT NULL,
  `gender` varchar(10) NOT NULL,
  `dob` date NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `gift` varchar(255) DEFAULT NULL,
  `year_joined` int(11) NOT NULL,
  `address` text NOT NULL,
  `occupation` varchar(255) DEFAULT NULL,
  `picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `first_name`, `second_name`, `third_name`, `gender`, `dob`, `phone`, `email`, `gift`, `year_joined`, `address`, `occupation`, `picture`, `created_at`, `updated_at`) VALUES
(1, 'Denis', 'Junior', '', 'Male', '2001-09-14', '0762924109', 'denisjunior3743@gmail.com', 'Bible teacher', 2021, 'Mbarara', 'Software Engineer', 'profile_6892524b79cf9_1754419787.jpg', '2025-08-05 16:54:17', '2025-08-05 18:49:47'),
(2, 'Namaganda', 'Grace', '', 'Female', '2025-08-05', '0707369266', 'grace@gmail.com', 'Bible teacher', 2023, 'Kyengera', 'Radiologist', NULL, '2025-08-05 17:00:28', '2025-08-05 17:00:28'),
(3, 'Fei', 'Anthia', '', 'Female', '2025-08-29', '07766223344', 'fei@gmail.om', 'Intercessor', 2023, 'Mukono', 'Nurse', NULL, '2025-08-05 17:10:28', '2025-08-05 17:10:28'),
(4, 'Nantume', 'Olivia', '', 'Female', '2011-11-11', '099999999', 'olivia@gmail.com', 'Intercessor', 2017, 'Kisozi', 'Nurse', NULL, '2025-08-05 17:14:08', '2025-08-05 17:14:08'),
(5, 'Mwagalwa ', 'Christine', 'Shalom', 'Female', '2025-08-20', '0333333333', 'shalz@gmail.com', 'Worshipper', 2023, 'Mbarara', 'Business', NULL, '2025-08-05 17:17:19', '2025-08-05 17:17:19'),
(6, 'Kabiito', 'Edward', '', 'Female', '2025-08-29', '0777000999', 'eddy@gmIL.COM', 'Evangelist', 2023, 'Bukoto', '', NULL, '2025-08-05 17:19:01', '2025-08-05 17:19:01'),
(7, 'Arinda', 'Treasure', '', 'Male', '2025-08-08', '0888444555', 'treasure@gmail.com', 'Evangelist', 2024, 'Buddo', '', NULL, '2025-08-05 17:22:09', '2025-08-05 17:22:09');

-- --------------------------------------------------------

--
-- Stand-in structure for view `member_book_completion`
-- (See below for the actual view)
--
CREATE TABLE `member_book_completion` (
`member_id` int(11)
,`full_name` text
,`book_name` varchar(50)
,`total_chapters` int(11)
,`testament` enum('OT','NT')
,`book_order` int(11)
,`chapters_read` bigint(21)
,`status` varchar(11)
,`completion_percent` decimal(26,2)
,`last_read_date` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `member_download_stats`
-- (See below for the actual view)
--
CREATE TABLE `member_download_stats` (
`member_id` int(11)
,`first_name` varchar(255)
,`second_name` varchar(255)
,`third_name` varchar(255)
,`total_downloads` bigint(21)
,`audio_downloads` bigint(21)
,`slides_downloads` bigint(21)
,`complete_downloads` bigint(21)
,`total_downloaded_bytes` decimal(41,0)
,`unique_sermons_downloaded` bigint(21)
,`last_download_date` timestamp
,`first_download_date` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `member_progress_summary`
-- (See below for the actual view)
--
CREATE TABLE `member_progress_summary` (
`member_id` int(11)
,`full_name` text
,`total_chapters_read` bigint(21)
,`ot_chapters_read` bigint(21)
,`nt_chapters_read` bigint(21)
,`overall_progress_percent` decimal(26,2)
,`ot_progress_percent` decimal(26,2)
,`nt_progress_percent` decimal(26,2)
,`last_read_date` timestamp
,`first_read_date` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `discussion_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `type` enum('text','audio','image','file') DEFAULT 'text',
  `content` text NOT NULL,
  `reply_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `discussion_id`, `sender_id`, `type`, `content`, `reply_to`, `created_at`, `updated_at`, `is_deleted`) VALUES
(1, 1, 1, 'text', 'Baptism means immersion', NULL, '2025-08-05 19:36:12', '2025-08-05 19:36:12', 0),
(2, 1, 1, 'audio', '/G.A.N.G/discussion/audio/audio_68925d3fdddf58.96501796.webm', NULL, '2025-08-05 19:36:31', '2025-08-05 19:36:31', 0),
(3, 1, 2, 'text', 'I think its deeping in something', NULL, '2025-08-05 19:37:34', '2025-08-05 19:37:34', 0);

-- --------------------------------------------------------

--
-- Stand-in structure for view `message_details`
-- (See below for the actual view)
--
CREATE TABLE `message_details` (
`id` int(11)
,`discussion_id` int(11)
,`sender_id` int(11)
,`type` enum('text','audio','image','file')
,`content` text
,`reply_to` int(11)
,`created_at` timestamp
,`updated_at` timestamp
,`is_deleted` tinyint(1)
,`first_name` varchar(255)
,`second_name` varchar(255)
,`third_name` varchar(255)
,`picture` varchar(255)
,`username` varchar(255)
,`role_id` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `popular_sermons`
-- (See below for the actual view)
--
CREATE TABLE `popular_sermons` (
`id` int(11)
,`title` varchar(500)
,`speaker` varchar(200)
,`created_at` timestamp
,`download_count` bigint(21)
,`total_downloaded_bytes` decimal(41,0)
,`unique_downloaders` bigint(21)
,`last_download_date` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `reactions`
--

CREATE TABLE `reactions` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `reaction_type` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recording_sessions`
--

CREATE TABLE `recording_sessions` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `sermon_id` int(11) DEFAULT NULL,
  `session_token` varchar(100) NOT NULL,
  `folder_name` varchar(255) NOT NULL,
  `title` varchar(500) DEFAULT NULL,
  `speaker` varchar(200) DEFAULT NULL,
  `status` enum('active','paused','completed','abandoned') DEFAULT 'active',
  `chunks_count` int(11) DEFAULT 0,
  `slides_count` int(11) DEFAULT 0,
  `last_activity_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `description`) VALUES
(1, 'Admin', 'Administrator with full access'),
(2, 'Member', 'Standard member with basic access'),
(3, 'Mobilizer', 'Can create announcements and sermons');

-- --------------------------------------------------------

--
-- Table structure for table `schema_version`
--

CREATE TABLE `schema_version` (
  `version` varchar(20) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schema_version`
--

INSERT INTO `schema_version` (`version`, `applied_at`) VALUES
('1.0.0', '2025-08-05 18:43:02');

-- --------------------------------------------------------

--
-- Table structure for table `sermons`
--

CREATE TABLE `sermons` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `title` varchar(500) NOT NULL,
  `speaker` varchar(200) NOT NULL DEFAULT 'Unknown Speaker',
  `folder_name` varchar(255) NOT NULL,
  `total_chunks` int(11) DEFAULT 0,
  `total_duration_seconds` int(11) DEFAULT 0,
  `total_size_bytes` bigint(20) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sermons`
--

INSERT INTO `sermons` (`id`, `member_id`, `title`, `speaker`, `folder_name`, `total_chunks`, `total_duration_seconds`, `total_size_bytes`, `completed_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'Arm of God', 'Junior', 'Arm_of_God_Junior_20250805184338', 1, 11, 99037, '2025-08-05 18:43:45', '2025-08-05 18:43:45', '2025-08-05 18:43:45'),
(2, 1, 'Baptism', 'Junior', 'Baptism_Junior_20250805184454', 4, 4845, 3185476, '2025-08-05 18:48:09', '2025-08-05 18:48:09', '2025-08-05 18:48:09'),
(3, 1, 'Arm of God', 'Junior', 'Arm_of_God_Junior_20250805185100', 1, 12, 108547, '2025-08-05 18:51:08', '2025-08-05 18:51:08', '2025-08-05 18:51:08');

-- --------------------------------------------------------

--
-- Table structure for table `sermon_chunks`
--

CREATE TABLE `sermon_chunks` (
  `id` int(11) NOT NULL,
  `sermon_id` int(11) NOT NULL,
  `chunk_index` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_size` bigint(20) NOT NULL DEFAULT 0,
  `duration_seconds` int(11) DEFAULT NULL,
  `is_final_chunk` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sermon_chunks`
--

INSERT INTO `sermon_chunks` (`id`, `sermon_id`, `chunk_index`, `filename`, `file_size`, `duration_seconds`, `is_final_chunk`, `created_at`, `updated_at`) VALUES
(1, 1, 0, 'chunk_000.webm', 99037, NULL, 0, '2025-08-05 18:43:45', '2025-08-05 18:43:45'),
(2, 2, 0, 'chunk_000.webm', 980098, NULL, 0, '2025-08-05 18:48:09', '2025-08-05 18:48:09'),
(3, 2, 1, 'chunk_001.webm', 989329, NULL, 0, '2025-08-05 18:48:09', '2025-08-05 18:48:09'),
(4, 2, 2, 'chunk_002.webm', 989440, NULL, 0, '2025-08-05 18:48:09', '2025-08-05 18:48:09'),
(5, 2, 3, 'chunk_003.webm', 226609, NULL, 0, '2025-08-05 18:48:09', '2025-08-05 18:48:09'),
(6, 3, 0, 'chunk_000.webm', 108547, NULL, 0, '2025-08-05 18:51:08', '2025-08-05 18:51:08');

-- --------------------------------------------------------

--
-- Stand-in structure for view `sermon_details_view`
-- (See below for the actual view)
--
CREATE TABLE `sermon_details_view` (
`id` int(11)
,`title` varchar(500)
,`speaker` varchar(200)
,`folder_name` varchar(255)
,`total_chunks` int(11)
,`total_duration_seconds` int(11)
,`total_size_bytes` bigint(20)
,`completed_at` timestamp
,`created_at` timestamp
,`updated_at` timestamp
,`first_name` varchar(255)
,`second_name` varchar(255)
,`third_name` varchar(255)
,`member_email` varchar(255)
,`full_member_name` text
,`actual_chunks` bigint(21)
,`slides_count` bigint(21)
,`total_slides_size` decimal(41,0)
,`status` varchar(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `sermon_download_stats`
-- (See below for the actual view)
--
CREATE TABLE `sermon_download_stats` (
`id` int(11)
,`title` varchar(500)
,`speaker` varchar(200)
,`created_at` timestamp
,`member_id` int(11)
,`first_name` varchar(255)
,`second_name` varchar(255)
,`total_downloads` bigint(21)
,`audio_downloads` bigint(21)
,`slides_downloads` bigint(21)
,`complete_downloads` bigint(21)
,`total_downloaded_bytes` decimal(41,0)
,`last_download_date` timestamp
,`first_download_date` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `sermon_metadata`
--

CREATE TABLE `sermon_metadata` (
  `id` int(11) NOT NULL,
  `sermon_id` int(11) NOT NULL,
  `metadata_key` varchar(100) NOT NULL,
  `metadata_value` text DEFAULT NULL,
  `metadata_type` enum('string','number','boolean','json','datetime') DEFAULT 'string',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sermon_slides`
--

CREATE TABLE `sermon_slides` (
  `id` int(11) NOT NULL,
  `sermon_id` int(11) NOT NULL,
  `original_filename` varchar(500) NOT NULL,
  `saved_filename` varchar(255) NOT NULL,
  `file_size` bigint(20) NOT NULL DEFAULT 0,
  `file_type` varchar(100) NOT NULL,
  `slide_order` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sermon_slides`
--

INSERT INTO `sermon_slides` (`id`, `sermon_id`, `original_filename`, `saved_filename`, `file_size`, `file_type`, `slide_order`, `uploaded_at`, `updated_at`) VALUES
(1, 2, 'Document from John Doe.pdf', 'Document_from_John_Doe.pdf', 390236, 'application/pdf', NULL, '2025-08-05 18:48:09', '2025-08-05 18:48:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `member_id`, `username`, `password_hash`, `role_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'Denis', '$2y$10$5nAbRNK7fDWm7dUkHqb0FeRvmXFrXCu/Ih11UGYfpM7K9RiBUv8Se', 1, '2025-08-05 17:26:03', '2025-08-05 17:26:03'),
(2, 2, 'Grace', '$2y$10$QauVEkQdFA4xmWlFmbACjeeoIu3eCKlKUwYudo9u/zvIKLWTrQAPa', 3, '2025-08-05 17:27:27', '2025-08-05 17:27:27');

-- --------------------------------------------------------

--
-- Structure for view `announcement_stats`
--
DROP TABLE IF EXISTS `announcement_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `announcement_stats`  AS SELECT count(0) AS `total_announcements`, count(case when `announcements`.`status` = 'active' then 1 end) AS `active_announcements`, count(case when `announcements`.`status` = 'expired' then 1 end) AS `expired_announcements`, count(case when `announcements`.`priority` = 'high' and `announcements`.`status` = 'active' then 1 end) AS `high_priority_active`, count(case when `announcements`.`priority` = 'normal' and `announcements`.`status` = 'active' then 1 end) AS `normal_priority_active`, count(case when `announcements`.`priority` = 'low' and `announcements`.`status` = 'active' then 1 end) AS `low_priority_active`, count(case when `announcements`.`event_date` >= curdate() then 1 end) AS `upcoming_events`, count(case when `announcements`.`event_date` < curdate() then 1 end) AS `past_events` FROM `announcements` ;

-- --------------------------------------------------------

--
-- Structure for view `daily_reading_stats`
--
DROP TABLE IF EXISTS `daily_reading_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_reading_stats`  AS SELECT cast(`bp`.`read_at` as date) AS `read_date`, count(distinct `bp`.`member_id`) AS `active_readers`, count(`bp`.`id`) AS `total_chapters_read`, count(case when `bb`.`testament` = 'OT' then 1 end) AS `ot_chapters_read`, count(case when `bb`.`testament` = 'NT' then 1 end) AS `nt_chapters_read`, count(distinct `bp`.`book`) AS `unique_books_read` FROM (`bible_progress` `bp` join `bible_books` `bb` on(`bp`.`book` = `bb`.`name`)) GROUP BY cast(`bp`.`read_at` as date) ORDER BY cast(`bp`.`read_at` as date) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `discussion_list`
--
DROP TABLE IF EXISTS `discussion_list`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `discussion_list`  AS SELECT `d`.`id` AS `id`, `d`.`topic` AS `topic`, `d`.`created_by` AS `created_by`, `d`.`created_at` AS `created_at`, `d`.`updated_at` AS `updated_at`, `d`.`status` AS `status`, `mem`.`first_name` AS `first_name`, `mem`.`second_name` AS `second_name`, `mem`.`third_name` AS `third_name`, `mem`.`picture` AS `picture`, `u`.`username` AS `username`, `u`.`role_id` AS `role_id`, (select count(0) from `messages` where `messages`.`discussion_id` = `d`.`id` and `messages`.`is_deleted` = 0) AS `message_count`, (select max(`messages`.`created_at`) from `messages` where `messages`.`discussion_id` = `d`.`id` and `messages`.`is_deleted` = 0) AS `last_message_at` FROM ((`discussions` `d` join `users` `u` on(`d`.`created_by` = `u`.`id`)) join `members` `mem` on(`u`.`member_id` = `mem`.`id`)) WHERE `d`.`status` = 'active' ;

-- --------------------------------------------------------

--
-- Structure for view `download_stats_view`
--
DROP TABLE IF EXISTS `download_stats_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `download_stats_view`  AS SELECT `s`.`id` AS `sermon_id`, `s`.`title` AS `title`, `s`.`speaker` AS `speaker`, `s`.`folder_name` AS `folder_name`, count(`dl`.`id`) AS `total_downloads`, count(case when `dl`.`download_type` = 'audio' then 1 end) AS `audio_downloads`, count(case when `dl`.`download_type` = 'slides' then 1 end) AS `slides_downloads`, count(case when `dl`.`download_type` = 'complete' then 1 end) AS `complete_downloads`, sum(`dl`.`file_size`) AS `total_downloaded_bytes`, max(`dl`.`downloaded_at`) AS `last_download_at`, count(distinct `dl`.`member_id`) AS `unique_downloaders` FROM (`sermons` `s` left join `download_logs` `dl` on(`s`.`id` = `dl`.`sermon_id`)) GROUP BY `s`.`id`, `s`.`title`, `s`.`speaker`, `s`.`folder_name` ;

-- --------------------------------------------------------

--
-- Structure for view `member_book_completion`
--
DROP TABLE IF EXISTS `member_book_completion`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `member_book_completion`  AS SELECT `m`.`id` AS `member_id`, concat_ws(' ',`m`.`first_name`,`m`.`second_name`,`m`.`third_name`) AS `full_name`, `bb`.`name` AS `book_name`, `bb`.`chapters` AS `total_chapters`, `bb`.`testament` AS `testament`, `bb`.`book_order` AS `book_order`, count(`bp`.`chapter`) AS `chapters_read`, CASE WHEN count(`bp`.`chapter`) = `bb`.`chapters` THEN 'completed' WHEN count(`bp`.`chapter`) > 0 THEN 'in_progress' ELSE 'not_started' END AS `status`, round(count(`bp`.`chapter`) / `bb`.`chapters` * 100,2) AS `completion_percent`, max(`bp`.`read_at`) AS `last_read_date` FROM ((`members` `m` join `bible_books` `bb`) left join `bible_progress` `bp` on(`m`.`id` = `bp`.`member_id` and `bb`.`name` = `bp`.`book`)) GROUP BY `m`.`id`, `m`.`first_name`, `m`.`second_name`, `m`.`third_name`, `bb`.`name`, `bb`.`chapters`, `bb`.`testament`, `bb`.`book_order` ORDER BY `m`.`id` ASC, `bb`.`book_order` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `member_download_stats`
--
DROP TABLE IF EXISTS `member_download_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `member_download_stats`  AS SELECT `m`.`id` AS `member_id`, `m`.`first_name` AS `first_name`, `m`.`second_name` AS `second_name`, `m`.`third_name` AS `third_name`, count(`dl`.`id`) AS `total_downloads`, count(case when `dl`.`download_type` = 'audio' then 1 end) AS `audio_downloads`, count(case when `dl`.`download_type` = 'slides' then 1 end) AS `slides_downloads`, count(case when `dl`.`download_type` = 'complete' then 1 end) AS `complete_downloads`, coalesce(sum(`dl`.`file_size`),0) AS `total_downloaded_bytes`, count(distinct `dl`.`sermon_id`) AS `unique_sermons_downloaded`, max(`dl`.`downloaded_at`) AS `last_download_date`, min(`dl`.`downloaded_at`) AS `first_download_date` FROM (`members` `m` left join `download_logs` `dl` on(`m`.`id` = `dl`.`member_id`)) GROUP BY `m`.`id`, `m`.`first_name`, `m`.`second_name`, `m`.`third_name` ORDER BY count(`dl`.`id`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `member_progress_summary`
--
DROP TABLE IF EXISTS `member_progress_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `member_progress_summary`  AS SELECT `m`.`id` AS `member_id`, concat_ws(' ',`m`.`first_name`,`m`.`second_name`,`m`.`third_name`) AS `full_name`, count(`bp`.`id`) AS `total_chapters_read`, count(case when `bb`.`testament` = 'OT' then 1 end) AS `ot_chapters_read`, count(case when `bb`.`testament` = 'NT' then 1 end) AS `nt_chapters_read`, round(count(`bp`.`id`) / 1189 * 100,2) AS `overall_progress_percent`, round(count(case when `bb`.`testament` = 'OT' then 1 end) / 929 * 100,2) AS `ot_progress_percent`, round(count(case when `bb`.`testament` = 'NT' then 1 end) / 260 * 100,2) AS `nt_progress_percent`, max(`bp`.`read_at`) AS `last_read_date`, min(`bp`.`read_at`) AS `first_read_date` FROM ((`members` `m` left join `bible_progress` `bp` on(`m`.`id` = `bp`.`member_id`)) left join `bible_books` `bb` on(`bp`.`book` = `bb`.`name`)) GROUP BY `m`.`id`, `m`.`first_name`, `m`.`second_name`, `m`.`third_name` ;

-- --------------------------------------------------------

--
-- Structure for view `message_details`
--
DROP TABLE IF EXISTS `message_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `message_details`  AS SELECT `m`.`id` AS `id`, `m`.`discussion_id` AS `discussion_id`, `m`.`sender_id` AS `sender_id`, `m`.`type` AS `type`, `m`.`content` AS `content`, `m`.`reply_to` AS `reply_to`, `m`.`created_at` AS `created_at`, `m`.`updated_at` AS `updated_at`, `m`.`is_deleted` AS `is_deleted`, `mem`.`first_name` AS `first_name`, `mem`.`second_name` AS `second_name`, `mem`.`third_name` AS `third_name`, `mem`.`picture` AS `picture`, `u`.`username` AS `username`, `u`.`role_id` AS `role_id` FROM ((`messages` `m` join `users` `u` on(`m`.`sender_id` = `u`.`id`)) join `members` `mem` on(`u`.`member_id` = `mem`.`id`)) WHERE `m`.`is_deleted` = 0 ;

-- --------------------------------------------------------

--
-- Structure for view `popular_sermons`
--
DROP TABLE IF EXISTS `popular_sermons`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `popular_sermons`  AS SELECT `s`.`id` AS `id`, `s`.`title` AS `title`, `s`.`speaker` AS `speaker`, `s`.`created_at` AS `created_at`, count(`dl`.`id`) AS `download_count`, coalesce(sum(`dl`.`file_size`),0) AS `total_downloaded_bytes`, count(distinct `dl`.`member_id`) AS `unique_downloaders`, max(`dl`.`downloaded_at`) AS `last_download_date` FROM (`sermons` `s` left join `download_logs` `dl` on(`s`.`id` = `dl`.`sermon_id`)) GROUP BY `s`.`id`, `s`.`title`, `s`.`speaker`, `s`.`created_at` HAVING `download_count` > 0 ORDER BY count(`dl`.`id`) DESC, count(distinct `dl`.`member_id`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `sermon_details_view`
--
DROP TABLE IF EXISTS `sermon_details_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `sermon_details_view`  AS SELECT `s`.`id` AS `id`, `s`.`title` AS `title`, `s`.`speaker` AS `speaker`, `s`.`folder_name` AS `folder_name`, `s`.`total_chunks` AS `total_chunks`, `s`.`total_duration_seconds` AS `total_duration_seconds`, `s`.`total_size_bytes` AS `total_size_bytes`, `s`.`completed_at` AS `completed_at`, `s`.`created_at` AS `created_at`, `s`.`updated_at` AS `updated_at`, `m`.`first_name` AS `first_name`, `m`.`second_name` AS `second_name`, `m`.`third_name` AS `third_name`, `m`.`email` AS `member_email`, concat_ws(' ',`m`.`first_name`,`m`.`second_name`,`m`.`third_name`) AS `full_member_name`, (select count(0) from `sermon_chunks` `sc` where `sc`.`sermon_id` = `s`.`id`) AS `actual_chunks`, (select count(0) from `sermon_slides` `ss` where `ss`.`sermon_id` = `s`.`id`) AS `slides_count`, (select sum(`ss`.`file_size`) from `sermon_slides` `ss` where `ss`.`sermon_id` = `s`.`id`) AS `total_slides_size`, CASE WHEN `s`.`completed_at` is not null THEN 'completed' WHEN `s`.`total_chunks` > 0 THEN 'in_progress' ELSE 'started' END AS `status` FROM (`sermons` `s` left join `members` `m` on(`s`.`member_id` = `m`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `sermon_download_stats`
--
DROP TABLE IF EXISTS `sermon_download_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `sermon_download_stats`  AS SELECT `s`.`id` AS `id`, `s`.`title` AS `title`, `s`.`speaker` AS `speaker`, `s`.`created_at` AS `created_at`, `s`.`member_id` AS `member_id`, `m`.`first_name` AS `first_name`, `m`.`second_name` AS `second_name`, count(`dl`.`id`) AS `total_downloads`, count(case when `dl`.`download_type` = 'audio' then 1 end) AS `audio_downloads`, count(case when `dl`.`download_type` = 'slides' then 1 end) AS `slides_downloads`, count(case when `dl`.`download_type` = 'complete' then 1 end) AS `complete_downloads`, coalesce(sum(`dl`.`file_size`),0) AS `total_downloaded_bytes`, max(`dl`.`downloaded_at`) AS `last_download_date`, min(`dl`.`downloaded_at`) AS `first_download_date` FROM ((`sermons` `s` left join `members` `m` on(`s`.`member_id` = `m`.`id`)) left join `download_logs` `dl` on(`s`.`id` = `dl`.`sermon_id`)) GROUP BY `s`.`id`, `s`.`title`, `s`.`speaker`, `s`.`created_at`, `s`.`member_id`, `m`.`first_name`, `m`.`second_name` ORDER BY `s`.`created_at` DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `bible_books`
--
ALTER TABLE `bible_books`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `unique_book_name` (`name`),
  ADD UNIQUE KEY `unique_book_order` (`book_order`),
  ADD KEY `idx_testament` (`testament`),
  ADD KEY `idx_book_order` (`book_order`),
  ADD KEY `idx_testament_order` (`testament`,`book_order`);

--
-- Indexes for table `bible_notes`
--
ALTER TABLE `bible_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `bible_progress`
--
ALTER TABLE `bible_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_member_book_chapter` (`member_id`,`book`,`chapter`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_book` (`book`),
  ADD KEY `idx_chapter` (`chapter`),
  ADD KEY `idx_read_at` (`read_at`),
  ADD KEY `idx_member_book` (`member_id`,`book`),
  ADD KEY `idx_member_book_read_date` (`member_id`,`book`,`read_at`),
  ADD KEY `idx_read_date` (`read_at`);

--
-- Indexes for table `discussions`
--
ALTER TABLE `discussions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_discussions_status_created` (`status`,`created_at`);

--
-- Indexes for table `download_logs`
--
ALTER TABLE `download_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_sermon_id` (`sermon_id`),
  ADD KEY `idx_download_type` (`download_type`),
  ADD KEY `idx_downloaded_at` (`downloaded_at`),
  ADD KEY `idx_member_sermon_date` (`member_id`,`sermon_id`,`downloaded_at`);

--
-- Indexes for table `enquiries`
--
ALTER TABLE `enquiries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_discussion_id` (`discussion_id`),
  ADD KEY `idx_sender_id` (`sender_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_reply_to` (`reply_to`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_is_deleted` (`is_deleted`),
  ADD KEY `idx_messages_discussion_created` (`discussion_id`,`created_at`);

--
-- Indexes for table `reactions`
--
ALTER TABLE `reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `message_id_member_id` (`message_id`,`member_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `recording_sessions`
--
ALTER TABLE `recording_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD UNIQUE KEY `unique_session_token` (`session_token`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_sermon_id` (`sermon_id`),
  ADD KEY `idx_folder_name` (`folder_name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_last_activity` (`last_activity_at`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `schema_version`
--
ALTER TABLE `schema_version`
  ADD PRIMARY KEY (`version`);

--
-- Indexes for table `sermons`
--
ALTER TABLE `sermons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `folder_name` (`folder_name`),
  ADD UNIQUE KEY `unique_folder` (`folder_name`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_completed_at` (`completed_at`),
  ADD KEY `idx_speaker` (`speaker`),
  ADD KEY `idx_title` (`title`(100)),
  ADD KEY `idx_member_completed` (`member_id`,`completed_at`),
  ADD KEY `idx_created_completed` (`created_at`,`completed_at`);
ALTER TABLE `sermons` ADD FULLTEXT KEY `ft_title_speaker` (`title`,`speaker`);

--
-- Indexes for table `sermon_chunks`
--
ALTER TABLE `sermon_chunks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sermon_chunk` (`sermon_id`,`chunk_index`),
  ADD KEY `idx_sermon_id` (`sermon_id`),
  ADD KEY `idx_chunk_index` (`chunk_index`),
  ADD KEY `idx_filename` (`filename`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_sermon_chunk_order` (`sermon_id`,`chunk_index`);

--
-- Indexes for table `sermon_metadata`
--
ALTER TABLE `sermon_metadata`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sermon_metadata` (`sermon_id`,`metadata_key`),
  ADD KEY `idx_sermon_id` (`sermon_id`),
  ADD KEY `idx_metadata_key` (`metadata_key`);

--
-- Indexes for table `sermon_slides`
--
ALTER TABLE `sermon_slides`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sermon_id` (`sermon_id`),
  ADD KEY `idx_saved_filename` (`saved_filename`),
  ADD KEY `idx_file_type` (`file_type`),
  ADD KEY `idx_slide_order` (`slide_order`),
  ADD KEY `idx_uploaded_at` (`uploaded_at`),
  ADD KEY `idx_sermon_upload_date` (`sermon_id`,`uploaded_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_id` (`member_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bible_books`
--
ALTER TABLE `bible_books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `bible_notes`
--
ALTER TABLE `bible_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bible_progress`
--
ALTER TABLE `bible_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `discussions`
--
ALTER TABLE `discussions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `download_logs`
--
ALTER TABLE `download_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enquiries`
--
ALTER TABLE `enquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reactions`
--
ALTER TABLE `reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recording_sessions`
--
ALTER TABLE `recording_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sermons`
--
ALTER TABLE `sermons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sermon_chunks`
--
ALTER TABLE `sermon_chunks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sermon_metadata`
--
ALTER TABLE `sermon_metadata`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sermon_slides`
--
ALTER TABLE `sermon_slides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bible_notes`
--
ALTER TABLE `bible_notes`
  ADD CONSTRAINT `bible_notes_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bible_notes_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `bible_books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bible_progress`
--
ALTER TABLE `bible_progress`
  ADD CONSTRAINT `bible_progress_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `bible_progress_ibfk_2` FOREIGN KEY (`book`) REFERENCES `bible_books` (`name`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `discussions`
--
ALTER TABLE `discussions`
  ADD CONSTRAINT `discussions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `download_logs`
--
ALTER TABLE `download_logs`
  ADD CONSTRAINT `download_logs_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `download_logs_ibfk_2` FOREIGN KEY (`sermon_id`) REFERENCES `sermons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`discussion_id`) REFERENCES `discussions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`reply_to`) REFERENCES `messages` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reactions`
--
ALTER TABLE `reactions`
  ADD CONSTRAINT `reactions_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reactions_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recording_sessions`
--
ALTER TABLE `recording_sessions`
  ADD CONSTRAINT `recording_sessions_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `recording_sessions_ibfk_2` FOREIGN KEY (`sermon_id`) REFERENCES `sermons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `sermons`
--
ALTER TABLE `sermons`
  ADD CONSTRAINT `sermons_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sermon_chunks`
--
ALTER TABLE `sermon_chunks`
  ADD CONSTRAINT `sermon_chunks_ibfk_1` FOREIGN KEY (`sermon_id`) REFERENCES `sermons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sermon_metadata`
--
ALTER TABLE `sermon_metadata`
  ADD CONSTRAINT `sermon_metadata_ibfk_1` FOREIGN KEY (`sermon_id`) REFERENCES `sermons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sermon_slides`
--
ALTER TABLE `sermon_slides`
  ADD CONSTRAINT `sermon_slides_ibfk_1` FOREIGN KEY (`sermon_id`) REFERENCES `sermons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
