# MAC Frontend Motion Editor — Spec V1 (v0.2.0)

> Thay thế bản phác thảo trước. Đây là spec mới — plugin build lại từ đầu theo spec này.

## 1. Mục tiêu

Cho phép admin gán **entrance animation native của Elementor** (effect + speed + delay + device) trực tiếp ngoài frontend, không cần mở `Edit with Elementor`. Dữ liệu cuối cùng vẫn là `_elementor_data` chuẩn, lưu qua `Document::save()` — không ghi `update_post_meta` thô, không dùng class `m-*` tự chế.

## 2. Edit Mode (per-user, không global)

- Toggle lưu `user_meta` key `macfme_enabled` — chỉ account đang bật mới thấy editor. Khách và admin khác không load asset.
- Trang setting: **Tools → MAC Frontend Motion** (checkbox + lưu).
- Ngoài frontend (với user có `edit_post` trên page Elementor): admin bar có node **Motion Edit: ON/OFF** — click toggle qua AJAX rồi reload.
- Khi ON: preload **toàn bộ CSS entrance animation của Elementor** (`e-animation-{name}`) để preview được cả animation chưa dùng trên page (từ Elementor 3.24 CSS animation chỉ load khi được dùng). Tắt mode → frontend về trạng thái conditional-load bình thường.

## 3. Element detection & document boundary

- Frontend lấy post id từ container `.elementor-{post_id}` — **chỉ cho edit element thuộc document chính của page**.
- Element = selector `[data-element_type][data-id]` trong document root.
- Element ngoài document (header/footer/loop/global template cùng `data-id` namespace nhưng document khác): hiện chip **Shared Template — locked**, không cho sửa.
- V4/Atomic: `widgetType` bắt đầu `e-` hoặc `elType` lạ → **read-only**, panel hiện "Not supported yet". Không ghi sai schema.

## 4. Element map (source of truth từ server)

Khi edit mode ON, PHP build map một lần từ `Document::get_elements_data()` và inline vào page:

```
element id → { elType, widgetType, supported,
               animation {desktop, tablet, mobile},
               delay {desktop, tablet, mobile},
               duration, ancestors[] }
```

Kèm `hash = md5(json elements)` để conflict check. Hover chỉ lookup map — không AJAX mỗi lần rê chuột.

## 5. Schema settings (theo elType)

| elType | animation | delay | duration |
|---|---|---|---|
| `widget` | `_animation` (+`_tablet`/`_mobile`) | `_animation_delay` (+suffix) | `animation_duration` |
| `section`/`column`/`container` | `animation` (+suffix) | `animation_delay` (+suffix) | `animation_duration` |

- Desktop = không suffix; tablet/mobile = `_{device}`.
- `animation_duration` không responsive: `'' | 'slow' | 'fast'` (Normal = empty).
- Delay = int ms, step 100.
- `animation = 'none'` = explicit none (hiện ngay). Clear = **unset key**, không ghi `''`.

## 6. UI frontend

- Hover element → overlay outline + chip `✦ Motion` (không đụng vào DOM của element).
- Click chip → panel cố định cạnh phải:
  - Badge elType/widgetType; device tabs Desktop/Tablet/Mobile.
  - Animation select: `<optgroup>` đúng list `Control_Animation::get_animations()` (gồm cả animation thêm qua filter) — **không hardcode list**.
  - Speed: Slow / Normal / Fast. Delay: 0/100/200/300/500 + custom ms.
  - `Replay` = preview trong browser (class `elementor-invisible` → `animated {name} {speed}` sau delay), không ghi DB.
  - `Apply` = save. `Clear` = unset. `Undo` = re-apply giá trị trước đó (client-side stack).
- Panel hiện cảnh báo:
  - `⚠ Nested animation` — khi element hoặc ancestor đã có animation.
  - `⚠ {n} animated elements` — trên admin bar khi count > 10; bình thường hiện `Animated: n`.

## 7. Save engine

`admin-ajax.php`, actions `macfme_save` / `macfme_clear` / `macfme_toggle`.

Save payload: `post_id, element_id, device, animation, duration, delay, hash`.

Flow:

```
nonce + current_user_can('edit_post', post_id)
→ Document::get_elements_data()
→ md5 compare hash (mismatch → 409, client báo reload)
→ locate element theo 'id' (recursive path)
→ supported? (elType check, e-* reject)
→ adapter ghi đúng keys theo device
→ Document::save(['elements' => ...])
→ response { new hash, previous values, applied values }
```

Browser **không truyền tên setting** — chỉ truyền value; PHP tự chọn `_animation` hay `animation`.

## 8. Rule dùng tool (ngăn "animation hết cả page")

3–6 motion moments/page. Hero/CTA/giá/điện thoại hiện ngay, không delay. Cards cùng hàng stagger 0/100/200. Gallery animate heading/container không animate từng ảnh. Không stack entrance lên video/carousel. Parent đã animate thì child không. Mobile giảm movement.

## 9. Test cases bắt buộc trước khi rollout

1. Apply cho 1 widget heading + 1 container → reload → `Edit with Elementor` thấy đúng setting trong Advanced → Motion Effects.
2. Set tablet/mobile animation → responsive hoạt động.
3. Clear → element không còn `elementor-invisible`, keys biến mất khỏi `_elementor_data`.
4. Mở cùng page ở tab khác sửa trong Elementor → Apply ở tool → nhận 409.
5. Hover header/footer → locked.
6. Element `e-*` → read-only.
7. Tắt mode → page không còn asset editor, animation đã lưu vẫn chạy.
8. Export/import Elementor giữ đúng hiệu ứng.
