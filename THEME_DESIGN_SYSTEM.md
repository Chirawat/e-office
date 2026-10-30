# N Academy Enterprise ERP & Financial UI Design System
> **ไฟล์มาตรฐานการออกแบบ (Theme Specification & Design System)** สำหรับระบบบริหารโรงเรียนกวดวิชา N Academy
> ใช้สำหรับอ้างอิง (Reference) หรือคัดลอกใส่ใน AI Prompt ในการสร้างหน้าจอและพัฒนาต่อยอดในแชทอื่น

---

## 1. ข้อมูลภาพรวม (Overview & Philosophy)

- **Theme Name**: N Academy Deep Navy Enterprise ERP & PEAK Accounting Style
- **Core Concept**: 
  - เรียบหรู ชัดเจน สไตล์ Enterprise ERP ผสมผสานระบบบัญชีสมัยใหม่ (PEAK-inspired)
  - ความหนาแน่นของข้อมูลสูง (High-Density / Compact Layout) เพื่อให้ผู้บริหารและครูเห็นข้อมูลสำคัญครบในหน้าเดียวโดยไม่ต้องเลื่อนเยอะ
  - ใช้สีหลักเป็น **Deep Navy Blue** (`#084b96`) ตัดขอบด้วย **Sky Cyan** (`#38bdf8`) และพื้นหลัง **Soft Gray** (`#edf2f7`) เพื่อความสบายตาในการทำงานต่อเนื่องทั้งวัน
- **Primary Frameworks**:
  - Bootstrap 5.3+ (CSS/JS)
  - Bootstrap Icons (`bi bi-*`)
  - Google Fonts: **Prompt** (หัวข้อ/ความคมชัด) + **Sarabun** (เนื้อหาภาษาไทยมาตรฐาน)

---

## 2. โค้ดสีและตัวแปรหลัก (Color Tokens & CSS Variables)

```css
:root {
    /* Brand Navy Theme */
    --erp-blue-dark: #003366;          /* กรมท่าน้ำเงินเข้มลึก */
    --erp-blue-primary: #084b96;       /* กรมท่าหลักของระบบ */
    --erp-blue-light: #0d6efd;         /* น้ำเงินสว่าง */
    --erp-blue-accent: #38bdf8;        /* ฟ้าสดใส (Sky Cyan Accent) */

    /* พื้นหลังและเส้นแบ่ง */
    --erp-bg-body: #edf2f7;            /* พื้นหลังหน้าเว็บ Soft Gray */
    --erp-bg-card: #ffffff;            /* การ์ดสีขาวบริสุทธิ์ */
    --erp-border-color: #dce4ec;       /* เส้นขอบการ์ดและตาราง */
    --erp-border-light: #f1f5f9;       /* เส้นขอบภายในเซลล์ */

    /* มิติโครงสร้าง (Layout Dimensions) */
    --erp-topbar-height: 52px;
    --erp-sidebar-width: 230px;
    --erp-statusbar-height: 28px;

    /* สีเชิงความหมาย (Semantic Colors) */
    --erp-revenue: #0284c7;            /* รายรับ / รายได้ (Sky Blue) */
    --erp-revenue-bg: #e0f2fe;
    --erp-savings: #10b981;            /* เงินออม / สำรอง (Emerald Green) */
    --erp-savings-bg: #ecfdf5;
    --erp-expense: #ef4444;            /* รายจ่าย (Coral Red) */
    --erp-expense-bg: #fef2f2;
    --erp-warning: #f59e0b;            /* รายจ่ายคงที่ / รอชำระ (Amber) */
    --erp-warning-bg: #fffbeb;
    --erp-text-main: #1e293b;          /* ตัวอักษรสีเข้ม (Dark Slate) */
    --erp-text-muted: #64748b;         /* ตัวอักษรสีเทารอง */
}
```

---

## 3. ระบบตัวอักษร (Typography)

- **Font Family**: 
  ```css
  font-family: 'Prompt', 'Sarabun', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  ```
- **CDN สำหรับแทรกใน `<head>`**:
  ```html
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  ```
- **ขนาดตัวอักษรมาตรฐาน**:
  - `body.erp-theme`: `0.9rem` (14.4px)
  - `table` เนื้อหาตาราง: `0.8125rem` (13px)
  - `table thead th`: `0.78rem` (12.5px), ตัวหนา `font-weight: 600`
  - `badge / sub-label`: `0.70rem - 0.75rem`
  - `ตัวเลขเงิน / รหัส`: ใช้ `font-monospace` และ `font-variant-numeric: tabular-nums` ชิดขวา (`text-end`)

---

## 4. โครงสร้างเค้าโครงหลัก 3 ระดับ (3-Tier Layout Architecture)

```
+-----------------------------------------------------------------------+
|  1. Topbar Header (52px, Deep Navy Gradient, Ribbon Menu, User Info)  |
+---------------------+-------------------------------------------------+
|                     |  Breadcrumb Navigation                          |
|  2. Sidebar Nav     |-------------------------------------------------|
|     (230px Fixed)   |  Main Content Workspace                         |
|     - หน้าหลัก      |  - KPI Summary Metric Cards                     |
|     - จัดการ        |  - Filter Bar / Search                          |
|     - รายงาน        |  - Compact ERP Tables                           |
|     (Collapsible)   |                                                 |
+---------------------+-------------------------------------------------+
|  3. Statusbar Footer (28px Fixed, Green Pulse Status, Live Clock)     |
+-----------------------------------------------------------------------+
```

### โครงสร้าง HTML สำหรับ Layout Template:
```html
<body class="erp-theme d-flex flex-column min-vh-100">

  <!-- 1. Topbar Header -->
  <header class="app-topbar fixed-top d-flex align-items-center justify-content-between px-3">
    <div class="d-flex align-items-center gap-2">
      <button type="button" class="btn btn-topbar-icon" id="btnToggleSidebar">
        <i class="bi bi-list fs-5"></i>
      </button>
      <div class="brand-badge-icon">
        <i class="bi bi-mortarboard-fill"></i>
      </div>
      <div class="brand-text text-white">
        <span class="fw-bold fs-6">ระบบ N Academy</span>
      </div>
    </div>
    <!-- User Capsule -->
    <div class="btn-user-capsule d-flex align-items-center gap-2 text-white">
      <span class="user-avatar-badge">A</span>
      <span class="user-name">Admin</span>
    </div>
  </header>

  <!-- 2. Main Wrapper -->
  <div class="app-body-wrapper d-flex flex-grow-1">
    <!-- Sidebar -->
    <aside id="app-sidebar" class="app-sidebar bg-white border-end">
      <!-- Sidebar items -->
    </aside>

    <!-- Main Content -->
    <main class="app-main-content flex-grow-1">
      <div class="container-fluid px-3 px-md-4 py-3">
        <!-- Content Goes Here -->
      </div>
    </main>
  </div>

  <!-- 3. Statusbar Footer -->
  <footer class="app-statusbar fixed-bottom d-flex align-items-center justify-content-between px-3 text-white">
    <div class="d-flex align-items-center gap-2 font-monospace small">
      <span class="status-dot-green"></span>
      <span>พร้อมใช้งาน</span>
    </div>
    <div class="font-monospace small text-info" id="live-statusbar-clock">
      --:--:--
    </div>
  </footer>
</body>
```

---

## 5. คอมโพเนนต์มาตรฐานที่ใช้งานซ้ำ (Reusable UI Components)

### 5.1 ตารางกะทัดรัดแบบ Enterprise ERP (Compact ERP Table)
ตารางหัวสี Deep Navy พร้อม Gradient ตัวหนังสือสีขาว ปรับขนาดช่องให้กะทัดรัด (Padding `5px 8px`) และมีเส้นสลับสีม้าลาย (Zebra Striping):

```html
<div class="card card-table-wrap border-0 shadow-sm mb-4">
  <!-- Table Header Banner -->
  <div class="card-header table-header-banner">
    <h5>
      <i class="bi bi-table text-white me-1"></i>
      รายชื่อและข้อมูลธุรกรรม
    </h5>
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-white text-primary fw-bold">หน่วย: บาท (THB)</span>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th class="ps-3" style="width: 120px;">วันที่</th>
            <th>หมวดหมู่</th>
            <th>รายละเอียด</th>
            <th class="text-end" style="width: 140px;">จำนวนเงิน (บาท)</th>
            <th class="text-center" style="width: 100px;">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="ps-3 font-monospace text-secondary">2026-10-29</td>
            <td class="fw-bold text-dark">ซื้อขนมเพื่อขาย</td>
            <td>ค่าขนมเข้าร้าน B2S</td>
            <td class="text-end font-monospace text-danger fw-semibold">1,063.00</td>
            <td class="text-center">
              <button class="btn btn-sm btn-outline-primary py-0 px-2">ดู</button>
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="table-summary">
            <td colspan="3" class="ps-3 fw-bold">รวมทั้งสิ้น</td>
            <td class="text-end font-monospace fw-bold text-primary">1,063.00</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
```

---

### 5.2 การ์ดตัวชี้วัด KPI (Metric Cards with Border Accent)
การ์ดสรุปสถิติด้านบนของหน้าจอ เน้นแถบสีหนา 4px ทางด้านซ้าย:

```html
<div class="row g-3 mb-4">
  <!-- Blue / Revenue -->
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card metric-card metric-card-blue border-0 shadow-sm rounded-3 h-100 bg-white">
      <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="text-muted small fw-semibold">รายรับรวม</span>
          <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-2">Income</span>
        </div>
        <div class="fs-4 fw-bold text-dark font-monospace">฿14,210.00</div>
        <div class="small text-muted mt-1">100% ฐานรายรับ</div>
      </div>
    </div>
  </div>

  <!-- Green / Savings -->
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card metric-card metric-card-green border-0 shadow-sm rounded-3 h-100 bg-white">
      <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="text-muted small fw-semibold">เงินออมสำรอง</span>
          <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2">Savings</span>
        </div>
        <div class="fs-4 fw-bold text-success font-monospace">฿2,000.00</div>
        <div class="small text-muted mt-1">14.07% ของรายรับ</div>
      </div>
    </div>
  </div>

  <!-- Red / Expenses -->
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card metric-card metric-card-red border-0 shadow-sm rounded-3 h-100 bg-white">
      <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="text-muted small fw-semibold">รายจ่ายรวม</span>
          <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2">Expense</span>
        </div>
        <div class="fs-4 fw-bold text-danger font-monospace">฿7,500.00</div>
        <div class="small text-muted mt-1">52.78% ของรายรับ</div>
      </div>
    </div>
  </div>
</div>
```

---

### 5.3 ตาราง P&L 12 เดือนพร้อม Sticky Column ซ้าย & Popover Hover Breakdown
รองรับการเลื่อนดูแนวนอน (Horizontal Scroll) โดยคอลัมน์แรกติดตรึงไว้ ไม่บังหรือทะลุพื้นหลัง:

```css
/* Sticky Left Column */
.sticky-col-left {
    position: sticky !important;
    left: 0;
    z-index: 10 !important;
    background-color: #ffffff;
    box-shadow: 3px 0 6px -2px rgba(0, 0, 0, 0.12);
    border-right: 2px solid #cbd5e1 !important;
}

.pl-table thead tr th.sticky-col-left {
    z-index: 20 !important;
    background: linear-gradient(180deg, #094f9b 0%, #063c78 100%) !important;
    color: #ffffff !important;
}

/* Hover cell breakdown popup */
.cell-breakdown-hover {
    cursor: pointer;
    transition: background-color 0.15s ease, transform 0.1s ease;
}
.cell-breakdown-hover:hover {
    background-color: #e0f2fe !important;
    font-weight: bold;
}
```

---

### 5.4 ปฏิทินตารางเรียนรายสัปดาห์ (Weekly Calendar Google Style)
- แสดงสัปดาห์โดยมี **วันอาทิตย์เป็นวันแรก** (อา. จ. อ. พ. พฤ. ศ. ส.)
- จัดกลุ่มคอร์สเรียนเวลาเดียวกันให้อยู่แถวเดียวกันแบบ Google Calendar:

```html
<div class="table-responsive">
  <table class="table table-bordered calendar-weekly-table text-center align-middle">
    <thead class="table-dark">
      <tr style="background: #084b96;">
        <th style="width: 120px;">ช่วงเวลา</th>
        <th class="text-danger">อาทิตย์</th>
        <th class="text-warning">จันทร์</th>
        <th class="text-pink">อังคาร</th>
        <th class="text-success">พุธ</th>
        <th class="text-orange">พฤหัสบดี</th>
        <th class="text-info">ศุกร์</th>
        <th class="text-purple">เสาร์</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="fw-bold bg-light text-secondary font-monospace">09:00 - 12:00 น.</td>
        <!-- เซลล์คอร์สเรียนแบบ Card ซ้อน -->
        <td>
          <div class="course-cal-card p-2 rounded shadow-xs text-white" style="background-color: #0284c7;">
            <div class="fw-bold small">คณิตศาสตร์ ม.1</div>
            <div class="font-monospace" style="font-size: 0.7rem;">09:00 - 12:00</div>
          </div>
        </td>
        <td>-</td>
        <td>-</td>
        <td>-</td>
        <td>-</td>
        <td>-</td>
        <td>-</td>
      </tr>
    </tbody>
  </table>
</div>
```

---

## 6. CSS หลักฉบับรวม (Core CSS Bundle)

นำโค้ดด้านล่างนี้ไปวางในไฟล์ CSS ของโปรเจกต์ (`site.css` หรือ `<style>`):

```css
/* ==========================================================================
   ENTERPRISE ERP THEME (N ACADEMY SCHOOL MANAGEMENT PORTAL)
   ========================================================================== */

:root {
    --erp-blue-dark: #003366;
    --erp-blue-primary: #084b96;
    --erp-blue-light: #0d6efd;
    --erp-blue-accent: #38bdf8;
    --erp-bg-body: #edf2f7;
    --erp-sidebar-width: 230px;
    --erp-topbar-height: 52px;
    --erp-statusbar-height: 28px;
    --erp-border-color: #dce4ec;
}

body.erp-theme {
    font-family: 'Prompt', 'Sarabun', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background-color: var(--erp-bg-body);
    color: #1e293b;
    margin: 0;
    padding: 0;
    font-size: 0.9rem;
    overflow-x: hidden;
}

/* TOPBAR */
.app-topbar {
    height: var(--erp-topbar-height);
    background: linear-gradient(90deg, #063c78 0%, #084b96 50%, #0a56aa 100%);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    z-index: 1040;
}

.brand-badge-icon {
    width: 32px;
    height: 32px;
    background-color: #ffffff;
    color: var(--erp-blue-primary);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
}

.btn-topbar-icon {
    width: 32px;
    height: 32px;
    color: #ffffff;
    background: transparent;
    border: none;
    border-radius: 4px;
}

.btn-user-capsule {
    background-color: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 20px;
    padding: 2px 10px 2px 3px;
}

.user-avatar-badge {
    width: 24px;
    height: 24px;
    background-color: #ffffff;
    color: var(--erp-blue-primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.75rem;
}

/* SIDEBAR & CONTENT */
.app-body-wrapper {
    margin-top: var(--erp-topbar-height);
    margin-bottom: var(--erp-statusbar-height);
    min-height: calc(100vh - var(--erp-topbar-height) - var(--erp-statusbar-height));
    display: flex;
}

.app-sidebar {
    width: var(--erp-sidebar-width);
    position: fixed;
    top: var(--erp-topbar-height);
    bottom: var(--erp-statusbar-height);
    left: 0;
    z-index: 1020;
    border-right: 1px solid var(--erp-border-color);
    background-color: #ffffff;
    transition: transform 0.2s ease-in-out;
}

.app-main-content {
    margin-left: var(--erp-sidebar-width);
    flex-grow: 1;
    background-color: var(--erp-bg-body);
    transition: margin-left 0.2s ease-in-out;
    min-width: 0;
}

body.sidebar-collapsed .app-sidebar {
    transform: translateX(-100%);
}
body.sidebar-collapsed .app-main-content {
    margin-left: 0 !important;
}

/* STATUSBAR */
.app-statusbar {
    height: var(--erp-statusbar-height);
    background-color: #063c78;
    z-index: 1030;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
}

.status-dot-green {
    width: 7px;
    height: 7px;
    background-color: #22c55e;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 4px #22c55e;
}

/* COMPACT TABLES */
.card-table-wrap {
    border: 1px solid var(--erp-border-color) !important;
    border-radius: 6px !important;
    overflow: hidden;
}

.table-header-banner {
    background: linear-gradient(90deg, #063c78 0%, #084b96 60%, #0a56aa 100%) !important;
    color: #ffffff !important;
    padding: 7px 12px !important;
    font-size: 0.84rem !important;
    font-weight: 600 !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

body.erp-theme .table {
    font-size: 0.8125rem !important;
    color: #1e293b;
    border-collapse: collapse;
}

body.erp-theme .table > :not(caption) > * > * {
    padding: 5px 8px !important;
    vertical-align: middle;
    border-bottom: 1px solid #e2e8f0;
    border-right: 1px solid #f1f5f9;
}

body.erp-theme .table thead th {
    background: linear-gradient(180deg, #094f9b 0%, #063c78 100%) !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    font-size: 0.78rem !important;
    white-space: nowrap;
    border-right: 1px solid rgba(255, 255, 255, 0.15) !important;
}

body.erp-theme .table tbody tr:nth-of-type(even) {
    background-color: #f8fafc !important;
}

body.erp-theme .table tbody tr:hover {
    background-color: #edf4fc !important;
}

/* KPI CARDS */
.metric-card-blue { border-left: 4px solid #0284c7 !important; }
.metric-card-green { border-left: 4px solid #10b981 !important; }
.metric-card-orange { border-left: 4px solid #f59e0b !important; }
.metric-card-red { border-left: 4px solid #ef4444 !important; }
```

---

## 7. คำสั่ง Prompt สำหรับนำไปใช้งานต่อใน AI Chat อื่น (AI Prompt Snippet)

สามารถคัดลอกข้อความด้านล่างนี้ ส่งให้ AI ในแชทใหม่ได้ทันที:

```markdown
กรุณาออกแบบและเขียนโค้ดหน้า UI ตามคู่มือดีไซน์ "N Academy Enterprise ERP & Financial Theme":
1. โทนสีหลัก: Deep Navy Blue (#084b96 / #063c78) ขอบฟ้าสดใส (#38bdf8) พื้นหลังบอดี้สีเทาอ่อน (#edf2f7) และการ์ดสีขาว (#ffffff)
2. ฟอนต์: 'Prompt' สำหรับหัวข้อ และ 'Sarabun' สำหรับเนื้อหาภาษาไทย พร้อม Bootstrap Icons
3. สไตล์ตาราง (Compact Table):
   - หัวตาราง (thead) สี Deep Navy ตัวหนังสือสีขาว ขอบมน
   - ความสูงแถวกะทัดรัด (padding: 5px 8px, font-size: ~13px / 0.8125rem)
   - สลับแถวสีม้าลาย (even: #f8fafc) และไฮไลต์เมื่อนำเมาส์ชี้ (#edf4fc)
   - ตัวเลขเงินและรหัส ใช้ font-monospace จัดชิดขวา
4. ด้านบนมี KPI Metric Cards แถบสี 4px ทางซ้าย (ฟ้า=รายรับ, เขียว=เงินออม, แดง=รายจ่าย)
5. สไตล์เค้าโครง: เป็นแบบ 3-Tier Layout (Topbar 52px, Sidebar 230px พับได้, Statusbar ล่าง 28px พร้อมจุดเขียวสถานะ)
```
