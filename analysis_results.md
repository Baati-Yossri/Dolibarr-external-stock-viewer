# ClientStock Module — Enhancement Analysis

After reviewing every file in the module, here's what I found — organized from **highest impact** to nice-to-haves.

---

## 🔴 Security & Robustness (Should Fix)

### 1. CSRF Protection Missing on `setup.php` DELETE action
The delete link uses a GET request with `token=` in the URL, but the token is **never validated** on the server side. Anyone with the URL could delete access rows.
- **Fix**: Add `if (!verifCond($action == 'delete')) { ... }` or check `newToken()` vs submitted token.

### 2. SQL Injection Surface in `clientstock_list.php`
Line 316 — if `$allowed_entrepots` were somehow manipulated (unlikely since it comes from DB, but defensive coding):
```php
$sql .= " WHERE ps.fk_entrepot IN (" . implode(',', $allowed_entrepots) . ")";
```
- Already cast to `(int)` on insert ✅, but the array values themselves aren't cast at the `implode` point. Adding `array_map('intval', ...)` would be belt-and-suspenders safe.

### 3. No `htmlspecialchars()` on Admin Page Output
In [setup.php](file:///c:/dolibarr/www/technoprod/custom/clientstock/admin/setup.php) lines 117-118, `$obj->client_name` and `$obj->warehouse_ref` are printed raw — XSS risk if a company name contains `<script>`.

---

## 🟠 Functional Enhancements (High Value)

### 4. Stock Summary Dashboard / KPI Cards
Currently the stock page jumps straight into tables. Adding **summary KPI cards** at the top would give clients an instant overview:
- **Total Products** in stock
- **Total Warehouses** accessible
- **Total Physical Stock** (sum of all quantities)
- **Active Reservations** count

### 5. Sortable Table Columns
Neither the stock table nor the OF table supports **column sorting** (by ref, label, qty, warehouse). Adding clickable column headers with JS sorting would be very useful for clients with many products.

### 6. Export to CSV/Excel
Clients often need to download their stock data. Adding an **"Export CSV"** button above the stock table would be a high-value feature — just a PHP endpoint that outputs `Content-Type: text/csv` with the same SQL query.

### 7. Pagination
Both pages load **all rows at once**. For clients with hundreds of products or OFs, this could become slow. Adding basic pagination (`LIMIT/OFFSET`) with page navigation would improve performance and UX.

### 8. Last Updated Timestamp
Show a **"Données mises à jour le: ..."** timestamp so clients know how fresh the data is. Could use the `tms` column from `product_stock` or simply display `NOW()`.

---

## 🟡 UX & Visual Improvements (Medium Value)

### 9. Empty State Improvements
When there's no data, the empty states are plain text. Adding an **icon + message + call-to-action** (e.g., a stock icon with "Aucun stock trouvé") would look more polished.

### 10. Reservation Card — Clickable / Expandable Detail
The reservation cards show order ref + product list, but there's no way to see **component-level reservation detail**. Adding an expandable/collapsible section per card showing the BOM components and their reservation status would give clients full transparency.

### 11. Color-Coded Stock Levels
Highlight stock quantities with color coding:
- **Red** for low/critical stock (e.g., < 10 units)
- **Orange** for medium
- **Green** for healthy stock levels
- Thresholds could be configurable in admin settings.

### 12. Mobile Responsiveness
The reservation cards use `flex: 1 1 calc(25% - 16px)` with `min-width: 250px`, which is decent, but the **tables** (`liste centpercent`) may not scroll well on mobile. Wrapping tables in a horizontally scrollable container would help.

### 13. Print-Friendly View
Add a **"Print"** button or `@media print` CSS so clients can print their stock list or OF status cleanly.

---

## 🟢 Admin & Configuration Improvements

### 14. Bulk Access Management
The admin [setup.php](file:///c:/dolibarr/www/technoprod/custom/clientstock/admin/setup.php) only allows adding **one client ↔ warehouse** pair at a time. Adding:
- **Multi-select** for warehouses (assign multiple warehouses to one client at once)
- **"Clone access from another client"** button
- **Bulk delete** with checkboxes

### 15. Admin Preview Banner
When an admin views `clientstock_list.php` or `clientstock_of.php`, they see ALL data but there's no clear indication they're in **admin preview mode**. Adding a visible banner like *"⚠️ Mode aperçu administrateur — Vue de tous les clients"* would prevent confusion.

### 16. Delete Confirmation Dialog
In `setup.php`, clicking the delete icon immediately removes the access with no confirmation. Adding a JavaScript `confirm()` dialog or Dolibarr's built-in confirmation would prevent accidental deletions.

### 17. Activity Log / Audit Trail
Track when access rows are created/deleted and by whom. The `fk_user_creat` and `fk_user_modif` columns exist in the SQL schema but are **never populated** in `setup.php`.

---

## 🔵 Code Quality & Architecture

### 18. Duplicate Badge Functions
`get_prod_status_badge()` and `get_control_status_badge()` in `clientstock_of.php` are **identical functions**. They should be consolidated into one.

### 19. Shared Badge Function Between Files
`get_reservation_status_badge()` in `clientstock_list.php` follows a similar pattern. All badge functions could be moved to a shared `lib/clientstock.lib.php` file.

### 20. Module Descriptor — Missing `init()` SQL Execution
The `init()` method in [modClientStock.class.php](file:///c:/dolibarr/www/technoprod/custom/clientstock/core/modules/modClientStock.class.php) passes an empty `$sql` array. The table creation SQL files in `/sql/` are never auto-executed on module activation. Adding them to `$this->_init()` would ensure clean installs.

### 21. Missing `en_US` Language File
Only `fr_FR` translations exist. Adding an `en_US/clientstock.lang` file would support international Dolibarr installations.

---

## 💡 Advanced Feature Ideas (Future)

| Feature | Description |
|---------|-------------|
| **Stock Movement History** | Show recent in/out movements for each product in the client's warehouses |
| **Email Notifications** | Alert clients when their stock drops below a threshold |
| **Stock Reservation Detail View** | Drill-down page showing BOM-level reservation breakdown per order |
| **Dashboard Graphs** | Chart showing stock evolution over time (using Dolibarr's built-in chart libs) |
| **Multi-language Support** | `en_US` lang file + dynamic language detection |

---

## Recommended Priority Order

> [!TIP]
> If you want to tackle these, I'd suggest this order for maximum impact with minimum effort:

1. **Security fixes** (#1, #2, #3) — quick wins, critical
2. **Delete confirmation** (#16) — 2 lines of JS
3. **Admin preview banner** (#15) — quick visual improvement
4. **Sortable columns** (#5) — high UX value
5. **Export CSV** (#6) — clients will love this
6. **KPI summary cards** (#4) — polished first impression
7. **Pagination** (#7) — needed as data grows

Let me know which items you'd like me to implement!
