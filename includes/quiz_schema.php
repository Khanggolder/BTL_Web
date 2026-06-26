<?php
function ensure_quiz_schema(PDO $pdo) {
    $columns = $pdo->query("SHOW COLUMNS FROM lesson_progress LIKE 'video_completed'")->fetchAll();
    if (!$columns) {
        $pdo->exec("ALTER TABLE lesson_progress ADD COLUMN video_completed tinyint(1) NOT NULL DEFAULT 0 AFTER completed");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS quizzes (id int NOT NULL AUTO_INCREMENT, lesson_id int NOT NULL, title varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL, description text COLLATE utf8mb4_unicode_ci, pass_score int NOT NULL DEFAULT 70, active tinyint(1) NOT NULL DEFAULT 1, created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP, updated_at timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY lesson_id (lesson_id), CONSTRAINT quizzes_lesson_fk FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_questions (id int NOT NULL AUTO_INCREMENT, quiz_id int NOT NULL, question_text text COLLATE utf8mb4_unicode_ci NOT NULL, order_index int NOT NULL DEFAULT 1, created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY quiz_id (quiz_id), CONSTRAINT quiz_questions_quiz_fk FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_options (id int NOT NULL AUTO_INCREMENT, question_id int NOT NULL, option_text text COLLATE utf8mb4_unicode_ci NOT NULL, is_correct tinyint(1) NOT NULL DEFAULT 0, order_index int NOT NULL DEFAULT 1, PRIMARY KEY (id), KEY question_id (question_id), CONSTRAINT quiz_options_question_fk FOREIGN KEY (question_id) REFERENCES quiz_questions (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_attempts (id int NOT NULL AUTO_INCREMENT, quiz_id int NOT NULL, user_id int NOT NULL, score decimal(5,2) NOT NULL DEFAULT 0.00, correct_count int NOT NULL DEFAULT 0, total_questions int NOT NULL DEFAULT 0, passed tinyint(1) NOT NULL DEFAULT 0, created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY quiz_id (quiz_id), KEY user_id (user_id), CONSTRAINT quiz_attempts_quiz_fk FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE, CONSTRAINT quiz_attempts_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_attempt_answers (id int NOT NULL AUTO_INCREMENT, attempt_id int NOT NULL, question_id int NOT NULL, option_id int DEFAULT NULL, is_correct tinyint(1) NOT NULL DEFAULT 0, PRIMARY KEY (id), KEY attempt_id (attempt_id), KEY question_id (question_id), KEY option_id (option_id), CONSTRAINT quiz_attempt_answers_attempt_fk FOREIGN KEY (attempt_id) REFERENCES quiz_attempts (id) ON DELETE CASCADE, CONSTRAINT quiz_attempt_answers_option_fk FOREIGN KEY (option_id) REFERENCES quiz_options (id) ON DELETE SET NULL, CONSTRAINT quiz_attempt_answers_question_fk FOREIGN KEY (question_id) REFERENCES quiz_questions (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    seed_lesson_quiz($pdo, 1, 'Kiểm tra nhanh sau bài tổng quan Web', 'Hoàn thành quiz để mở bài học tiếp theo.', [
        ['HTML thường được dùng để làm gì trong một trang web?', ['Tạo cấu trúc và nội dung cho trang web', 'Quản lý cơ sở dữ liệu', 'Biên dịch ứng dụng di động', 'Thiết kế hệ điều hành'], 0],
        ['CSS có vai trò chính nào?', ['Tạo màu sắc, bố cục và phong cách hiển thị', 'Lưu mật khẩu người dùng', 'Chạy truy vấn SQL', 'Quản trị máy chủ'], 0],
        ['JavaScript giúp trang web có thêm khả năng gì?', ['Tương tác động như xử lý sự kiện và cập nhật giao diện', 'Thay thế hoàn toàn HTML', 'Tạo địa chỉ IP cho website', 'Nén toàn bộ database'], 0],
    ]);
    seed_lesson_quiz($pdo, 5, 'Kiểm tra nhanh sau bài Giới thiệu PHP và XAMPP', 'Quiz demo cho khóa Lập trình PHP & MySQL từ cơ bản đến nâng cao.', [
        ['PHP chủ yếu chạy ở đâu trong mô hình web truyền thống?', ['Trên máy chủ để xử lý logic trước khi trả HTML về trình duyệt', 'Chỉ trong trình duyệt như CSS', 'Trong card đồ họa của người dùng', 'Chỉ trong hệ điều hành Android'], 0],
        ['XAMPP thường được dùng để làm gì khi học PHP?', ['Tạo môi trường localhost gồm Apache, PHP và MySQL', 'Thiết kế logo tự động', 'Tăng tốc mạng Wi-Fi', 'Biên dịch file CSS'], 0],
        ['MySQL trong khóa học PHP thường đảm nhiệm vai trò nào?', ['Lưu trữ và truy vấn dữ liệu cho website', 'Tạo hiệu ứng hover cho nút bấm', 'Chạy animation trên trình duyệt', 'Tạo font chữ cho website'], 0],
    ]);
}

function seed_lesson_quiz(PDO $pdo, $lesson_id, $title, $description, array $questions) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE id = ?");
    $stmt->execute([$lesson_id]);
    if ((int) $stmt->fetchColumn() === 0) return;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE lesson_id = ?");
    $stmt->execute([$lesson_id]);
    if ((int) $stmt->fetchColumn() > 0) return;

    $stmt = $pdo->prepare("INSERT INTO quizzes (lesson_id, title, description, pass_score, active) VALUES (?, ?, ?, 70, 1)");
    $stmt->execute([$lesson_id, $title, $description]);
    $quiz_id = (int) $pdo->lastInsertId();

    $question_stmt = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question_text, order_index) VALUES (?, ?, ?)");
    $option_stmt = $pdo->prepare("INSERT INTO quiz_options (question_id, option_text, is_correct, order_index) VALUES (?, ?, ?, ?)");
    foreach ($questions as $question_index => $question) {
        $question_stmt->execute([$quiz_id, $question[0], $question_index + 1]);
        $question_id = (int) $pdo->lastInsertId();
        foreach ($question[1] as $option_index => $option_text) {
            $option_stmt->execute([$question_id, $option_text, $option_index === $question[2] ? 1 : 0, $option_index + 1]);
        }
    }
}
?>