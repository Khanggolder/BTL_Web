
CREATE DATABASE IF NOT EXISTS elearning_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE elearning_db;


CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    avatar TEXT DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT NOT NULL,
    role VARCHAR(50) NOT NULL,
    PRIMARY KEY (user_id, role),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    thumbnail TEXT DEFAULT NULL,
    price DECIMAL(10, 2) NOT NULL,
    discount_price DECIMAL(10, 2) DEFAULT NULL,
    level VARCHAR(50) NOT NULL,
    category VARCHAR(100) NOT NULL,
    instructor VARCHAR(100) DEFAULT NULL,
    duration INT DEFAULT NULL,
    total_lectures INT DEFAULT NULL,
    published TINYINT(1) DEFAULT 0,
    enrollment_count INT DEFAULT 0,
    rating DOUBLE DEFAULT 0.0,
    rating_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    video_url TEXT DEFAULT NULL,
    duration INT DEFAULT NULL,
    order_index INT NOT NULL,
    is_free TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    progress DOUBLE DEFAULT 0.0,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    UNIQUE KEY (user_id, course_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    total_price DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    course_id INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (cart_id, course_id),
    FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(100) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status VARCHAR(50) DEFAULT 'PENDING',
    payment_method VARCHAR(50) NOT NULL,
    payment_transaction_id VARCHAR(255) DEFAULT NULL,
    momo_order_id VARCHAR(255) DEFAULT NULL,
    momo_request_id VARCHAR(255) DEFAULT NULL,
    momo_transaction_id VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    course_id INT NOT NULL,
    course_name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    original_price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;









INSERT INTO users (id, email, password, full_name, phone, avatar, active) VALUES
(1, 'admin@learnhub.com', '$2y$10$Vn3kFsq9D.MU4gNTTx6JXeeczj12KPp244TG8avqgLUnpS3X5UGf2', 'Nguyá»…n Quáº£n Trá»‹', '0912345678', 'https://api.dicebear.com/7.x/adventurer/svg?seed=admin', 1),
(2, 'student@learnhub.com', '$2y$10$Vn3kFsq9D.MU4gNTTx6JXeeczj12KPp244TG8avqgLUnpS3X5UGf2', 'Tráº§n Há»c ViÃªn', '0987654321', 'https://api.dicebear.com/7.x/adventurer/svg?seed=student', 1);


INSERT INTO user_roles (user_id, role) VALUES
(1, 'ROLE_ADMIN'),
(1, 'ROLE_USER'),
(2, 'ROLE_USER');


INSERT INTO courses (id, title, slug, description, thumbnail, price, discount_price, level, category, instructor, duration, total_lectures, published, enrollment_count, rating, rating_count) VALUES
(1, 'Láº­p trÃ¬nh Web toÃ n diá»‡n vá»›i HTML, CSS vÃ  JavaScript', 'lap-trinh-web-toan-dien-html-css-javascript', 'KhÃ³a há»c nÃ y sáº½ Ä‘Æ°a báº¡n tá»« con sá»‘ 0 trá»Ÿ thÃ nh má»™t nhÃ  phÃ¡t triá»ƒn Front-End thá»±c thá»¥. Báº¡n sáº½ há»c cÃ¡ch xÃ¢y dá»±ng cÃ¡c giao diá»‡n web tuyá»‡t Ä‘áº¹p, tÆ°Æ¡ng tÃ¡c máº¡nh máº½ vÃ  tÆ°Æ¡ng thÃ­ch má»i thiáº¿t bá»‹ di Ä‘á»™ng.', 'https://images.unsplash.com/photo-1547658719-da2b51169166?w=600', 990000.00, 499000.00, 'BEGINNER', 'Web Development', 'KhÃ¡nh Nguyá»…n', 1200, 15, 1, 142, 4.8, 32),
(2, 'Láº­p trÃ¬nh PHP & MySQL tá»« cÆ¡ báº£n Ä‘áº¿n nÃ¢ng cao', 'lap-trinh-php-mysql-co-ban-nang-cao', 'LÃ m chá»§ ngÃ´n ngá»¯ láº­p trÃ¬nh Back-End phá»• biáº¿n nháº¥t tháº¿ giá»›i. Tá»± tay thiáº¿t káº¿ vÃ  váº­n hÃ nh há»‡ thá»‘ng CÆ¡ sá»Ÿ dá»¯ liá»‡u MySQL báº£o máº­t cao, xÃ¢y dá»±ng dá»± Ã¡n website bÃ¡n hÃ ng thá»±c táº¿.', 'https://images.unsplash.com/photo-1599507593499-a3f7f7d9a666?w=600', 1200000.00, 799000.00, 'INTERMEDIATE', 'Web Development', 'HoÃ ng LÃ¢m', 1500, 18, 1, 98, 4.7, 24),
(3, 'LÃ m chá»§ ReactJS vÃ  Spring Boot trong 30 ngÃ y', 'lam-chu-reactjs-spring-boot-30-ngay', 'KhÃ³a há»c Full-Stack cao cáº¥p káº¿t há»£p thÆ° viá»‡n UI hÃ ng Ä‘áº§u ReactJS cÃ¹ng vá»›i Framework backend Spring Boot cá»§a Java. PhÃ¹ há»£p cho nhá»¯ng ai muá»‘n thÄƒng tiáº¿n thu nháº­p lÃªn ngÃ n Ä‘Ã´.', 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=600', 2500000.00, 1890000.00, 'ADVANCED', 'Web Development', 'TÃ¹ng SÆ¡n', 2100, 25, 1, 64, 4.9, 15);


INSERT INTO lessons (id, course_id, title, description, video_url, duration, order_index, is_free) VALUES
(1, 1, 'BÃ i 1: Tá»•ng quan vá» tháº¿ giá»›i láº­p trÃ¬nh Web', 'ChÃ o má»«ng báº¡n Ä‘áº¿n vá»›i khÃ³a há»c. BÃ i há»c nÃ y sáº½ giá»›i thiá»‡u cho báº¡n Ä‘á»‹nh hÆ°á»›ng trá»Ÿ thÃ nh Web Developer.', 'https://www.w3schools.com/html/mov_bbb.mp4', 15, 1, 1),
(2, 1, 'BÃ i 2: HTML5 lÃ  gÃ¬? CÃ¡c tháº» cÆ¡ báº£n cáº§n biáº¿t', 'HÆ°á»›ng dáº«n chi tiáº¿t cÃ¡ch táº¡o trang HTML Ä‘áº§u tiÃªn vÃ  cÃ¡ch sá»­ dá»¥ng cÃ¡c tháº» div, p, headings, a, img.', 'https://www.w3schools.com/html/mov_bbb.mp4', 25, 2, 1),
(3, 1, 'BÃ i 3: Táº¡o kiá»ƒu cho trang web báº±ng CSS3', 'Há»c cÃ¡ch Ä‘á»•i mÃ u sáº¯c, Ä‘iá»u chá»‰nh font chá»¯, kÃ­ch thÆ°á»›c, khoáº£ng cÃ¡ch margin vÃ  padding trong CSS.', 'https://www.w3schools.com/html/mov_bbb.mp4', 35, 3, 0),
(4, 1, 'BÃ i 4: Bá»‘ cá»¥c Layout nÃ¢ng cao vá»›i Flexbox', 'Sá»­ dá»¥ng Flexbox Ä‘á»ƒ dÃ n trang responsive má»™t cÃ¡ch dá»… dÃ ng vÃ  nhanh chÃ³ng.', 'https://www.w3schools.com/html/mov_bbb.mp4', 40, 4, 0);


INSERT INTO lessons (id, course_id, title, description, video_url, duration, order_index, is_free) VALUES
(5, 2, 'BÃ i 1: Giá»›i thiá»‡u vá» PHP vÃ  XAMPP', 'CÃ¡ch cÃ i Ä‘áº·t XAMPP Ä‘á»ƒ cháº¡y localhost phá»¥c vá»¥ cho viá»‡c há»c vÃ  láº­p trÃ¬nh PHP trÃªn mÃ¡y tÃ­nh cÃ¡ nhÃ¢n.', 'https://www.w3schools.com/html/movie.mp4', 20, 1, 1),
(6, 2, 'BÃ i 2: CÃº phÃ¡p PHP cÆ¡ báº£n vÃ  Kiá»ƒu dá»¯ liá»‡u', 'LÃ m quen vá»›i biáº¿n, háº±ng, cÃ¢u Ä‘iá»u kiá»‡n if-else, vÃ²ng láº·p for vÃ  while trong PHP.', 'https://www.w3schools.com/html/movie.mp4', 30, 2, 0),
(7, 2, 'BÃ i 3: Káº¿t ná»‘i cÆ¡ sá»Ÿ dá»¯ liá»‡u MySQL báº±ng PDO', 'CÃ¡ch káº¿t ná»‘i an toÃ n báº£o máº­t, chá»‘ng SQL Injection vá»›i PDO cá»§a PHP.', 'https://www.w3schools.com/html/movie.mp4', 45, 3, 0);


INSERT INTO lessons (id, course_id, title, description, video_url, duration, order_index, is_free) VALUES
(8, 3, 'BÃ i 1: Táº¡i sao láº¡i lÃ  ReactJS & Spring Boot?', 'PhÃ¢n tÃ­ch kiáº¿n trÃºc Single Page Application (SPA) káº¿t há»£p RESTful API.', 'https://www.w3schools.com/html/mov_bbb.mp4', 22, 1, 1),
(9, 3, 'BÃ i 2: Khá»Ÿi táº¡o Project Spring Boot Ä‘áº§u tiÃªn', 'HÆ°á»›ng dáº«n sá»­ dá»¥ng Spring Initializr Ä‘á»ƒ táº¡o má»™t á»©ng dá»¥ng Maven Boot.', 'https://www.w3schools.com/html/mov_bbb.mp4', 35, 2, 0);
