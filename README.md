# LearnHub

LearnHub là website học trực tuyến viết bằng PHP thuần, MySQL và giao diện HTML/CSS/JavaScript. Dự án có trang người dùng để xem khóa học, mua khóa học, học bài giảng, quản lý hồ sơ; đồng thời có trang quản trị để quản lý nội dung website.

## Chức năng chính

- Đăng ký, đăng nhập, đăng xuất người dùng.
- Xem danh sách khóa học, chi tiết khóa học và bài giảng.
- Giỏ hàng, đặt hàng và thanh toán.
- Hỗ trợ thanh toán chuyển khoản ngân hàng và MoMo Sandbox.
- Trang hồ sơ cá nhân, khóa học đã sở hữu và đơn hàng đã mua.
- Trang quản trị cho khóa học, bài giảng, đơn hàng và người dùng.
- Tìm kiếm, lọc dữ liệu và thao tác nhiều mục trong trang quản trị.
- Giao diện có hỗ trợ desktop, tablet và mobile.

## Yêu cầu môi trường

- XAMPP hoặc môi trường tương đương có Apache, PHP và MySQL.
- PHP 8.0 trở lên.
- MySQL hoặc MariaDB.
- Extension PHP cần có: `pdo_mysql`, `curl`.

## Cài đặt

1. Đặt thư mục dự án vào:

```text
C:\xampp\htdocs\BTL_Web
```

2. Khởi động Apache và MySQL trong XAMPP.

3. Tạo database trong phpMyAdmin:

```sql
CREATE DATABASE elearning_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

4. Import file:

```text
database.sql
```

5. Tạo file `.env` từ file mẫu `.env.example`, sau đó điền thông tin thật:

```env
DB_HOST=localhost
DB_USER=root
DB_PASS=your_database_password
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

Nếu bạn đang chạy qua port khác, ví dụ `8080`, dùng:

```text
http://localhost:8080/BTL_Web/
```

## Cấu hình thanh toán MoMo

Dự án dùng MoMo Sandbox. Các khóa cấu hình không đặt trực tiếp trong code mà đọc từ `.env`:

- `MOMO_ENDPOINT`
- `MOMO_PARTNER_CODE`
- `MOMO_ACCESS_KEY`
- `MOMO_SECRET_KEY`


## Tài khoản và quyền quản trị

Người dùng có thể đăng ký trực tiếp ở trang đăng ký. Nếu cần cấp quyền admin, cập nhật bảng `user_roles` trong database:

```sql
INSERT INTO user_roles (user_id, role)
VALUES (USER_ID_CAN_CAP_QUYEN, 'ROLE_ADMIN');
```

Sau đó đăng nhập lại để hệ thống nhận quyền mới.

## Cấu trúc thư mục

```text
admin/          Trang quản trị
assets/         CSS, JavaScript và tài nguyên giao diện
config/         Cấu hình kết nối DB và đọc biến môi trường
includes/       Header, footer, kiểm tra đăng nhập và thành phần dùng chung
database.sql    Cấu trúc database và dữ liệu mẫu
.env.example    File mẫu cấu hình môi trường
```
