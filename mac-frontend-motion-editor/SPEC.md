# MAC Frontend Motion Editor — Spec v1.0

## 1. Mục tiêu

Cho admin chỉnh **entrance animation của Elementor ngay trên frontend**, không phải mở `Edit with Elementor`:

- Admin bật công tắc → mở page ngoài frontend → hover element → `✦ Motion`
- Picker xổ ra: animation (list native Elementor), speed, delay, device
- `Replay` = preview trong browser, **không ghi DB**
- `Apply` = lưu thật vào `_elementor_data` qua **`Document->save()`** (không `update_post_meta` thô) → CSS/cache/revision của Elementor tự chạy đúng lifecycle
- Dữ liệu cuối cùng là **native Elementor** — không `m-*` class, không animation system riêng

## 2. Setting schema (đã xác nhận từ Elementor core)

| Element | Animation key | Delay key | Duration key |
|---|---|---|---|
| Widget (`elType=widget`) | `_animation` | `_animation_delay` | `animation_duration` |
| Section / Column / Container | `animation` | `animation_delay` | `animation_duration` |
| Atomic V4 (`widgetType` = `e-*`) | **không support — locked** | — | — |

- Animation key là **responsive**: `_animation`, `_animation_tablet`, `_animation_mobile` (widget) và `animation`, `animation_tablet`, `animation_mobile` (container family). Device `desktop` = key gốc.
- `animation_duration`: `''` (Normal, default) / `'slow'` / `'fast'` — **không responsive**, dùng chung mọi device.
- Delay: số ms (0–10000, step 100) — **không responsive**.
- Giá trị animation hợp lệ = `Control_Animation::get_animations()` flatten + `''` (clear/Default) + `'none'` (explicit None). Không hardcode list trong plugin — gọi thẳng API của Elementor để kể cả animation do plugin khác add qua filter vẫn có.

## 3. Edit mode

- Toggle **theo user**, lưu `user_meta` `mfme_enabled` — không phải option global. Khách và admin khác không thấy gì.
- Bật/tắt ở: **Tools → MAC Frontend Motion** (checkbox) hoặc admin bar frontend `✦ Motion Edit: ON/OFF` (qua `admin-post.php?action=mfme_toggle` + nonce).
- Frontend assets chỉ enqueue khi: user login + toggle ON + `current_user_can('edit_post', $post_id)` + singular + document `is_built_with_elementor()` + không phải Elementor editor preview (`elementor-preview`, `is_preview_mode()`).

## 4. Element inspector

- Scope hover chỉ trong wrapper document chính: `.elementor-{post_id}`. Header/footer Theme Builder render ngoài wrapper này → tự nhiên không bị chọn.
- Server build **element map** từ `_elementor_data` một lần khi render page, nhúng vào `window.MFME_DATA`:
  `element_id → { elType, label, parent, editable, animated, motion: {desktop|tablet|mobile → {animation, duration, delay}} }`
- Element `.elementor-element[data-id]` **không có trong map** = shared template/loop render bên trong page → badge `Shared template — locked`, không cho sửa (vì `_elementor_data` của nó nằm ở document khác).
- `editable=false`: elType ngoài `[widget, section, column, container]` hoặc atomic `e-*` → badge `V4 / locked`.
- Panel có nút `↑ Select parent` để leo lên container cha — cách chính để animate cả group thay vì từng child.

## 5. Save engine (AJAX `wp_ajax_mfme_apply`)

Request: `nonce, doc_id, element_id, device, animation, duration, delay, hash`.

Server:

1. `check_ajax_referer("mfme_{doc_id}")`
2. Toggle của user đang ON + `current_user_can('edit_post', doc_id)` + Elementor loaded
3. `$document = Plugin::$instance->documents->get(doc_id)`, check `is_built_with_elementor()`
4. `$elements = $document->get_elements_data()` → tính `md5(wp_json_encode($elements))`, so với `hash` client → mismatch = **409 "Page changed, reload"** (chống ghi đè khi người khác/tab khác vừa save)
5. Tìm node theo `element_id` (đệ quy qua `elements[]`) → check `is_editable`
6. Validate: animation ∈ allowed list, duration ∈ `{'','slow','fast'}`, delay int ≤ 10000
7. Adapter ghi key đúng schema (widget vs container family) — **browser chỉ gửi value, PHP quyết định key**; không cho client truyền tên setting tùy ý
8. `$document->save(['elements' => $elements])`
9. Response: `hash` mới + `anim` đọc lại từ node vừa ghi → client cập nhật map mà không cần reload

Undo: client-side — lưu snapshot setting trước mỗi Apply, nút Undo = Apply với giá trị cũ (đi lại đúng save flow, có conflict guard).

## 6. Preview

- `Replay` mô phỏng đúng `global.js` của Elementor: add `elementor-invisible` → `setTimeout(delay)` → remove invisible, add `animated {animation}` + `animated-slow|animated-fast`.
- Restore class gốc khi đổi element/đóng panel.
- Từ Elementor 3.24 CSS animation tách file nhỏ, chỉ animation nào được dùng mới load → **khi edit mode ON, plugin enqueue toàn bộ `e-animation-*` đã register** (chỉ cho admin đó) để preview mọi hiệu ứng. Fallback `elementor-animations` cho bản cũ bundle chung.

## 7. Motion rules (tool nhắc, người quyết)

- 3–6 motion moments / page; `>10` animated nodes → toolbar cảnh báo `⚠ N animated`
- Parent + child cùng animate → cảnh báo `⚠ nested` (tính theo chuỗi `parent` trong map)
- Hero headline/CTA/giá/phone: hiện ngay; card row: `fadeInUp` stagger 0/100/200; video/carousel không thêm entrance; mobile giảm movement.

Toolbar nổi: `Motion Edit ON · Animated: N · ⚠… · Turn off`.

## 8. V1 không làm

- Không edit Theme Builder document (header/footer), global widget, loop item
- Không ghi Atomic V4 Interactions
- Không bulk-apply nhiều element cùng lúc, không copy/paste animation
- Không đo hiệu năng, không QA mobile — đó là bước nghiệm thu khi triển khai 41 Home

## 9. File structure

```
mac-frontend-motion-editor/
├── mac-frontend-motion-editor.php   # bootstrap
├── includes/
│   ├── class-mfme-plugin.php        # init, admin bar, enqueue + context
│   ├── class-mfme-settings.php      # user_meta toggle, Tools page
│   ├── class-mfme-animations.php    # animation list + schema adapters
│   ├── class-mfme-element-map.php   # map builder + recursive mutate
│   └── class-mfme-ajax.php          # apply endpoint + toggle + hash guard
├── assets/js/motion-editor.js       # hover/outline/picker/preview/apply
└── assets/css/motion-editor.css     # overlay + panel + toolbar
```

## 10. Test cases (staging trước khi production)

1. Heading widget: `fadeInUp` + `fast` + `200` → Apply → reload → check `elementor-invisible` + classes render đúng; mở `Edit with Elementor` → Motion Effects hiển thị đúng value.
2. Container: `fadeIn` tablet only → `_elementor_data` có `animation_tablet`, desktop giữ nguyên.
3. Clear: Apply animation `''` → unset key animation của device đang chọn, duration Normal và delay 0 cũng unset; animation device khác giữ nguyên. Responsive default có thể kế thừa device lớn hơn.
4. Conflict: sửa page ở tab khác → Apply ở tab cũ → 409, không ghi đè.
5. Hover element shared template → locked badge, picker không mở.
6. Atomic `e-heading` (nếu site có V4) → locked.
7. Tắt toggle → frontend sạch, không load JS/CSS plugin.
8. `Edit with Elementor` vẫn mở/save bình thường sau khi tool đã ghi data.
