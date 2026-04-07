-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 07, 2026 at 07:11 AM
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
-- Database: `gravetrack_db`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `burial_records_view`
-- (See below for the actual view)
--
CREATE TABLE `burial_records_view` (
`full_name` varchar(150)
,`date_of_death` date
,`date_of_burial` date
,`gender` enum('Male','Female','Other')
,`contact_person` varchar(150)
,`contact_number` varchar(20)
,`address` text
,`plot_location` varchar(156)
,`burial_type` varchar(50)
);

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `contact_id` int(11) NOT NULL,
  `deceased_id` int(11) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contacts`
--

INSERT INTO `contacts` (`contact_id`, `deceased_id`, `contact_person`, `contact_number`) VALUES
(1, 1, 'Pedro Santos', '09123456789'),
(4, 5, 'Luisa Mendoza', '09987654321');

-- --------------------------------------------------------

--
-- Table structure for table `deceased`
--

CREATE TABLE `deceased` (
  `deceased_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `date_of_death` date DEFAULT NULL,
  `date_of_burial` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `plot_id` int(11) DEFAULT NULL,
  `burial_type` varchar(50) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deceased`
--

INSERT INTO `deceased` (`deceased_id`, `full_name`, `date_of_death`, `date_of_burial`, `gender`, `address`, `plot_id`, `burial_type`, `created_by`) VALUES
(1, 'Maria Santos', '2024-01-10', '2024-01-15', 'Female', 'Lipa City, Batangas', 1, 'Standard Burial', 1),
(5, 'Jose Mendoza', '2023-11-05', '2023-11-10', 'Male', 'Batangas City', 2, 'Family Burial', 1);

--
-- Triggers `deceased`
--
DELIMITER $$
CREATE TRIGGER `after_burial_insert` AFTER INSERT ON `deceased` FOR EACH ROW BEGIN
    UPDATE plots
    SET status = 'Occupied'
    WHERE plot_id = NEW.plot_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `rental_id` int(11) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` enum('Paid','Pending') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `rental_id`, `payment_date`, `amount`, `status`) VALUES
(1, 1, '2024-01-16', 5000.00, 'Paid'),
(2, 3, '2023-11-11', 7000.00, 'Paid');

-- --------------------------------------------------------

--
-- Table structure for table `plots`
--

CREATE TABLE `plots` (
  `plot_id` int(11) NOT NULL,
  `block` varchar(50) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `lot` varchar(50) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `status` enum('Vacant','Occupied','Reserved') DEFAULT 'Vacant'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plots`
--

INSERT INTO `plots` (`plot_id`, `block`, `section`, `lot`, `type`, `status`) VALUES
(1, 'A', '1', '101', 'Lawn', 'Occupied'),
(2, 'B', '2', '202', 'Lawn', 'Occupied');

-- --------------------------------------------------------

--
-- Table structure for table `rentals`
--

CREATE TABLE `rentals` (
  `rental_id` int(11) NOT NULL,
  `deceased_id` int(11) DEFAULT NULL,
  `plot_id` int(11) DEFAULT NULL,
  `rental_start` date DEFAULT NULL,
  `rental_end` date DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` enum('Active','Expired','Paid','Unpaid') DEFAULT 'Unpaid',
  `processed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rentals`
--

INSERT INTO `rentals` (`rental_id`, `deceased_id`, `plot_id`, `rental_start`, `rental_end`, `amount`, `status`, `processed_by`) VALUES
(1, 1, 1, '2024-01-15', '2025-01-15', 5000.00, 'Unpaid', 1),
(3, 5, 2, '2023-11-10', '2024-11-10', 7000.00, 'Paid', 1);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `transaction_date` date DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `name`, `transaction_date`, `amount`, `type`, `user_id`) VALUES
(1, 'Maria Santos', '2024-01-16', 5000.00, 'Payment', 1),
(2, 'Jose Mendoza', '2023-11-11', 7000.00, 'Payment', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Engineer','Treasurer') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'testdummy1', 'test@gmail.com', '12345', 'Treasurer', '2026-04-07 04:13:15');

-- --------------------------------------------------------

--
-- Structure for view `burial_records_view`
--
DROP TABLE IF EXISTS `burial_records_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `burial_records_view`  AS SELECT `d`.`full_name` AS `full_name`, `d`.`date_of_death` AS `date_of_death`, `d`.`date_of_burial` AS `date_of_burial`, `d`.`gender` AS `gender`, `c`.`contact_person` AS `contact_person`, `c`.`contact_number` AS `contact_number`, `d`.`address` AS `address`, concat(`p`.`block`,' - ',`p`.`section`,' - ',`p`.`lot`) AS `plot_location`, `d`.`burial_type` AS `burial_type` FROM ((`deceased` `d` left join `contacts` `c` on(`d`.`deceased_id` = `c`.`deceased_id`)) left join `plots` `p` on(`d`.`plot_id` = `p`.`plot_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`contact_id`),
  ADD KEY `deceased_id` (`deceased_id`);

--
-- Indexes for table `deceased`
--
ALTER TABLE `deceased`
  ADD PRIMARY KEY (`deceased_id`),
  ADD KEY `plot_id` (`plot_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `rental_id` (`rental_id`);

--
-- Indexes for table `plots`
--
ALTER TABLE `plots`
  ADD PRIMARY KEY (`plot_id`);

--
-- Indexes for table `rentals`
--
ALTER TABLE `rentals`
  ADD PRIMARY KEY (`rental_id`),
  ADD KEY `deceased_id` (`deceased_id`),
  ADD KEY `plot_id` (`plot_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `contact_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `deceased`
--
ALTER TABLE `deceased`
  MODIFY `deceased_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `plots`
--
ALTER TABLE `plots`
  MODIFY `plot_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `rentals`
--
ALTER TABLE `rentals`
  MODIFY `rental_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `contacts`
--
ALTER TABLE `contacts`
  ADD CONSTRAINT `contacts_ibfk_1` FOREIGN KEY (`deceased_id`) REFERENCES `deceased` (`deceased_id`) ON DELETE CASCADE;

--
-- Constraints for table `deceased`
--
ALTER TABLE `deceased`
  ADD CONSTRAINT `deceased_ibfk_1` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`plot_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `deceased_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`rental_id`) REFERENCES `rentals` (`rental_id`) ON DELETE CASCADE;

--
-- Constraints for table `rentals`
--
ALTER TABLE `rentals`
  ADD CONSTRAINT `rentals_ibfk_1` FOREIGN KEY (`deceased_id`) REFERENCES `deceased` (`deceased_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rentals_ibfk_2` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`plot_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rentals_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
