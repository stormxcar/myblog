# Tailwind Migration Plan (Phase-by-Phase)

## Muc tieu

- Tach dan CSS legacy de chuyen sang Tailwind utility-first co kiem soat.
- Giu nguyen giao dien va hanh vi trong qua trinh chuyen doi.
- Giam xung dot style giua `output.css`, `blog-modern.css`, `ui-system.css`, `admin-modern.css`.

## Hien trang CSS

- `output.css`: Tailwind build output (duoc giu lai).
- `input.css`: nguon Tailwind (`@tailwind base/components/utilities`) va custom layer.
- `blog-modern.css`: public UI custom, dang dung nhieu.
- `ui-system.css`: utility/component chung cho user-facing pages.
- `admin-modern.css`: style rieng khu admin (khong Tailwind thuan).
- `style_dark.css`, `style_edit.css`: CSS legacy cu can danh gia su dung thuc te.

## Lo trinh de xuat

### Phase 1 - Inventory va canh bao xung dot

- Them tag phan loai trong moi CSS file: `core`, `legacy`, `admin`, `public`.
- Ghi ro file nao la "source of truth" cho tung khu:
  - Public: `output.css` + mot phan nho tu `ui-system.css`.
  - Admin: tam thoi giu `admin-modern.css`.
- Cam them style moi vao `blog-modern.css` neu da co utility Tailwind tuong duong.

### Phase 2 - Component hoa class custom

- Chuyen cac class lap lai sang `@layer components` trong `input.css`:
  - nut: `.btn-primary`, `.btn-secondary`
  - card: `.panel-card`, `.stat-card`
  - form: `.form-input`, `.form-label`
- Public pages uu tien chuyen truoc: login/register/home/posts/search.

### Phase 3 - Giam phu thuoc CSS legacy

- Loai bo dan style trung lap giua `blog-modern.css` va utility Tailwind.
- Moi sprint chi deprecate 1 nhom:
  1. spacing + typography
  2. cards + table
  3. form + button
- Dat co `/* @deprecated */` cho rule cu, xoa sau 1-2 sprint neu khong con su dung.

### Phase 4 - Admin migration co kiem soat

- Tach admin sang bo class utility rieng, giam dan rule global override.
- Boi cach chuyen tung man hinh admin:
  1. dashboard
  2. view_posts/comments/add_cart/users_accounts
  3. forms (add/edit)
- Khi da on dinh, doi `admin-modern.css` thanh file nhe (chi con override toi thieu).

## Quy tac implementation

- Truoc khi sua layout, xac dinh breakpoints va man hinh muc tieu.
- Khong dung selector qua rong (`body *`, `a`, `table`) de tranh tac dong day chuyen.
- Moi PR migration phai co:
  - screenshot desktop + mobile
  - danh sach class legacy da bo
  - ghi chu fallback neu rollback

## Nhung viec tiep theo

1. Danh dau cac rule trong `blog-modern.css` dang trung voi Tailwind utility.
2. Chuyen nhom component form/button vao `input.css` (`@layer components`).
3. Chay QA responsive tren login/register/home va admin dashboard sau migration dot 1.
