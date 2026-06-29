# LearnHub

LearnHub là website bán khóa học và học trực tuyến được xây dựng bằng PHP thuần, MySQL, HTML, CSS và JavaScript. Dự án có hai phần chính: giao diện người dùng để xem/mua/học khóa học và trang quản trị để quản lý dữ liệu hệ thống.

## Tổng quan chức năng

### Phía người dùng

- Đăng ký, đăng nhập, đăng xuất tài khoản.
- Xem trang chủ, trang giới thiệu, liên hệ, FAQ, điều khoản và chính sách.
- Xem danh sách khóa học đã xuất bản.
- Tìm kiếm, lọc theo danh mục/cấp độ và sắp xếp khóa học.
- Xem chi tiết khóa học, giá, giảm giá, giảng viên, curriculum và bài học thử.
- Thêm khóa học vào giỏ hàng, mua ngay và checkout.
- Thanh toán bằng chuyển khoản thủ công, MoMo Sandbox hoặc các cổng mô phỏng PayPal/VNPay/thẻ.
- Xem lịch sử đơn hàng và trạng thái đơn.
- Xem/cập nhật hồ sơ cá nhân, avatar URL và thông tin liên hệ.
- Vào học sau khi đã có quyền học trong bảng `enrollments`.
- Học video từ URL MP4, YouTube hoặc Google Drive preview.
- Lưu tiến độ học, vị trí video, trạng thái xem video và trạng thái hoàn thành bài.
- Làm quiz trắc nghiệm theo từng bài học.
- Xem kết quả quiz, làm lại quiz và đồng bộ trạng thái hoàn thành bài học.
- Ghi chú theo bài học và vị trí video.

### Phía quản trị

- Dashboard thống kê tổng quan khóa học, học viên, đơn hàng và doanh thu.
- Quản lý khóa học: thêm, xem, sửa, xóa, tìm kiếm và xóa hàng loạt.
- Quản lý bài học: thêm, xem, sửa, xóa, lọc theo khóa học, video, học thử và thứ tự bài.
- Quản lý quiz ngay trong bài học: tạo quiz, sửa quiz, xóa quiz, thêm/sửa/xóa câu hỏi và đáp án.
- Trang xem chi tiết bài học có preview video, thông tin bài học và phần xem quiz nếu có.
- Quản lý đơn hàng: tạo/sửa/xóa, duyệt, hủy, lọc trạng thái và đồng bộ quyền học.
- Quản lý người dùng: thêm/sửa/xóa, khóa/kích hoạt tài khoản, cấp/thu hồi quyền admin và quyền học.
- Các trang admin đều kiểm tra quyền `ROLE_ADMIN`.

## Tài khoản test: 

- Email: student@learnhub.com
- Mật khẩu: 123456

## Công nghệ sử dụng

- Backend: PHP thuần theo kiểu page-controller.
- Database: MySQL/MariaDB, kết nối bằng PDO.
- Frontend: HTML5, CSS3, JavaScript thuần.
- Giao diện: CSS Grid, Flexbox, media query responsive.
- Icon: Lucide Icons qua CDN.
- Font: Plus Jakarta Sans qua Google Fonts.
- Thanh toán: chuyển khoản thủ công, MoMo Sandbox và cổng mô phỏng.
- Video: URL trực tiếp, YouTube, Google Drive preview.

## Yêu cầu môi trường

- XAMPP hoặc môi trường tương đương có Apache, PHP và MySQL/MariaDB.
- PHP 8.0 trở lên.
- MySQL hoặc MariaDB.
- Extension PHP nên có:
  - `pdo_mysql`
  - `curl`
  - `mbstring`

## Cài đặt và chạy dự án

1. Đặt thư mục dự án tại:

```text
C:\xampp\htdocs\BTL_Web
```

2. Bật Apache và MySQL trong XAMPP.

3. Tạo database nếu chưa có:

```sql
CREATE DATABASE elearning_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

4. Import file:

```text
database.sql
```

File `database.sql` chứa schema và dữ liệu mẫu/demo cho hệ thống.

5. Tạo file `.env` từ `.env.example` và điền cấu hình phù hợp:

```env
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=elearning_db

MOMO_ENDPOINT=https://test-payment.momo.vn/v2/gateway/api/create
MOMO_PARTNER_CODE=your_momo_partner_code
MOMO_ACCESS_KEY=your_momo_access_key
MOMO_SECRET_KEY=your_momo_secret_key
```

6. Truy cập website:

```text
http://localhost/BTL_Web/
```

Nếu Apache chạy port khác, ví dụ `8080`, truy cập:

```text
http://localhost:8080/BTL_Web/
```

## Cấu trúc thư mục

```text
BTL_Web/
├── admin/                 Trang quản trị
├── assets/
│   ├── css/               CSS giao diện người dùng và admin
│   ├── js/                JavaScript admin menu/confirm
│   └── images/            Ảnh giao diện đang dùng, ví dụ login.webp
├── config/                Đọc .env và kết nối database
├── includes/              Header, footer, auth, quiz schema, video helper
├── database.sql           Schema và dữ liệu mẫu
├── WEB_CONTEXT.md         Tài liệu phân tích chi tiết source hiện tại
├── scripts.txt            Kịch bản demo/thuyết trình
├── README.md              Tài liệu hướng dẫn dự án
├── .env.example           Mẫu biến môi trường
└── .gitignore             Loại trừ file môi trường và file tạm
```

## Các file chính

### Người dùng

| File | Vai trò |
| --- | --- |
| `index.php` | Trang chủ, hiển thị hero và khóa học nổi bật. |
| `courses.php` | Danh sách khóa học, tìm kiếm/lọc/sắp xếp/phân trang. |
| `course-detail.php` | Chi tiết khóa học, curriculum, học thử, thêm giỏ/mua ngay. |
| `cart.php` | Giỏ hàng, thêm/xóa khóa học, mua ngay. |
| `checkout.php` | Tạo đơn hàng và điều hướng thanh toán. |
| `order-success.php` | Trang kết quả đặt hàng/thanh toán. |
| `orders.php` | Lịch sử đơn hàng của người dùng. |
| `profile.php` | Hồ sơ cá nhân, khóa học đã mua và đơn hàng. |
| `learning.php` | Học video, quiz, ghi chú và tiến độ học. |
| `login.php` | Đăng nhập và nạp role vào session. |
| `signup.php` | Đăng ký tài khoản và tạo role user. |
| `logout.php` | Đăng xuất. |
| `momo_payment.php` | Tạo giao dịch MoMo Sandbox. |
| `momo_callback.php` | Xử lý callback MoMo. |
| `gateway_payment.php` | Cổng thanh toán mô phỏng. |
| `about.php` | Trang giới thiệu LearnHub. |
| `contact.php` | Trang liên hệ, thông tin hỗ trợ và quy trình hỗ trợ. |

### Admin

| File | Vai trò |
| --- | --- |
| `admin/dashboard.php` | Dashboard thống kê và xử lý nhanh đơn hàng. |
| `admin/courses.php` | CRUD khóa học, tìm kiếm và xóa hàng loạt. |
| `admin/lessons.php` | CRUD bài học, preview video và quản lý quiz trong bài học. |
| `admin/orders.php` | CRUD đơn hàng, duyệt/hủy đơn và đồng bộ enrollment. |
| `admin/users.php` | CRUD user, role, trạng thái tài khoản và quyền học. |

### Dùng chung

| File | Vai trò |
| --- | --- |
| `config/env.php` | Đọc biến môi trường từ `.env`. |
| `config/db.php` | Kết nối PDO và cấu hình timezone. |
| `includes/auth_check.php` | Kiểm tra đăng nhập, role và quyền admin. |
| `includes/header.php` | Header người dùng, menu, tìm kiếm, giỏ hàng và user dropdown. |
| `includes/footer.php` | Footer và script giao diện chung. |
| `includes/video_helper.php` | Helper xử lý YouTube/Google Drive video URL. |
| `includes/quiz_schema.php` | Đảm bảo các bảng quiz tồn tại khi cần. |

## Database

Database chính: `elearning_db`.

File `database.sql` hiện có 16 bảng:

- `users`
- `user_roles`
- `courses`
- `lessons`
- `enrollments`
- `lesson_progress`
- `lesson_notes`
- `quizzes`
- `quiz_questions`
- `quiz_options`
- `quiz_attempts`
- `quiz_attempt_answers`
- `carts`
- `cart_items`
- `orders`
- `order_items`

Các nhóm dữ liệu chính:

- User và phân quyền: `users`, `user_roles`.
- Khóa học và bài học: `courses`, `lessons`, `enrollments`.
- Tiến độ học: `lesson_progress`, `lesson_notes`.
- Quiz: `quizzes`, `quiz_questions`, `quiz_options`, `quiz_attempts`, `quiz_attempt_answers`.
- Giỏ hàng và đơn hàng: `carts`, `cart_items`, `orders`, `order_items`.

## Phân quyền

Dự án sử dụng role trong bảng `user_roles`:

- `ROLE_USER`: người dùng thường.
- `ROLE_ADMIN`: quản trị viên.

Muốn cấp quyền admin cho một user, thêm role vào database:

```sql
INSERT INTO user_roles (user_id, role)
VALUES (USER_ID_CAN_CAP_QUYEN, 'ROLE_ADMIN');
```

Sau khi đổi quyền, user nên đăng xuất và đăng nhập lại để session cập nhật role.

## Luồng học trực tuyến

1. User đăng nhập.
2. User mua khóa học hoặc được admin cấp quyền học.
3. Hệ thống tạo bản ghi trong `enrollments`.
4. User vào `learning.php` để học.
5. Hệ thống kiểm tra quyền học theo `user_id` và `course_id`.
6. User xem video, lưu vị trí video và trạng thái đã xem.
7. Nếu bài học có quiz active, user cần làm quiz đạt điểm yêu cầu để hoàn thành bài.
8. User có thể tạo ghi chú theo bài học và vị trí video.
9. Tiến độ khóa học được tính từ `lesson_progress`.

## Quiz bài học

Quiz được gắn với từng bài học trong `admin/lessons.php`.

Admin có thể:

- Tạo quiz khi thêm hoặc sửa bài học.
- Bật/tắt quiz.
- Cấu hình điểm đạt.
- Thêm/sửa/xóa câu hỏi.
- Thêm/sửa/xóa đáp án và chọn đáp án đúng.
- Xem quiz ngay trong trang chi tiết bài học.

User có thể:

- Làm quiz trong trang học.
- Xem kết quả sau khi nộp.
- Làm lại quiz.
- Hoàn thành bài học khi đạt điểm yêu cầu.

## Thanh toán

Dự án hỗ trợ các luồng thanh toán sau:

- Chuyển khoản thủ công: đơn ở trạng thái chờ, admin duyệt để mở quyền học.
- MoMo Sandbox: gọi API tạo giao dịch và xử lý callback.
- PayPal/VNPay/Card mô phỏng: dùng để demo luồng thanh toán thành công.

Các biến MoMo được đọc từ `.env`, không hard-code credential trong source.

## Responsive

Dự án có responsive cho cả trang người dùng và admin:

- Header người dùng chuyển sang menu mobile ở màn hình nhỏ.
- Form login/signup tối ưu mobile.
- Trang khóa học, chi tiết khóa học, learning và contact có layout co giãn.
- Admin có sidebar mobile thông qua `assets/js/admin-menu.js`.
- Các bảng admin được chỉnh theo chiều rộng `%`, hạn chế vỡ layout ở zoom trình duyệt 100%.

## Ghi chú quan trọng khi demo

- Nên chạy trên Chrome ở zoom 100% để kiểm tra layout thật.
- Nếu dùng MoMo Sandbox, cần credential hợp lệ và kết nối mạng ổn định.
- Nếu không muốn phụ thuộc cổng thanh toán online, có thể demo chuyển khoản thủ công hoặc cổng mô phỏng.
- Khóa học nổi bật ở trang chủ dựa trên `courses.published = 1`, sắp xếp theo `enrollment_count` giảm dần và giới hạn 3 khóa.
- Trang học chỉ mở khi user có enrollment active.
- Bài học có quiz cần đạt điểm yêu cầu để hoàn thành hợp lệ.
- Chi tiết phân tích đầy đủ hơn nằm trong `WEB_CONTEXT.md`.
- Kịch bản demo/thuyết trình nằm trong `scripts.txt`.

## Kiểm tra nhanh sau khi chỉnh sửa

Có thể kiểm tra cú pháp PHP bằng XAMPP PHP:

```powershell
C:\xampp\php\php.exe -l index.php
C:\xampp\php\php.exe -l learning.php
C:\xampp\php\php.exe -l admin\courses.php
C:\xampp\php\php.exe -l admin\lessons.php
```

Hoặc kiểm tra toàn bộ file PHP bằng PowerShell:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
```
