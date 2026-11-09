# MAC Frontend Motion Editor

Plugin WordPress chỉnh entrance animation native của Elementor ngay trên frontend.

## Cài lần đầu

1. Mở [Releases](https://github.com/LeoVo2003/mac-frontend-motion-editor/releases/latest).
2. Tải **mac-frontend-motion-editor.zip** (không dùng Source code ZIP).
3. Vào WordPress → Plugins → Add New → Upload Plugin. Nếu đã cài bản 0.2.0, chọn thay thế bản hiện tại một lần để bổ sung updater.
4. Vào Tools → MAC Frontend Motion, bật editor và mở một trang Elementor.

## Cập nhật sau này

Đẩy commit lên nhánh **main** → GitHub Actions kiểm thử → tạo release và ZIP → WordPress → Plugins → MAC Frontend Motion Editor → **Check updates → Update now**.

Mỗi lần workflow chạy có version tăng tự động, ví dụ `0.2.1.1001`, `0.2.1.2001`. Không phải sửa version cho từng commit. Commit còn ở máy hoặc ở nhánh khác chưa phát hành bản cập nhật. Bản lỗi kiểm thử không được phát hành. WordPress chỉ cài khi bạn bấm Update hoặc tự bật auto-update của WordPress.

WordPress kiểm tra định kỳ; Check updates xóa cache để kiểm tra ngay. Repo public nên website không cần GitHub token. Nếu GitHub đang đóng gói hoặc không truy cập được, công cụ báo kiểm tra thất bại để thử lại sau.

## Sử dụng

Rê chuột → Motion → chọn hiệu ứng, tốc độ, delay và thiết bị. Replay chỉ xem thử; Apply lưu qua Elementor Document::save(). Clear bỏ override thiết bị đang chọn và reset tốc độ/delay dùng chung. Undo khôi phục giá trị trước đó trong phiên hiện tại. Select parent chọn cả nhóm.

Chỉ sửa document chính; template dùng chung, global widget và Atomic/V4 bị khóa. Trang đổi ở tab khác thì phải reload trước khi lưu.

## Phát triển

```sh
npm ci
npx playwright install chromium
npm test
php tests/backend.php
php tests/updater.php
```

`npm test` kiểm tra cú pháp PHP 7.4 và thao tác trình duyệt với phản hồi lưu giả lập. Hai bài PHP kiểm tra hợp đồng backend/updater bằng test doubles. CI chạy thêm PHP thật. Kiểm tra tích hợp trên staging WordPress + Elementor theo `mac-frontend-motion-editor/readme.txt` trước khi triển khai rộng.

Source plugin ở `mac-frontend-motion-editor/`; workflow chỉ đóng gói thư mục này, giữ đúng tên plugin. `archive/` lưu bản phác thảo cũ; không nằm trong ZIP cài đặt.
