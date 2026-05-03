-- GraveTrack Database - Clean Complete Setup
-- This is a single unified import file with all merged actions
-- No duplicates, no forward references, no errors

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ========================================
-- DROP EXISTING DATABASE (CLEAN START)
-- ========================================
DROP DATABASE IF EXISTS `gravetrack_db`;
CREATE DATABASE `gravetrack_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `gravetrack_db`;

-- ========================================
-- TABLE 1: users
-- ========================================
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(150) NOT NULL UNIQUE,
  `email` varchar(150) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('Engineer','Treasurer','Admin','Staff') NOT NULL DEFAULT 'Staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role`) VALUES
(1, 'testdummy1', 'test@gmail.com', '12345', 'Treasurer'),
(2, 'admin', 'admin@gravetrack.com', 'admin123', 'Admin');

-- ========================================
-- TABLE 2: phases
-- ========================================
CREATE TABLE `phases` (
  `phase_id` int(11) NOT NULL AUTO_INCREMENT,
  `phase_number` int(11) NOT NULL UNIQUE,
  `phase_name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`phase_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `phases` (`phase_number`, `phase_name`, `description`) VALUES
(1, 'Phase 1', 'Cemetery Phase 1 (Blocks A-I) - Traditional cemetery layout'),
(2, 'Phase 2', 'Cemetery Phase 2 (Blocks T-Z) - Traditional cemetery layout'),
(3, 'Phase 3 - Apartment Court', 'Cemetery Phase 3 - Apartment Court (Block AA with 50 lots per section × 4 sections)');

-- ========================================
-- TABLE 3: blocks
-- ========================================
CREATE TABLE `blocks` (
  `block_id` int(11) NOT NULL AUTO_INCREMENT,
  `block_name` varchar(10) NOT NULL UNIQUE,
  `phase_id` int(11) DEFAULT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`block_id`),
  FOREIGN KEY (`phase_id`) REFERENCES `phases` (`phase_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `blocks` (`block_name`, `phase_id`) VALUES
('A', 1), ('B', 1), ('C', 1), ('D', 1), ('E', 1),
('F', 1), ('G', 1), ('H', 1), ('I', 1),
('T', 2), ('U', 2), ('V', 2), ('W', 2), ('X', 2), ('Y', 2), ('Z', 2),
('AA', 3);

-- ========================================
-- TABLE 4: sections
-- ========================================
CREATE TABLE `sections` (
  `section_id` int(11) NOT NULL AUTO_INCREMENT,
  `section_name` varchar(100) NOT NULL,
  `block_id` int(11) DEFAULT NULL,
  `section_number` int(11),
  `total_plots` int(11) DEFAULT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`section_id`),
  FOREIGN KEY (`block_id`) REFERENCES `blocks` (`block_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sections` (`section_name`, `block_id`, `section_number`, `total_plots`) VALUES
('Section 1', 17, 1, 50),
('Section 2', 17, 2, 50),
('Section 3', 17, 3, 50),
('Section 4', 17, 4, 50);

-- ========================================
-- TABLE 5: plots (CLEAN - NO DUPLICATE KEYS)
-- ========================================
CREATE TABLE `plots` (
  `plot_id` int(11) NOT NULL AUTO_INCREMENT,
  `plot_code` varchar(50),
  `block_id` int(11) DEFAULT NULL,
  `block` varchar(50) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `section_number` int(11) DEFAULT NULL,
  `lot` varchar(50) DEFAULT NULL,
  `lot_number` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `status` enum('Vacant','Occupied','Reserved') DEFAULT 'Vacant',
  `max_occupants` int(11) DEFAULT 1,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`plot_id`),
  UNIQUE KEY `uk_plot_code` (`plot_code`),
  KEY `fk_block_id` (`block_id`),
  KEY `idx_block` (`block`),
  KEY `idx_section` (`section`),
  KEY `idx_lot` (`lot`),
  CONSTRAINT `fk_plots_blocks` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`block_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `plots` (`plot_code`, `block_id`, `block`, `section`, `lot`, `type`, `status`) VALUES
('A-1', 1, 'A', '1', '1', 'Single', 'Occupied'),
('A-2', 1, 'A', '2', '2', 'Single', 'Vacant'),
('B-1', 2, 'B', '1', '1', 'Single', 'Occupied'),
('AA-S1-L1', 17, 'AA', '1', '1', 'Apartment', 'Occupied'),
('AA-S1-L2', 17, 'AA', '1', '2', 'Apartment', 'Vacant');

-- ========================================
-- TABLE 6: deceased
-- ========================================
CREATE TABLE `deceased` (
  `deceased_id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `date_of_death` date DEFAULT NULL,
  `date_of_burial` date DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `plot_id` int(11) DEFAULT NULL,
  `burial_type` varchar(50) DEFAULT NULL,
  `payment_status` enum('unpaid','paid','partial') DEFAULT 'unpaid',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`deceased_id`),
  KEY `fk_deceased_plot` (`plot_id`),
  KEY `fk_deceased_user` (`created_by`),
  CONSTRAINT `fk_deceased_plots` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`plot_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_deceased_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `deceased` (`deceased_id`, `full_name`, `date_of_death`, `date_of_burial`, `birth_date`, `gender`, `address`, `plot_id`, `burial_type`, `payment_status`, `created_by`) VALUES
(1, 'Maria Santos', '2024-01-10', '2024-01-15', '1950-05-03', 'Female', 'Lipa City, Batangas', 1, 'Family Burial', 'paid', 1),
(2, 'Jose Mendoza', '2023-11-05', '2023-11-10', '1945-03-15', 'Male', 'Batangas City', 3, 'Family Burial', 'paid', 1),
(3, 'WATATA', '2026-05-14', '2026-05-15', NULL, 'Male', '0403, Sitio palico, Bilaran', 4, 'Ground', 'unpaid', NULL);

-- ========================================
-- TABLE 7: contacts
-- ========================================
CREATE TABLE `contacts` (
  `contact_id` int(11) NOT NULL AUTO_INCREMENT,
  `deceased_id` int(11) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `relationship` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`contact_id`),
  KEY `fk_contact_deceased` (`deceased_id`),
  CONSTRAINT `fk_contacts_deceased` FOREIGN KEY (`deceased_id`) REFERENCES `deceased` (`deceased_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `contacts` (`deceased_id`, `contact_person`, `contact_number`, `relationship`) VALUES
(1, 'Pedro Santos', '09123456789', 'Son'),
(2, 'Luisa Mendoza', '09987654321', 'Spouse'),
(3, 'Niel Francis Benedict', '09566632253', 'Relative');

-- ========================================
-- TABLE 8: rentals
-- ========================================
CREATE TABLE `rentals` (
  `rental_id` int(11) NOT NULL AUTO_INCREMENT,
  `deceased_id` int(11) DEFAULT NULL,
  `plot_id` int(11) DEFAULT NULL,
  `rental_start` date DEFAULT NULL,
  `rental_end` date DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` enum('Active','Expired','Paid','Unpaid','Partial') DEFAULT 'Unpaid',
  `billing_cycle` varchar(50) DEFAULT 'Annual',
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`rental_id`),
  KEY `fk_rental_deceased` (`deceased_id`),
  KEY `fk_rental_plot` (`plot_id`),
  KEY `fk_rental_user` (`processed_by`),
  CONSTRAINT `fk_rentals_deceased` FOREIGN KEY (`deceased_id`) REFERENCES `deceased` (`deceased_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rentals_plots` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`plot_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rentals_users` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `rentals` (`deceased_id`, `plot_id`, `rental_start`, `rental_end`, `amount`, `status`, `processed_by`) VALUES
(1, 1, '2024-01-15', '2025-01-15', 5000.00, 'Paid', 1),
(2, 3, '2023-11-10', '2024-11-10', 7000.00, 'Paid', 1);

-- ========================================
-- TABLE 9: payments
-- ========================================
CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `rental_id` int(11) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `status` enum('Paid','Pending','Failed') DEFAULT 'Pending',
  `received_by` int(11) DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payment_id`),
  KEY `fk_payment_rental` (`rental_id`),
  KEY `fk_payment_user` (`received_by`),
  CONSTRAINT `fk_payments_rentals` FOREIGN KEY (`rental_id`) REFERENCES `rentals` (`rental_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_users` FOREIGN KEY (`received_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `payments` (`rental_id`, `payment_date`, `amount`, `status`, `received_by`) VALUES
(1, '2024-01-16', 5000.00, 'Paid', 1),
(2, '2023-11-11', 7000.00, 'Paid', 1);

-- ========================================
-- TABLE 10: transactions
-- ========================================
CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) DEFAULT NULL,
  `transaction_date` date DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `type` enum('Payment','Refund','Adjustment','Other') DEFAULT 'Payment',
  `description` text,
  `user_id` int(11) DEFAULT NULL,
  `deceased_id` int(11) DEFAULT NULL,
  `rental_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`transaction_id`),
  KEY `fk_transaction_user` (`user_id`),
  KEY `fk_transaction_deceased` (`deceased_id`),
  KEY `fk_transaction_rental` (`rental_id`),
  KEY `idx_transaction_date` (`transaction_date`),
  CONSTRAINT `fk_transactions_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_transactions_deceased` FOREIGN KEY (`deceased_id`) REFERENCES `deceased` (`deceased_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_transactions_rentals` FOREIGN KEY (`rental_id`) REFERENCES `rentals` (`rental_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `transactions` (`name`, `transaction_date`, `amount`, `type`, `user_id`, `deceased_id`) VALUES
('Maria Santos', '2024-01-16', 5000.00, 'Payment', 1, 1),
('Jose Mendoza', '2023-11-11', 7000.00, 'Payment', 1, 2);

-- ========================================
-- TABLE 11: activity_logs
-- ========================================
CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(100) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `fk_activity_user` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_activity_logs_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TRIGGERS
-- ========================================
DELIMITER $$

CREATE TRIGGER `after_deceased_insert` AFTER INSERT ON `deceased` FOR EACH ROW 
BEGIN
    UPDATE plots SET status = 'Occupied' WHERE plot_id = NEW.plot_id;
END $$

CREATE TRIGGER `after_deceased_delete` AFTER DELETE ON `deceased` FOR EACH ROW 
BEGIN
    IF (SELECT COUNT(*) FROM deceased WHERE plot_id = OLD.plot_id) = 0 THEN
        UPDATE plots SET status = 'Vacant' WHERE plot_id = OLD.plot_id;
    END IF;
END $$

DELIMITER ;

-- ========================================
-- VIEWS
-- ========================================

CREATE VIEW `burial_records_view` AS
SELECT 
  d.deceased_id,
  d.full_name,
  d.date_of_death,
  d.date_of_burial,
  d.gender,
  c.contact_person,
  c.contact_number,
  d.address,
  CONCAT(IFNULL(p.block, 'XX'), ' - ', IFNULL(p.section, 'N/A'), ' - ', IFNULL(p.lot, '0')) AS plot_location,
  p.plot_code,
  d.burial_type,
  d.payment_status,
  r.amount AS rental_amount,
  r.status AS rental_status
FROM deceased d
LEFT JOIN contacts c ON d.deceased_id = c.deceased_id
LEFT JOIN plots p ON d.plot_id = p.plot_id
LEFT JOIN rentals r ON d.deceased_id = r.deceased_id
ORDER BY d.created_at DESC;

CREATE VIEW `plot_phase_view` AS
SELECT 
  p.plot_id,
  p.plot_code,
  p.block_id,
  IFNULL(b.block_name, p.block) AS block_name,
  p.block,
  p.section,
  p.lot,
  p.type,
  p.status,
  p.date_added,
  IFNULL(ph.phase_number, 0) AS phase_number,
  IFNULL(ph.phase_name, 'Unassigned') AS phase_name,
  COUNT(d.deceased_id) AS occupant_count,
  GROUP_CONCAT(DISTINCT d.full_name SEPARATOR ', ') AS deceased_names
FROM plots p
LEFT JOIN blocks b ON p.block_id = b.block_id
LEFT JOIN phases ph ON b.phase_id = ph.phase_id
LEFT JOIN deceased d ON p.plot_id = d.plot_id
GROUP BY p.plot_id;

CREATE VIEW `cemetery_map_view` AS
SELECT 
  p.plot_id,
  p.plot_code,
  p.lot_number,
  p.section_number,
  IFNULL(b.block_id, 0) AS block_id,
  IFNULL(b.block_name, p.block) AS block_name,
  IFNULL(ph.phase_id, 0) AS phase_id,
  IFNULL(ph.phase_number, 0) AS phase_number,
  IFNULL(ph.phase_name, 'Unassigned') AS phase_name,
  COUNT(d.deceased_id) as occupant_count,
  GROUP_CONCAT(DISTINCT d.full_name SEPARATOR ', ') as deceased_names,
  COALESCE(MAX(d.payment_status), 'unpaid') as payment_status,
  p.status as plot_status
FROM plots p
LEFT JOIN blocks b ON p.block_id = b.block_id
LEFT JOIN phases ph ON b.phase_id = ph.phase_id
LEFT JOIN deceased d ON p.plot_id = d.plot_id
GROUP BY p.plot_id;

-- ========================================
-- FINALIZE
-- ========================================
COMMIT;
