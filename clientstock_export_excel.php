<?php
/**
 * Excel export logic for ClientStock
 * Expected variables in scope: $socid, $allowed_entrepots, $search_entrepot, $search_keyword, $db, $langs
 */

$csv_where = "";
if ($socid > 0) {
    $csv_where .= " WHERE ps.fk_entrepot IN (" . implode(',', array_map('intval', $allowed_entrepots)) . ")";
    $csv_where .= " AND ps.reel != 0";
} else {
    $csv_where .= " INNER JOIN " . MAIN_DB_PREFIX . "clientstock_access as ca ON ps.fk_entrepot = ca.fk_entrepot";
    $csv_where .= " WHERE ps.reel != 0";
}

if ($search_entrepot > 0) {
    $csv_where .= " AND ps.fk_entrepot = " . (int) $search_entrepot;
}

if (!empty($search_keyword)) {
    $search_esc = $db->escape(trim($search_keyword));
    $csv_where .= " AND (p.ref LIKE '%" . $search_esc . "%' OR p.label LIKE '%" . $search_esc . "%' OR e.ref LIKE '%" . $search_esc . "%' OR e.description LIKE '%" . $search_esc . "%')";
}

$csv_sql = "SELECT p.ref, p.label, e.ref as warehouse_ref, e.description as warehouse_label, ps.reel as stock_physique";
$csv_sql .= " FROM " . MAIN_DB_PREFIX . "product_stock as ps";
$csv_sql .= " INNER JOIN " . MAIN_DB_PREFIX . "product as p ON ps.fk_product = p.rowid";
$csv_sql .= " INNER JOIN " . MAIN_DB_PREFIX . "entrepot as e ON ps.fk_entrepot = e.rowid";
$csv_sql .= $csv_where;
$csv_sql .= " ORDER BY e.description ASC, p.ref ASC";

$resql_csv = $db->query($csv_sql);
if ($resql_csv) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="stock_export_' . date('Y-m-d_His') . '.xls"');
    
    $html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    $html .= '<head><meta charset="utf-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Stock</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
    $html .= '<body style="font-family: \'Segoe UI\', Arial, sans-serif; font-size: 11pt; color: #334155;">';
    $html .= '<table border="1" cellpadding="8" style="border-collapse: collapse; border: 1px solid #cbd5e1;">';
    
    // Main Title Row
    $html .= '<tr style="background-color: #f8fafc;">';
    $html .= '<th colspan="4" style="font-size: 18pt; font-weight: bold; color: #0f172a; text-align: left; padding: 20px 15px 5px 15px; border-bottom: none;">';
    $html .= '📦 ' . $langs->trans("MyStock");
    $html .= '</th>';
    $html .= '</tr>';
    
    // Date/Subtitle Row
    $html .= '<tr style="background-color: #f8fafc;">';
    $html .= '<th colspan="4" style="font-size: 10pt; font-weight: normal; color: #64748b; text-align: left; padding: 0 15px 20px 15px; border-top: none;">';
    $html .= 'Généré le: ' . dol_print_date(dol_now(), 'dayhour');
    $html .= '</th>';
    $html .= '</tr>';
    
    // Header row with styling and fixed widths
    $html .= '<tr style="background-color: #1e293b; color: #ffffff; font-weight: bold; text-align: left; height: 40px;">';
    $html .= '<th style="width: 150px; padding: 10px; border: 1px solid #475569;">' . $langs->trans("ProductRef") . '</th>';
    $html .= '<th style="width: 700px; padding: 10px; border: 1px solid #475569;">' . $langs->trans("ProductLabel") . '</th>';
    $html .= '<th style="width: 250px; padding: 10px; border: 1px solid #475569;">' . $langs->trans("Warehouse") . '</th>';
    $html .= '<th style="width: 120px; padding: 10px; border: 1px solid #475569; text-align: right;">' . $langs->trans("PhysicalStock") . '</th>';
    $html .= '</tr>';
    
    $row_num = 0;
    while ($obj_csv = $db->fetch_object($resql_csv)) {
        $stock_val = (float) round($obj_csv->stock_physique, 4);
        
        // Apply color coding for stock levels (darker colors for readability on white/gray)
        $color = '#15803d'; // healthy
        if ($stock_val <= 0) $color = '#b91c1c'; // critical
        elseif ($stock_val < 10) $color = '#c2410c'; // low
        elseif ($stock_val < 50) $color = '#a16207'; // medium
        
        // Alternating row colors
        $bg_color = ($row_num % 2 == 0) ? '#ffffff' : '#f8fafc';
        $row_num++;
        
        $html .= '<tr style="background-color: ' . $bg_color . '; height: 32px;">';
        $html .= '<td style="border: 1px solid #cbd5e1; padding: 8px;">' . htmlspecialchars($obj_csv->ref) . '</td>';
        $html .= '<td style="border: 1px solid #cbd5e1; padding: 8px;">' . htmlspecialchars($obj_csv->label) . '</td>';
        $html .= '<td style="border: 1px solid #cbd5e1; padding: 8px;">' . htmlspecialchars($obj_csv->warehouse_ref . ' - ' . $obj_csv->warehouse_label) . '</td>';
        $html .= '<td style="border: 1px solid #cbd5e1; padding: 8px; text-align: right; color: ' . $color . '; font-weight: bold;">' . $stock_val . '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    $html .= '</body></html>';
    
    echo $html;
    $db->close();
    exit;
}
