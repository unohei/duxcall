-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- ホスト: localhost
-- 生成日時: 2026 年 1 月 06 日 22:34
-- サーバのバージョン： 10.4.28-MariaDB
-- PHP のバージョン: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `medical_mvp`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `hospitals`
--

CREATE TABLE `hospitals` (
  `id` int(11) NOT NULL,
  `hospital_code` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Tokyo',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `hospitals`
--

INSERT INTO `hospitals` (`id`, `hospital_code`, `name`, `timezone`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'tokyo-clinic', '東京テスト病院', 'Asia/Tokyo', 1, '2026-01-01 15:45:53', '2026-01-07 06:31:58');

-- --------------------------------------------------------

--
-- テーブルの構造 `news`
--

CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `priority` enum('high','normal') NOT NULL DEFAULT 'normal',
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `news`
--

INSERT INTO `news` (`id`, `hospital_id`, `title`, `body`, `priority`, `is_published`, `updated_at`) VALUES
(1, 1, '面会のご案内', '現在の面会受付時間はアプリ内「面会」からご確認ください。', 'high', 1, '2026-01-07 06:31:19');

-- --------------------------------------------------------

--
-- テーブルの構造 `patient_registrations`
--

CREATE TABLE `patient_registrations` (
  `id` int(11) NOT NULL,
  `hospital_code` varchar(64) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `patient_registrations`
--

INSERT INTO `patient_registrations` (`id`, `hospital_code`, `user_agent`, `created_at`) VALUES
(1, 'tokyo-clinic', 'curl/8.7.1', '2026-01-07 04:35:25'),
(2, 'tokyo-clinic', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-01-07 04:49:03'),
(3, 'tokyo-clinic', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-01-07 05:30:24'),
(4, 'tokyo-clinic', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-01-07 05:30:25');

-- --------------------------------------------------------

--
-- テーブルの構造 `routes`
--

CREATE TABLE `routes` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `key` varchar(50) NOT NULL,
  `label` varchar(50) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 10,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `routes`
--

INSERT INTO `routes` (`id`, `hospital_id`, `key`, `label`, `phone`, `is_enabled`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'reservation', '予約', '0312345678', 1, 10, '2026-01-01 15:45:53', '2026-01-01 15:45:53'),
(2, 1, 'visit', '面会', '0399990000', 1, 20, '2026-01-01 15:45:53', '2026-01-01 15:45:53');

-- --------------------------------------------------------

--
-- テーブルの構造 `route_exceptions`
--

CREATE TABLE `route_exceptions` (
  `id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `route_exceptions`
--

INSERT INTO `route_exceptions` (`id`, `route_id`, `start_date`, `end_date`, `title`, `created_at`) VALUES
(1, 2, '2026-03-20', '2026-03-31', '年度末体制（テスト）', '2026-01-01 15:45:53');

-- --------------------------------------------------------

--
-- テーブルの構造 `route_exception_hours`
--

CREATE TABLE `route_exception_hours` (
  `id` int(11) NOT NULL,
  `exception_id` int(11) NOT NULL,
  `dow` tinyint(4) NOT NULL,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `route_exception_hours`
--

INSERT INTO `route_exception_hours` (`id`, `exception_id`, `dow`, `open_time`, `close_time`, `is_closed`) VALUES
(1, 1, 0, '14:00:00', '15:00:00', 0),
(2, 1, 1, '14:00:00', '15:00:00', 0),
(3, 1, 2, '14:00:00', '15:00:00', 0),
(4, 1, 3, '14:00:00', '15:00:00', 0),
(5, 1, 4, '14:00:00', '15:00:00', 0),
(6, 1, 5, NULL, NULL, 1),
(7, 1, 6, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- テーブルの構造 `route_weekly_hours`
--

CREATE TABLE `route_weekly_hours` (
  `id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `dow` tinyint(4) NOT NULL,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `route_weekly_hours`
--

INSERT INTO `route_weekly_hours` (`id`, `route_id`, `dow`, `open_time`, `close_time`, `is_closed`) VALUES
(1, 1, 0, '09:00:00', '17:00:00', 0),
(2, 1, 1, '09:00:00', '17:00:00', 0),
(3, 1, 2, '09:00:00', '17:00:00', 0),
(4, 1, 3, '09:00:00', '17:00:00', 0),
(5, 1, 4, '09:00:00', '17:00:00', 0),
(6, 1, 5, '09:00:00', '12:00:00', 0),
(7, 1, 6, NULL, NULL, 1),
(8, 2, 0, '13:00:00', '16:00:00', 0),
(9, 2, 1, '13:00:00', '16:00:00', 0),
(10, 2, 2, '13:00:00', '16:00:00', 0),
(11, 2, 3, '13:00:00', '16:00:00', 0),
(12, 2, 4, '13:00:00', '16:00:00', 0),
(13, 2, 5, NULL, NULL, 1),
(14, 2, 6, NULL, NULL, 1);

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `hospitals`
--
ALTER TABLE `hospitals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hospitals_code` (`hospital_code`);

--
-- テーブルのインデックス `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_news_hospital_id` (`hospital_id`),
  ADD KEY `idx_news_priority` (`priority`);

--
-- テーブルのインデックス `patient_registrations`
--
ALTER TABLE `patient_registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pr_hospital_code` (`hospital_code`);

--
-- テーブルのインデックス `routes`
--
ALTER TABLE `routes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_routes_hospital_key` (`hospital_id`,`key`),
  ADD KEY `idx_routes_hospital_id` (`hospital_id`);

--
-- テーブルのインデックス `route_exceptions`
--
ALTER TABLE `route_exceptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ex_route_id` (`route_id`),
  ADD KEY `idx_ex_date_range` (`start_date`,`end_date`);

--
-- テーブルのインデックス `route_exception_hours`
--
ALTER TABLE `route_exception_hours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ex_hours_exception_dow` (`exception_id`,`dow`),
  ADD KEY `idx_ex_hours_exception_id` (`exception_id`);

--
-- テーブルのインデックス `route_weekly_hours`
--
ALTER TABLE `route_weekly_hours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_weekly_route_dow` (`route_id`,`dow`),
  ADD KEY `idx_weekly_route_id` (`route_id`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `hospitals`
--
ALTER TABLE `hospitals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- テーブルの AUTO_INCREMENT `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- テーブルの AUTO_INCREMENT `patient_registrations`
--
ALTER TABLE `patient_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- テーブルの AUTO_INCREMENT `routes`
--
ALTER TABLE `routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- テーブルの AUTO_INCREMENT `route_exceptions`
--
ALTER TABLE `route_exceptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- テーブルの AUTO_INCREMENT `route_exception_hours`
--
ALTER TABLE `route_exception_hours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- テーブルの AUTO_INCREMENT `route_weekly_hours`
--
ALTER TABLE `route_weekly_hours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `news`
--
ALTER TABLE `news`
  ADD CONSTRAINT `fk_news_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `routes`
--
ALTER TABLE `routes`
  ADD CONSTRAINT `fk_routes_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `route_exceptions`
--
ALTER TABLE `route_exceptions`
  ADD CONSTRAINT `fk_ex_route` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `route_exception_hours`
--
ALTER TABLE `route_exception_hours`
  ADD CONSTRAINT `fk_ex_hours_exception` FOREIGN KEY (`exception_id`) REFERENCES `route_exceptions` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `route_weekly_hours`
--
ALTER TABLE `route_weekly_hours`
  ADD CONSTRAINT `fk_weekly_route` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
