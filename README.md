# Product Requirements Document (PRD)
# HVN Pricing Calculator — WHMCS Addon Module

**Module Name:** HVN Pricing Calculator (`hvn_pricing_calculator`)
**Module Type:** WHMCS Addon Module
**Author:** HVN GROUP
**Version:** 1.0
**Date:** March 2026
**Status:** Draft

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

### 2.3 WHMCS đã có gì

- **Currency Management** (`System Settings → Currencies`): Quản lý danh sách currencies với Base Conversion Rate giữa mỗi currency, lưu trong `tblcurrencies.rate`.
- **Pricing Tables**: Bảng `tblpricing` lưu giá cho tất cả entity types (product, configoption, addon...) theo currency và billing cycle.

Module này sẽ **tận dụng** hoàn toàn data có sẵn, không duplicate.

---

## 3. Solution Overview

### 3.1 Two Core Features

**Feature A — Pricing Calculator Toolbar:**
Inject toolbar tính giá tự động vào các trang pricing của WHMCS admin. Admin chỉ cần nhập giá Monthly cho default currency → toolbar tính tất cả cycles và currencies.

**Feature B — Inline Configurable Options Manager:**
Hiển thị và quản lý configurable options pricing trực tiếp trong trang Product edit (tab Configurable Options), bao gồm tạo nhanh option group nếu chưa có.

### 3.2 Key Principles

- **Standalone**: Hoạt động độc lập, không phụ thuộc bất kỳ server module nào
- **Non-invasive**: Không sửa core WHMCS, chỉ inject qua hooks
- **Read WHMCS data**: Tận dụng currencies, rates, pricing tables có sẵn
- **Universal**: Áp dụng cho mọi product type, mọi server module

---

## 4. Feature A — Pricing Calculator Toolbar

### 4.1 Scope — Trang được hỗ trợ

| Page | Detection | Pricing Input Pattern |
|------|-----------|----------------------|
| Product Pricing (`configproducts.php#tab=2`) | `action=edit` + pricing tab | `pricing[currency_id][cycle]` |
| Configurable Options (`configproductoptions.php`) | Pricing section | Option pricing inputs |
| Addon Pricing (`configaddons.php#tab=2`) | `action=manage` + pricing tab | `pricing[currency_id][cycle]` |

### 4.2 Toolbar UI

```
┌───────────────────────────────────────────────────────────────────┐
│ ⚡ HVN Pricing Calculator                   [▾ Preset: Standard] │
├───────────────────────────────────────────────────────────────────┤
│                                                                   │
│  Base: [Monthly ▾]   Round: [Nearest ▾]   To: [1 ▾]              │
│                                                                   │
│  Discounts:  Q [0]%   SA [5]%   A [10]%   Bi [15]%   Tri [20]%   │
│                                                                   │
│  [📊 Calc Cycles] [💱 Calc Currencies] [⚡ Calc All] [↩ Undo]    │
│                                                                   │
│  ℹ VND (default, rate: 1.0) → USD (rate: 0.0000393)              │
└───────────────────────────────────────────────────────────────────┘
```

**Toolbar inputs:**

| Input | Type | Default | Description |
|-------|------|---------|-------------|
| Base Cycle | Dropdown | Monthly | Cycle gốc để tính các cycles khác |
| Preset | Dropdown | Standard | Bộ discount đã lưu |
| Discount Q | Number (%) | 0 | Discount cho Quarterly |
| Discount SA | Number (%) | 5 | Discount cho Semi-Annually |
| Discount A | Number (%) | 10 | Discount cho Annually |
| Discount Bi | Number (%) | 15 | Discount cho Biennially |
| Discount Tri | Number (%) | 20 | Discount cho Triennially |
| Rounding | Dropdown | Round nearest | Phương thức làm tròn |
| Round to | Dropdown | 1 | Đơn vị làm tròn (0.01 / 1 / 100 / 1,000 / 10,000) |

**Toolbar actions:**

| Button | Function |
|--------|----------|
| Calc Cycles | Tính tất cả cycles từ base cycle (trong default currency) |
| Calc Currencies | Quy đổi default currency → tất cả currencies khác |
| Calc All | Chạy Calc Cycles + Calc Currencies tuần tự |
| Undo | Khôi phục giá trị trước lần calculate gần nhất |

### 4.3 Calculation Formulas

**Cycle Calculation (base = Monthly):**

```
Quarterly     = Monthly × 3  × (1 - discount_q / 100)
Semi-Annually = Monthly × 6  × (1 - discount_sa / 100)
Annually      = Monthly × 12 × (1 - discount_a / 100)
Biennially    = Monthly × 24 × (1 - discount_bi / 100)
Triennially   = Monthly × 36 × (1 - discount_tri / 100)
```

**Cycle Calculation (base = Annually):**

```
Biennially    = Annually × 2 × (1 - discount_bi / 100)
Triennially   = Annually × 3 × (1 - discount_tri / 100)
```

**Currency Conversion (using WHMCS `tblcurrencies.rate`):**

```
Target Price = Source Price × (target_currency.rate / source_currency.rate)

Example:
  VND rate = 1.00000 (default currency)
  USD rate = 0.00003930

  245,455 VND → USD: 245,455 × (0.0000393 / 1.0) = $9.65
  $9.65 USD → VND: 9.65 × (1.0 / 0.0000393) = 245,547 VND
```

**Setup Fee handling:**

```
Setup fees are converted between currencies but NOT multiplied by cycle.
Only "Calc Currencies" affects setup fees.
"Calc Cycles" skips all setup fee columns.
```

### 4.4 Visual Feedback

| State | Visual |
|-------|--------|
| Cell vừa được calculate | Background `#ffffcc` (light yellow), fade sau 3s |
| Cell disabled (`-1.00`) | Background `#f5f5f5`, text `#999` — skip khi calculate |
| Cell free (`0.00`) | Giữ nguyên, skip khi calculate |
| Toast notification | Fixed top-right, hiện 3.5s, fade out |
| Currency rate display | Inline text dưới toolbar |

---

## 5. Feature B — Inline Configurable Options Manager

### 5.1 Scope

Inject vào **Product edit page → tab Configurable Options** (`configproducts.php?action=edit&id=X#tab=5`).

### 5.2 UI Layout

**Trường hợp 1: Product đã có assigned option groups**

```
┌───────────────────────────────────────────────────────────────────┐
│ ⚙ Configurable Options Manager                [↗ Open in WHMCS] │
├───────────────────────────────────────────────────────────────────┤
│                                                                   │
│  [VND Default] [USD]                    ← Currency Tabs           │
│                                                                   │
│  ⚡ Pricing Calculator Toolbar (same as Feature A)                │
│                                                                   │
│  ┌ Product SKU ID [DROPDOWN] ──────────────────── [✓] Hidden ──┐ │
│  │ Option           │ Hide │ Monthly │ Q    │ SA   │ ... │ Fee │ │
│  │ Business Starter │  [ ] │    0.00 │ 0.00 │ 0.00 │ ... │ 0.0│ │
│  │ Business Standard│  [ ] │    0.00 │ 0.00 │ 0.00 │ ... │ 0.0│ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                   │
│  ┌ Number of Seats [QUANTITY] Min:1/Max:300 ──── [ ] Hidden ───┐ │
│  │ Option           │ Hide │ Monthly   │ Q      │ SA     │ ... │ │
│  │ tài khoản        │  [ ] │ 245,455   │ 736,365│ 1.47M  │ ... │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                   │
│  [-1.00] = disabled  [0.00] = free                                │
│  Click Save Changes to persist.                                   │
└───────────────────────────────────────────────────────────────────┘
```

**Trường hợp 2: Product chưa có assigned option groups**

```
┌───────────────────────────────────────────────────────────────────┐
│ ⚙ Configurable Options Manager                                   │
├───────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ℹ No configurable option groups assigned to this product.        │
│                                                                   │
│  ┌─ Quick Create ───────────────────────────────────────────────┐ │
│  │                                                               │ │
│  │  Create new group:                                            │ │
│  │  Group Name: [_______________________]                        │ │
│  │  Description: [_______________________] (optional)            │ │
│  │                                                               │ │
│  │  [+ Create & Assign to This Product]                          │ │
│  │                                                               │ │
│  │  ── OR assign existing group: ──                              │ │
│  │  [▾ Select existing group...     ] [Assign]                   │ │
│  │                                                               │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                   │
└───────────────────────────────────────────────────────────────────┘
```

### 5.3 Quick Create Flow

```
1. Admin opens Product → tab Configurable Options
2. No groups assigned → Quick Create form shown
3. Admin enters group name → clicks "Create & Assign"
4. Module executes:
   a. INSERT INTO tblproductconfiggroups (name, description)
   b. INSERT INTO tblproductconfiglinks (gid, pid)
   c. Initialize default pricing records
5. Section reloads → shows empty group ready for pricing
6. Admin can add options via "Open in WHMCS" link
   or configure pricing for existing options inline
```

### 5.4 Data Flow

```
Read:
  tblproductconfiglinks  → which groups are assigned to this product
  tblproductconfiggroups → group names and descriptions
  tblproductconfigoptions → options within each group
  tblproductconfigoptionssub → sub-options (e.g., SKU list items)
  tblpricing (type='configoptions') → pricing per sub-option per currency
  tblcurrencies → currency list + rates

Write (on Save Changes):
  tblpricing → update/insert pricing values
  tblproductconfigoptions → update hidden state
  tblproductconfigoptionssub → update hidden state

Write (on Quick Create):
  tblproductconfiggroups → new group
  tblproductconfiglinks → link group to product
```

---

## 6. Feature C — Discount Presets

### 6.1 Preset Management

Admin can create, edit, delete discount presets via module settings page (`addonmodules.php?module=hvn_pricing`).

**Default presets (seeded on activation):**

| Preset Name | Q | SA | A | Bi | Tri |
|-------------|---|----|---|----|-----|
| No Discount | 0% | 0% | 0% | 0% | 0% |
| Standard | 0% | 5% | 10% | 15% | 20% |
| Aggressive | 5% | 10% | 20% | 25% | 30% |

### 6.2 Preset Storage

```sql
CREATE TABLE IF NOT EXISTS tbl_hvn_pricing_presets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    discount_quarterly DECIMAL(5,2) DEFAULT 0.00,
    discount_semiannually DECIMAL(5,2) DEFAULT 0.00,
    discount_annually DECIMAL(5,2) DEFAULT 0.00,
    discount_biennially DECIMAL(5,2) DEFAULT 0.00,
    discount_triennially DECIMAL(5,2) DEFAULT 0.00,
    rounding_method ENUM('none','ceil','floor','round') DEFAULT 'round',
    rounding_precision DECIMAL(10,2) DEFAULT 1.00,
    is_default TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 7. Technical Architecture

### 7.1 Module Structure

```
modules/addons/hvn_pricing_calculator/
├── hvn_pricing_calculator.php       # Main addon file
│                                    #   - config(): module metadata
│                                    #   - activate(): create tables, seed presets
│                                    #   - deactivate(): cleanup
│                                    #   - output(): admin settings page
│                                    #   - upgrade(): schema migrations
│
├── hooks.php                        # Hook registrations
│                                    #   - AdminAreaHeaderOutput (CSS)
│                                    #   - AdminAreaFooterOutput (JS injection)
│                                    #   - AdminProductConfigFieldsSave (save pricing)
│
├── lib/
│   ├── PageDetector.php             # Detect admin page type and context
│   ├── Calculator.php               # Cycle calculation + currency conversion
│   ├── ConfigOptionsReader.php      # Read config options data from DB
│   ├── ConfigOptionsWriter.php      # Write pricing + hidden states
│   ├── PresetManager.php            # Preset CRUD operations
│   ├── CurrencyHelper.php           # Read tblcurrencies, conversion helpers
│   └── Renderer.php                 # Build HTML for toolbar + config manager
│
├── assets/
│   ├── js/
│   │   ├── hvn-pricing.js           # Main JS: toolbar, calculate, inject
│   │   └── config-options.js        # Config options manager interactions
│   └── css/
│       └── hvn-pricing.css          # All styles
│
├── templates/
│   ├── toolbar.phtml                # Pricing calculator toolbar HTML
│   ├── config-options-manager.phtml # Config options inline manager
│   ├── quick-create.phtml           # Quick create group form
│   ├── settings.phtml               # Module admin settings page
│   └── presets.phtml                # Preset management UI
│
└── lang/
    ├── english.php
    └── vietnamese.php
```

### 7.2 Hooks

| Hook | When | Purpose |
|------|------|---------|
| `AdminAreaHeaderOutput` | Every admin page | Load CSS if on target page |
| `AdminAreaFooterOutput` | Every admin page | Detect page → inject toolbar/manager JS+HTML |
| `AdminProductConfigFieldsSave` | Product save | Save inline pricing, hidden states, quick create |

### 7.3 Page Detection

```php
class PageDetector
{
    /**
     * Detect current admin page and determine which features to inject.
     * Returns null if page is not relevant.
     */
    public function detect(): ?array
    {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $action = $_REQUEST['action'] ?? '';

        return match(true) {
            // Product edit — multiple tabs
            $script === 'configproducts.php' && $action === 'edit' => [
                'page'       => 'product_edit',
                'product_id' => (int) ($_REQUEST['id'] ?? 0),
                'inject'     => [
                    'tab2' => 'pricing_calculator',
                    'tab5' => 'config_options_manager',
                ],
            ],

            // Configurable Options management
            $script === 'configproductoptions.php' => [
                'page'   => 'config_options',
                'inject' => ['pricing_calculator'],
            ],

            // Addon edit
            $script === 'configaddons.php' && $action === 'manage' => [
                'page'     => 'addon_edit',
                'addon_id' => (int) ($_REQUEST['id'] ?? 0),
                'inject'   => ['tab2' => 'pricing_calculator'],
            ],

            default => null,
        };
    }
}
```

### 7.4 WHMCS Tables Used

| Table | Access | Purpose |
|-------|--------|---------|
| `tblcurrencies` | **Read only** | Currency list + conversion rates |
| `tblpricing` | Read + Write | All pricing data |
| `tblproducts` | Read only | Product info for context |
| `tblproductconfiggroups` | Read + Write* | Config option groups (*write for Quick Create) |
| `tblproductconfiglinks` | Read + Write* | Group ↔ Product links (*write for Quick Create) |
| `tblproductconfigoptions` | Read + Write | Options metadata + hidden state |
| `tblproductconfigoptionssub` | Read + Write | Sub-options + hidden state |
| `tbladdons` | Read only | Addon info for context |
| `tbladdonmodules` | Read + Write | Module settings storage |
| `tbl_hvn_pricing_presets` | Read + Write | Custom table for discount presets |

### 7.5 JS Architecture

```
hvn-pricing.js
├── HvnPricing (main namespace)
│   ├── init()                    # Detect page, inject appropriate UI
│   ├── PageDetector              # Client-side page detection
│   │   ├── isProductPricing()
│   │   ├── isConfigOptions()
│   │   ├── isAddonPricing()
│   │   └── isProductConfigTab()
│   │
│   ├── Calculator                # Calculation engine
│   │   ├── calcCycles(base, discounts, rounding)
│   │   ├── calcCurrencies(sourceId, rates)
│   │   ├── calcAll()
│   │   └── undo()
│   │
│   ├── Toolbar                   # Toolbar UI management
│   │   ├── render(targetElement)
│   │   ├── bindEvents()
│   │   ├── loadPreset(presetId)
│   │   └── getSettings()
│   │
│   ├── PricingTable              # Parse/update pricing tables
│   │   ├── findInputs(container)
│   │   ├── groupByCurrency()
│   │   ├── readValues()
│   │   ├── writeValues()
│   │   └── highlightChanged()
│   │
│   └── ConfigManager             # Config options manager
│       ├── render(container)
│       ├── quickCreate(name)
│       └── savePricing()
│
└── Utilities
    ├── applyRounding(value, method, precision)
    ├── showNotification(message, type)
    └── createUndoSnapshot()
```

### 7.6 AJAX Endpoints

Via module output handler (`addonmodules.php?module=hvn_pricing_calculator`):

| Action Parameter | Method | Purpose | Response |
|------------------|--------|---------|----------|
| `action=get_presets` | GET | List all presets | JSON array |
| `action=save_preset` | POST | Create/update preset | JSON {success, id} |
| `action=delete_preset` | POST | Delete preset | JSON {success} |
| `action=quick_create_group` | POST | Create config group + assign to product | JSON {success, group_id} |
| `action=get_config_options` | GET | Fetch config options + pricing for product | JSON object |
| `action=get_currencies` | GET | Fetch currencies + rates | JSON array |

---

## 8. Module Settings Page

Admin page at `addonmodules.php?module=hvn_pricing_calculator`:

### 8.1 General Settings

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Auto-inject Toolbar | Toggle | On | Tự động hiện toolbar trên pricing pages |
| Default Preset | Dropdown | Standard | Preset mặc định khi mở toolbar |
| Show Currency Rates | Toggle | On | Hiện tỷ giá trong toolbar |
| Confirm Before Apply | Toggle | Off | Dialog xác nhận trước khi apply |
| Default Rounding | Dropdown | Round nearest | Phương thức làm tròn mặc định |
| Default Round To | Dropdown | 1 | Đơn vị làm tròn mặc định |

### 8.2 Preset Management

CRUD interface cho discount presets — bảng hiển thị tất cả presets, nút Add/Edit/Delete, chọn preset mặc định.

---

## 9. Edge Cases & Validation

| Case | Handling |
|------|----------|
| Monthly = 0.00 | Skip cycle calculation, keep 0 (free tier) |
| Monthly = -1.00 | Skip entirely, keep -1 (disabled cycle) |
| Currency rate = 0 | Show warning toast, skip conversion for that currency |
| No default currency in WHMCS | Use first currency in tblcurrencies |
| Product has no assigned config groups | Show Quick Create form on tab 5 |
| Product has multiple config groups | Show all groups, each in separate panel |
| Config group shared across multiple products | Info badge: "Shared with X products" |
| WHMCS admin theme changes DOM | Use flexible selectors, test per WHMCS version |
| Setup fee fields | Convert currency only, never multiply by cycle |
| Admin manually edits cell after calculate | Remove yellow highlight on that cell |
| Page loaded via AJAX (WHMCS tab switching) | Re-detect and re-inject on tab activation |
| Very large numbers (VND) | Support Round to 1,000 and 10,000 |

---

## 10. Compatibility

| Requirement | Specification |
|-------------|---------------|
| WHMCS Version | 8.0+ |
| PHP Version | 8.0+ |
| Database | MySQL 5.7+ / MariaDB 10.3+ |
| Dependencies | None (vanilla JS, PHP, WHMCS Database\Capsule) |
| External Libraries | None required |
| Admin Theme | WHMCS default admin theme (Six / Eight) |
| Server Modules | Works with all (module-agnostic) |
| Currencies | Unlimited (reads from WHMCS settings) |
| Languages | English, Vietnamese (extensible) |

---

## 11. Development Phases

### Phase 1 — Pricing Calculator MVP (2-3 weeks)

- [ ] Module skeleton (`hvn_pricing_calculator.php` — activate, deactivate, config, output)
- [ ] `PageDetector` — detect Product Pricing, ConfigOptions, Addon Pricing pages
- [ ] `CurrencyHelper` — read `tblcurrencies`, get rates, get default
- [ ] `Calculator` — cycle calculation from Monthly, currency conversion
- [ ] Toolbar HTML/CSS — inject vào Product Pricing tab
- [ ] Toolbar JS — calculate cycles, calculate currencies, calc all
- [ ] Inline discount inputs (no presets yet)
- [ ] Rounding options (none, ceil, floor, round × precision)
- [ ] Visual feedback (highlight cells, toast notification)
- [ ] Undo (1 level, snapshot before calculate)
- [ ] Support Addon Pricing page
- [ ] Support Configurable Options page (`configproductoptions.php`)

### Phase 2 — Config Options Manager (2 weeks)

- [ ] `ConfigOptionsReader` — read groups, options, sub-options, pricing
- [ ] `ConfigOptionsWriter` — save pricing, hidden states
- [ ] Inline Config Options Manager on Product tab 5
- [ ] Multi-currency tabs with pricing table
- [ ] Pricing Calculator embedded in Config Options Manager
- [ ] Quick Create group + assign to product (AJAX)
- [ ] Assign existing group (AJAX)
- [ ] Save via `AdminProductConfigFieldsSave` hook
- [ ] Shared group warning badge

### Phase 3 — Presets & Polish (1-2 weeks)

- [ ] Custom table `tbl_hvn_pricing_presets`
- [ ] Preset CRUD (settings page UI)
- [ ] Preset selector dropdown in toolbar
- [ ] Default presets seeded on activation
- [ ] Module settings page (general settings + preset management)
- [ ] Confirm dialog option before batch apply
- [ ] Language files (English, Vietnamese)

### Phase 4 — Advanced Features (1-2 weeks)

- [ ] Copy pricing: Product A → Product B (with optional multiplier)
- [ ] Bulk apply: select multiple products → apply same pricing formula
- [ ] Pricing change audit log (optional table)
- [ ] Export/import pricing templates (CSV/JSON)
- [ ] Keyboard shortcuts (Ctrl+Shift+C = Calc All)
- [ ] Domain Pricing support (`configdomains.php`) — if needed

---

## 12. Success Metrics

| Metric | Before (Manual) | After (Module) |
|--------|-----------------|----------------|
| Time to price 1 product (6 cycles × 2 currencies) | 5-10 minutes | Under 30 seconds |
| Pricing calculation errors | Frequent | Zero (automated) |
| Pages needed to manage product + config options pricing | 3-4 pages | 1 page |
| Currency conversion accuracy | Manual (error-prone) | 100% (auto from WHMCS rates) |
| Time to add new currency pricing to all products | Hours | Minutes (bulk apply) |

---

## 13. Risks & Mitigations

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| WHMCS update changes admin DOM | Toolbar injection breaks | Medium | Flexible CSS selectors, version-specific fallbacks, test on each update |
| Incorrect rounding causes billing issues | Customer billed wrong | Low | Preview before save, undo support, confirm dialog |
| Currency rate stale/wrong | Wrong price conversion | Low | Display rate in toolbar, admin verifies before save |
| Conflict with other admin modules | Duplicate UI elements | Low | Unique DOM IDs, check-before-inject guard |
| Performance with many config options | Slow page load | Low | Lazy-load config options data, paginate if >50 sub-options |

---

## 14. Future Considerations

- **Real-time FX rates**: Fetch from API (exchangerate-api, openexchangerates) instead of manual WHMCS rates
- **Cost-based pricing**: Define cost price + markup % instead of just discount from Monthly
- **Pricing rules engine**: If Monthly > X, apply different discount tiers
- **Integration with billing**: Auto-detect price changes and flag affected active services
- **White-label**: Rebrandable for WHMCS marketplace distribution

---

## 15. Open Questions

1. **Base cycle flexibility** — Should the module support Annually as base (for annual-only products) in Phase 1 or Phase 2?
2. **Domain pricing** — `configdomains.php` has a very different structure (TLD-based, register/transfer/renew). Include in scope or separate module?
3. **Permission control** — Should all WHMCS admins have access, or add role-based permissions?
4. **Marketplace distribution** — Will this be published on WHMCS Marketplace? If yes, need to follow WHMCS module guidelines strictly.
5. **Setup fee calculation** — Should setup fees also have discount options, or always flat rate?
6. **Existing pricing protection** — When calculating, should the module skip cycles that already have non-zero pricing (prevent accidental overwrite)?
