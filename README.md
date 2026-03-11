# HVN Pricing Calculator — WHMCS Addon Module

**Module Name:** `hvn_pricing_calculator`  
**Module Type:** WHMCS Addon Module  
**Author:** HVN GROUP  
**Version:** 1.1.0  
**Last Updated:** March 2026  
**Status:** Production  

---

## 1. Executive Summary

HVN Pricing Calculator là một WHMCS addon module giúp quản trị viên tự động tính giá sản phẩm, configurable options và addons dựa trên giá Monthly của default currency. Module tự động tính giá cho tất cả billing cycles (Quarterly → Triennially) với discount tùy chỉnh, đồng thời quy đổi tỷ giá sang tất cả currencies khác sử dụng exchange rates có sẵn trong WHMCS.

---

## 2. Problem Statement

### 2.1 Pain Points

Quản trị viên WHMCS phải tính giá thủ công tại **4 nơi khác nhau**:

| # | Trang cấu hình | URL | Thao tác thủ công |
|---|---|---|---|
| 1 | Product Pricing | `configproducts.php#tab=2` | Nhập 6 cycles × N currencies |
| 2 | Product → Configurable Options | `configproducts.php#tab=5` | Chỉ assign group, phải sang trang khác set giá |
| 3 | Configurable Options Pricing | `configproductoptions.php` | Nhập 6 cycles × N currencies × N sub-options |
| 4 | Addon Pricing | `configaddons.php#tab=2` | Nhập 6 cycles × N currencies |

### 2.2 Quy mô vấn đề

Với mỗi item, admin phải:
- Tính giá 6 billing cycles từ giá Monthly (nhân 3, 6, 12, 24, 36)
- Áp dụng discount cho cycles dài hơn (nếu có chính sách)
- Quy đổi sang tất cả currencies theo tỷ giá
- Lặp lại cho hàng trăm products/options/addons

**Ví dụ thực tế:** 50 products × 6 cycles × 2 currencies = **600 ô nhập liệu thủ công**, mỗi ô phải bấm máy tính.

---

## 3. Solution Overview

### 3.1 Two Core Features

**Feature A — Pricing Calculator Toolbar:** Inject toolbar tính giá tự động vào các trang pricing của WHMCS admin. Admin chỉ cần nhập giá Monthly cho default currency → toolbar tính tất cả cycles và currencies.

**Feature B — Inline Configurable Options Manager:** Hiển thị và quản lý configurable options pricing trực tiếp trong trang Product edit (tab Configurable Options), bao gồm tạo nhanh option group nếu chưa có.

### 3.2 Key Principles

- **Standalone**: Hoạt động độc lập, không phụ thuộc bất kỳ server module nào
- **Non-invasive**: Không sửa core WHMCS, chỉ inject qua hooks
- **Read WHMCS data**: Tận dụng currencies, rates, pricing tables có sẵn
- **Universal**: Áp dụng cho mọi product type, mọi server module

---

## 4. Compatibility

| Requirement | Specification |
|-------------|---------------|
| WHMCS Version | 8.0+ |
| PHP Version | 8.0+ |
| Database | MySQL 5.7+ / MariaDB 10.3+ |
| Dependencies | None (vanilla JS + Alpine.js 3.x CDN) |
| External Libraries | Alpine.js 3.14.8 (CDN với local fallback) |
| Admin Theme | WHMCS default admin theme (Six / Eight) |
| Server Modules | Works with all (module-agnostic) |
| Currencies | Unlimited (reads from WHMCS settings) |
| Languages | English, Vietnamese (extensible) |

---

## 5. Installation

1. Upload thư mục `hvn_pricing_calculator` vào `/modules/addons/`
2. Vào **WHMCS Admin → Setup → Addon Modules**
3. Tìm **HVN Pricing Calculator** → click **Activate**
4. Click **Configure** → gán quyền Admin cho module
5. Module tự động tạo bảng `tbl_hvn_pricing_presets` và seed 6 presets mặc định

---

## 6. Feature A — Pricing Calculator Toolbar

### 6.1 Supported Pages

| Page | URL | Features |
|------|-----|----------|
| Product Pricing | `configproducts.php?action=edit` → Pricing tab | Cycles, Currencies, Setup Fee |
| Configurable Options | `configproductoptions.php` (popup) | Cycles, Currencies cho từng sub-option |
| Addon Pricing | `configaddons.php?action=manage` → Pricing tab | Cycles, Currencies, Setup Fee |
| Inline Config Manager | `configproducts.php?action=edit` → tab Configurable Options | Quick Create, inline pricing |

### 6.2 Toolbar UI

```
┌─────────────────────────────────────────────────────────────────────┐
│ ⚡ HVN Pricing Calculator             [▾ Preset: Standard All Cycles]│
├─────────────────────────────────────────────────────────────────────┤
│  Base: [Monthly ▾]  Round: [Nearest ▾]  To: [1 ▾]  [✓ Overwrite]  │
│                                                                     │
│  Cycles:   [✓ Quarterly] [✓ Semi-Annual] [✓ Annual] [✓ Bi] [✓ Tri]│
│                                                                     │
│  Discounts: Q [0]%  SA [5]%  A [10]%  Bi [15]%  Tri [20]%         │
│  Setup Fee: Q [0]%  SA [0]%  A [0]%   Bi [0]%   Tri [0]%          │
│                                                                     │
│  [📊 Calc Cycles] [💱 Calc Currencies] [⚡ Calc All] [↩ Undo]     │
│                                                                     │
│  ℹ VND (default, rate: 1.0)  USD (rate: 0.0000393)                 │
└─────────────────────────────────────────────────────────────────────┘
```

### 6.3 Toolbar Inputs

| Input | Type | Default | Description |
|-------|------|---------|-------------|
| Preset | Dropdown | Standard All Cycles | Bộ discount đã lưu, tự động load discount + cycle toggles |
| Base Cycle | Dropdown | Monthly | Cycle gốc để tính các cycles khác |
| Rounding | Dropdown | Nearest | none / ceil / floor / round |
| Round To | Dropdown | 1 | 0.01 / 1 / 100 / 1,000 / 10,000 |
| Overwrite | Checkbox | ✓ | Ghi đè giá đã có; nếu bỏ chọn, chỉ điền ô trống |
| Cycle Toggles | Checkboxes | Theo preset | Bật/tắt từng cycle cần tính |
| Discount Q–Tri | Number % | Theo preset | Discount cho từng cycle |
| Setup Fee Q–Tri | Number % | Theo preset | Discount setup fee cho từng cycle |

### 6.4 Actions

| Button | Function |
|--------|----------|
| Calc Cycles | Tính tất cả cycles từ base cycle trong default currency |
| Calc Currencies | Quy đổi default currency → tất cả currencies khác |
| Calc All | Chạy Calc Cycles + Calc Currencies tuần tự |
| Undo | Khôi phục giá trị trước lần calculate gần nhất (1 level) |

### 6.5 Calculation Formulas

**Cycle Calculation (base = Monthly):**
```
Quarterly     = Monthly × 3  × (1 - discount_q  / 100)
Semi-Annually = Monthly × 6  × (1 - discount_sa / 100)
Annually      = Monthly × 12 × (1 - discount_a  / 100)
Biennially    = Monthly × 24 × (1 - discount_bi / 100)
Triennially   = Monthly × 36 × (1 - discount_tri/ 100)
```

**Cycle Calculation (base = Annually):**
```
Biennially  = Annually × 2 × (1 - discount_bi  / 100)
Triennially = Annually × 3 × (1 - discount_tri / 100)
```

**Currency Conversion:**
```
Target Price = Source Price × (target_rate / source_rate)

Ví dụ: VND rate=1.0 (default), USD rate=0.0000393
  245,455 VND → USD = 245,455 × (0.0000393 / 1.0) = $9.65
```

**Setup Fee:**
```
Setup fees được quy đổi currency nhưng KHÔNG nhân theo cycle.
"Calc Cycles" bỏ qua cột setup fee.
"Calc Currencies" áp dụng cho cả setup fee.
```

### 6.6 Visual Feedback

| State | Visual |
|-------|--------|
| Cell vừa được tính | Background `#ffffcc` (light yellow), fade sau 3s |
| Cell disabled (`-1.00`) | Background `#f5f5f5`, text `#999` — bỏ qua khi tính |
| Cell free (`0.00`) | Giữ nguyên, bỏ qua khi tính |
| Toast notification | Fixed top-center, auto-dismiss sau 3.5s |
| Currency rate display | Inline dưới toolbar |

---

## 7. Feature B — Inline Configurable Options Manager

### 7.1 Scope

Inject vào **Product edit page → tab Configurable Options** (`configproducts.php?action=edit&id=X`).

### 7.2 Trường hợp có option groups

- Hiển thị currency tabs (VND / USD / ...)
- Embedded Pricing Calculator Toolbar
- Danh sách option groups → từng option → pricing table theo currency
- Mỗi option có toggle **Hidden**
- Mỗi sub-option có toggle **Hidden** riêng
- Badge **"Shared with N product(s)"** nếu group được dùng cho nhiều product
- Nút **↗ Manage** → mở WHMCS config options page

### 7.3 Trường hợp chưa có option groups

- Form **Quick Create**: nhập tên group + mô tả → tạo mới và tự động assign
- Hoặc **Assign existing**: chọn group đã có từ dropdown

### 7.4 Save

Pricing được lưu khi admin click **Save Changes** trên trang WHMCS thông qua hook `AdminProductConfigFieldsSave`. Dữ liệu ghi vào `tblpricing` và trạng thái hidden vào `tblproductconfigoptions` / `tblproductconfigoptionssub`.

---

## 8. Feature C — Discount Presets

### 8.1 Default Presets (seeded khi activate)

| Preset Name | Base | Q | SA | A | Bi | Tri |
|-------------|------|---|----|---|----|-----|
| No Discount — All Cycles | Monthly | 0% | 0% | 0% | 0% | 0% |
| Standard — All Cycles | Monthly | 0% | 5% | 10% | 15% | 20% |
| Standard — Flexible | Monthly | 0% | 5% | 10% | — | — |
| Standard — Annual Only | Annually | — | — | — | 10% | 15% |
| Aggressive — All Cycles | Monthly | 5% | 10% | 20% | 25% | 30% |
| Annual Base — Bi & Tri | Annually | — | — | — | 15% | 20% |

### 8.2 Preset Management

- Vào **WHMCS Admin → Addons → HVN Pricing Calculator → Preset Management**
- CRUD: thêm, sửa, xóa preset
- Mỗi preset lưu: tên, base cycle, discount cho 5 cycles, discount setup fee cho 5 cycles, cycle toggles, rounding method, rounding precision, is_default flag

### 8.3 Database Schema

Module tạo **1 custom table** khi activate. WHMCS tables được dùng theo read/write như mô tả ở mục 9.4.

#### `tbl_hvn_pricing_presets`

```sql
CREATE TABLE tbl_hvn_pricing_presets (
    id                           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                         VARCHAR(100) NOT NULL,
    base_cycle                   VARCHAR(20) DEFAULT 'monthly',      -- 'monthly' | 'annually'
    -- Recurring discounts (%)
    discount_quarterly           DECIMAL(5,2) DEFAULT 0.00,
    discount_semiannually        DECIMAL(5,2) DEFAULT 0.00,
    discount_annually            DECIMAL(5,2) DEFAULT 0.00,
    discount_biennially          DECIMAL(5,2) DEFAULT 0.00,
    discount_triennially         DECIMAL(5,2) DEFAULT 0.00,
    -- Setup fee discounts (%) — added v1.1.0
    discount_setup_quarterly     DECIMAL(5,2) DEFAULT 0.00,
    discount_setup_semiannually  DECIMAL(5,2) DEFAULT 0.00,
    discount_setup_annually      DECIMAL(5,2) DEFAULT 0.00,
    discount_setup_biennially    DECIMAL(5,2) DEFAULT 0.00,
    discount_setup_triennially   DECIMAL(5,2) DEFAULT 0.00,
    -- Cycle enable toggles: 1=enabled, 0=skip — added v1.1.0
    cycle_quarterly              TINYINT DEFAULT 1,
    cycle_semiannually           TINYINT DEFAULT 1,
    cycle_annually               TINYINT DEFAULT 1,
    cycle_biennially             TINYINT DEFAULT 1,
    cycle_triennially            TINYINT DEFAULT 1,
    -- Rounding
    rounding_method              ENUM('none','ceil','floor','round') DEFAULT 'round',
    rounding_precision           DECIMAL(10,2) DEFAULT 1.00,
    is_default                   TINYINT DEFAULT 0,
    sort_order                   INT DEFAULT 0,
    created_at                   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Migration v1.1.0** (tự động qua `_upgrade()`): thêm các cột `discount_setup_*`, `cycle_*`, `base_cycle` cho installations đang chạy v1.0.0.

### 8.4 Default Presets (9 presets — seeded khi activate)

Module seed **3 discount levels × 3 cycle profiles = 9 presets**:

| Preset Name | Base | Q | SA | A | Bi | Tri | Default? |
|---|---|---|---|---|---|---|---|
| No Discount — All Cycles | Monthly | 0% | 0% | 0% | 0% | 0% | |
| No Discount — Flexible | Monthly | 0% | 0% | 0% | — | — | |
| No Discount — Annual Only | Annually | — | — | — | 0% | 0% | |
| Standard — All Cycles | Monthly | 0% | 5% | 10% | 15% | 20% | ✓ |
| Standard — Flexible | Monthly | 0% | 5% | 10% | — | — | |
| Standard — Annual Only | Annually | — | — | — | 10% | 15% | |
| Aggressive — All Cycles | Monthly | 5% | 10% | 20% | 25% | 30% | |
| Aggressive — Flexible | Monthly | 5% | 10% | 20% | — | — | |
| Aggressive — Annual Only | Annually | — | — | — | 15% | 20% | |

> `—` = cycle bị tắt (skip), không tính.

---

## 9. Technical Architecture

### 9.1 Module Structure

```
modules/addons/hvn_pricing_calculator/
│
├── hvn_pricing_calculator.php       # Main addon file
│                                    #   config(), activate(), deactivate(), upgrade()
│                                    #   output() — AJAX handler + page router
│                                    #   getConfigOptionsData(), saveConfigOptionsPricing()
│                                    #   renderDashboard(), renderPresets()
│
├── hooks.php                        # Hook registrations + helpers
│                                    #   AdminAreaHeaderOutput  → load CSS (normal pages)
│                                    #   AdminAreaHeadOutput    → load CSS + JS (popup pages)
│                                    #   AdminAreaFooterOutput  → inject toolbar HTML + JS config
│                                    #   AdminProductConfigFieldsSave → save config options pricing
│
├── assets/
│   ├── js/
│   │   └── hvn-pricing.js           # Main JS (IIFE)
│   │                                #   Utils, Undo, Calc, PricingDOM
│   │                                #   Alpine: hvnPricingToolbar(), hvnConfigManager(), hvnConfigToolbar()
│   │                                #   buildToolbarHtml(), buildEmbeddedToolbar(), boot()
│   └── css/
│       └── hvn-pricing.css          # All styles (Ant Design–inspired, pure CSS)
│
├── templates/
│   ├── admin-dashboard.php          # Admin dashboard: guide, formulas, quick links
│   ├── admin-presets.php            # Preset management UI
│   └── config-options-manager.php   # Inline config options manager
│
└── lang/
    ├── english.php
    └── vietnamese.php
```

### 9.2 Hooks

| Hook | Khi nào | Mục đích |
|------|---------|---------|
| `AdminAreaHeaderOutput` | Mọi trang admin | Load CSS nếu đang ở trang target |
| `AdminAreaHeadOutput` | Mọi trang admin | Inject CSS + JS cho **popup pages** (configproductoptions.php) |
| `AdminAreaFooterOutput` | Mọi trang admin | Inject config data + JS + toolbar HTML cho normal pages |
| `AdminProductConfigFieldsSave` | Product save | Save inline pricing, hidden states, quick create |

### 9.3 Page Detection

```php
// PageDetector::detect() trả về:
'product_edit'  → configproducts.php?action=edit
'config_options'→ configproductoptions.php (popup)
'addon_edit'    → configaddons.php?action=manage
null            → không inject gì
```

### 9.4 WHMCS Tables Used

| Table | Access | Purpose |
|-------|--------|---------|
| `tblcurrencies` | Read only | Currency list + conversion rates |
| `tblpricing` | Read + Write | All pricing data |
| `tblproducts` | Read only | Product info for context |
| `tblproductconfiggroups` | Read + Write | Config option groups (Write: Quick Create) |
| `tblproductconfiglinks` | Read + Write | Group ↔ Product links (Write: Quick Create + Assign) |
| `tblproductconfigoptions` | Read + Write | Options metadata + hidden state |
| `tblproductconfigoptionssub` | Read + Write | Sub-options + hidden state |
| `tbladdons` | Read only | Addon info for context |
| `tbl_hvn_pricing_presets` | Read + Write | Custom table — discount presets |

### 9.5 JS Architecture

```
hvn-pricing.js (IIFE namespace)
├── Utils          — toast, fetchJson, round, formatNum
├── Undo           — snapshot(), restore()
├── Calc           — cycles(), setupFees(), currencies()
├── PricingDOM     — findInputs(), readVal(), writeVal(), highlightChanged()
├── HvnPricing     — Alpine.js component: hvnPricingToolbar()
└── buildToolbarHtml() — render toolbar HTML string (inject vào DOM)

config-options.js (IIFE namespace)
├── hvnConfigManager()   — Alpine.js component: load/save config options
└── hvnConfigToolbar()   — Alpine.js component: embedded toolbar trong manager
```

### 9.6 AJAX Endpoints

Tất cả qua `addonmodules.php?module=hvn_pricing_calculator&action=<action>`:

| Action | Method | Purpose | Response |
|--------|--------|---------|----------|
| `get_presets` | GET | Lấy danh sách presets | `{success, data[]}` |
| `save_preset` | POST | Tạo/cập nhật preset | `{success, id}` |
| `delete_preset` | POST | Xóa preset | `{success}` |
| `get_currencies` | GET | Lấy currencies + rates | `{success, data[]}` |
| `get_config_options` | GET | Lấy config options + pricing theo product | `{success, groups[], currencies[]}` |
| `save_config_options` | POST | Lưu pricing + hidden states | `{success, count}` |
| `quick_create_group` | POST | Tạo config group mới + assign to product | `{success, group_id}` |
| `assign_config_group` | POST | Assign group có sẵn vào product | `{success}` |

---

## 10. Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.0+, Laravel Capsule ORM |
| Frontend reactivity | Alpine.js 3.14.8 (CDN + local fallback) |
| CSS | Pure CSS — Ant Design–inspired, CSS custom properties |
| No build step | Không dùng Vue, React, Bootstrap, jQuery |
| AJAX | Native `fetch()` với JSON |

---

## 11. Development Status

### Phase 1 — Pricing Calculator MVP ✅ COMPLETED

- [x] Module skeleton (`hvn_pricing_calculator.php` — activate, deactivate, config, output)
- [x] `PageDetector` — detect Product Pricing, ConfigOptions, Addon Pricing pages
- [x] `CurrencyHelper` — read `tblcurrencies`, get rates, get default
- [x] `Calculator` — cycle calculation từ Monthly và Annually
- [x] Toolbar HTML/CSS — inject vào Product Pricing tab
- [x] Toolbar JS — calculate cycles, calculate currencies, calc all
- [x] Cycle toggles — bật/tắt từng cycle cần tính
- [x] Inline discount inputs cho cả recurring và setup fee
- [x] Rounding options (none, ceil, floor, round × precision)
- [x] Overwrite toggle — bảo vệ giá đã nhập
- [x] Visual feedback (highlight cells, toast notification)
- [x] Undo (1 level, snapshot before calculate)
- [x] Support Addon Pricing page
- [x] Support Configurable Options popup (`configproductoptions.php`)
- [x] Inject CSS + JS đúng cách cho cả normal pages và popup pages

### Phase 2 — Config Options Manager ✅ COMPLETED

- [x] `ConfigOptionsReader` — read groups, options, sub-options, pricing
- [x] `ConfigOptionsWriter` — save pricing, hidden states
- [x] Inline Config Options Manager trên Product tab Configurable Options
- [x] Multi-currency tabs với pricing table
- [x] Pricing Calculator embedded trong Config Options Manager
- [x] Quick Create group + assign to product (AJAX)
- [x] Assign existing group (AJAX)
- [x] Save via `AdminProductConfigFieldsSave` hook
- [x] Shared group warning badge
- [x] Collapse/expand option groups
- [x] Hidden toggle cho option và sub-option

### Phase 3 — Presets & Polish ✅ COMPLETED

- [x] Custom table `tbl_hvn_pricing_presets`
- [x] Preset CRUD (settings page UI — Alpine.js)
- [x] Preset selector dropdown trong toolbar
- [x] 6 default presets seeded khi activate
- [x] Module admin dashboard page
- [x] Language files (English, Vietnamese)
- [x] Preset lưu cycle toggles + setup fee discounts

---

## 12. Author

**HVN GROUP**  
Website: [https://hvn.vn](https://hvn.vn)

---

*Module này chỉ điền giá vào form WHMCS — việc lưu do WHMCS xử lý. Module không can thiệp vào core WHMCS.*