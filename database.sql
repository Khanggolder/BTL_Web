SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `elearning_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `elearning_db`;

DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `carts`;
DROP TABLE IF EXISTS `enrollments`;
DROP TABLE IF EXISTS `lessons`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `user_roles`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` text COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_roles` (
  `user_id` int NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`user_id`,`role`),
  CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `thumbnail` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) NOT NULL,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `level` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `instructor` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` int DEFAULT NULL,
  `total_lectures` int DEFAULT NULL,
  `published` tinyint(1) DEFAULT '0',
  `enrollment_count` int DEFAULT '0',
  `rating` double DEFAULT '0',
  `rating_count` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lessons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `video_url` text COLLATE utf8mb4_unicode_ci,
  `duration` int DEFAULT NULL,
  `order_index` int NOT NULL,
  `is_free` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `enrollments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `course_id` int NOT NULL,
  `enrolled_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `progress` double DEFAULT '0',
  `completed_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`,`course_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `carts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `total_price` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cart_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cart_id` int NOT NULL,
  `course_id` int NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cart_id` (`cart_id`,`course_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `momo_order_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `momo_request_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `momo_transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `course_id` int NOT NULL,
  `course_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `original_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `phone`, `avatar`, `active`) VALUES
(1, 'admin@learnhub.com', '$2y$10$Vn3kFsq9D.MU4gNTTx6JXeeczj12KPp244TG8avqgLUnpS3X5UGf2', 'Nguyễn Quản Trị', '0912345678', 'https://api.dicebear.com/7.x/adventurer/svg?seed=admin', 1),
(2, 'student@learnhub.com', '$2y$10$Vn3kFsq9D.MU4gNTTx6JXeeczj12KPp244TG8avqgLUnpS3X5UGf2', 'Trần Học Viên', '0987654321', 'https://api.dicebear.com/7.x/adventurer/svg?seed=student', 1);

INSERT INTO `user_roles` (`user_id`, `role`) VALUES
(1, 'ROLE_ADMIN'),
(1, 'ROLE_USER'),
(2, 'ROLE_USER');

INSERT INTO `courses` (`id`, `title`, `slug`, `description`, `thumbnail`, `price`, `discount_price`, `level`, `category`, `instructor`, `duration`, `total_lectures`, `published`, `enrollment_count`, `rating`, `rating_count`, `created_at`, `updated_at`) VALUES
(1, 'Lập trình Web toàn diện với HTML, CSS và JavaScript', 'lap-trinh-web-toan-dien-voi-html-css-va-javascript', 'Khóa học này sẽ đưa bạn từ con số 0 trở thành một nhà phát triển Front-End thực thụ. Bạn sẽ học cách xây dựng các giao diện web tuyệt đẹp, tương tác mạnh mẽ và tương thích mọi thiết bị di động.', 'https://images.unsplash.com/photo-1547658719-da2b51169166?w=600', '129000.00', '99000.00', 'BEGINNER', 'Web Development', 'Khánh Nguyễn', 1200, 15, 1, 1, 4.8, 32, '2026-05-31 08:00:17', '2026-06-12 05:02:28'),
(2, 'Lập trình PHP & MySQL từ cơ bản đến nâng cao', 'lap-trinh-php-mysql-tu-co-ban-den-nang-cao', 'Làm chủ ngôn ngữ lập trình Back-End phổ biến nhất thế giới. Tự tay thiết kế và vận hành hệ thống Cơ sở dữ liệu MySQL bảo mật cao, xây dựng dự án website bán hàng thực tế.', 'data:image/png;base64,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', '120000.00', '100000.00', 'INTERMEDIATE', 'Web Development', 'Hoàng Lâm', 1500, 18, 1, 1, 4.7, 24, '2026-05-31 08:00:17', '2026-06-13 13:11:27'),
(3, 'Làm chủ ReactJS và Spring Boot trong 30 ngày', 'lam-chu-reactjs-va-spring-boot-trong-30-ngay', 'Khóa học Full-Stack cao cấp kết hợp thư viện UI hàng đầu ReactJS cùng với Framework backend Spring Boot của Java. Phù hợp cho những ai muốn thăng tiến thu nhập lên ngàn đô.', 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=600', '250000.00', '200000.00', 'ADVANCED', 'Web Development', 'Tùng Sơn', 2100, 25, 1, 0, 4.9, 15, '2026-05-31 08:00:17', '2026-06-13 14:18:44');

INSERT INTO `lessons` (`id`, `course_id`, `title`, `description`, `video_url`, `duration`, `order_index`, `is_free`, `created_at`, `updated_at`) VALUES
(1, 1, 'Bài 1: Tổng quan về thế giới lập trình Web', 'Chào mừng bạn đến với khóa học. Bài học này sẽ giới thiệu cho bạn định hướng trở thành Web Developer.', 'https://www.w3schools.com/html/mov_bbb.mp4', 15, 1, 1, '2026-05-31 08:00:17', '2026-05-31 08:00:17'),
(2, 1, 'Bài 2: HTML5 là gì? Các thẻ cơ bản cần biết', 'Hướng dẫn chi tiết cách tạo trang HTML đầu tiên và cách sử dụng các thẻ div, p, headings, a, img.', 'https://www.w3schools.com/html/mov_bbb.mp4', 25, 2, 0, '2026-05-31 08:00:17', '2026-06-13 04:49:48'),
(3, 1, 'Bài 3: Tạo kiểu cho trang web bằng CSS3', 'Học cách đổi màu sắc, điều chỉnh font chữ, kích thước, khoảng cách margin và padding trong CSS.', 'https://www.w3schools.com/html/mov_bbb.mp4', 35, 3, 0, '2026-05-31 08:00:17', '2026-05-31 08:00:17'),
(4, 1, 'Bài 4: Bố cục Layout nâng cao với Flexbox', 'Sử dụng Flexbox để dàn trang responsive một cách dễ dàng và nhanh chóng.', 'https://www.w3schools.com/html/mov_bbb.mp4', 40, 4, 0, '2026-05-31 08:00:17', '2026-05-31 08:00:17'),
(5, 2, 'Bài 1: Giới thiệu về PHP và XAMPP', 'Cách cài đặt XAMPP để chạy localhost phục vụ cho việc học và lập trình PHP trên máy tính cá nhân.', 'https://www.w3schools.com/html/movie.mp4', 20, 1, 1, '2026-05-31 08:00:17', '2026-05-31 08:00:17'),
(6, 2, 'Bài 2: Cú pháp PHP cơ bản và Kiểu dữ liệu', 'Làm quen với biến, hằng, câu điều kiện if-else, vòng lặp for và while trong PHP.', 'https://www.w3schools.com/html/movie.mp4', 30, 2, 0, '2026-05-31 08:00:17', '2026-05-31 08:00:17'),
(7, 2, 'Bài 3: Kết nối cơ sở dữ liệu MySQL bằng PDO', 'Cách kết nối an toàn bảo mật, chống SQL Injection với PDO của PHP.', 'https://www.w3schools.com/html/movie.mp4', 45, 3, 0, '2026-05-31 08:00:17', '2026-05-31 08:00:17'),
(8, 3, 'Bài 1: Tại sao lại là ReactJS & Spring Boot?', 'Phân tích kiến trúc Single Page Application (SPA) kết hợp RESTful API.', 'https://www.w3schools.com/html/mov_bbb.mp4', 22, 1, 1, '2026-05-31 08:00:17', '2026-05-31 08:00:17'),
(9, 3, 'Bài 2: Khởi tạo Project Spring Boot đầu tiên', 'Hướng dẫn sử dụng Spring Initializr để tạo một ứng dụng Maven Boot.', 'https://www.w3schools.com/html/mov_bbb.mp4', 35, 2, 0, '2026-05-31 08:00:17', '2026-05-31 08:00:17');

SET FOREIGN_KEY_CHECKS = 1;
