<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

$langs->loadLangs(array("clientstock@clientstock", "products", "stocks"));

// Access control
if (empty($user->rights->clientstock->read))
    accessforbidden();

$socid = $user->socid;

llxHeader('', $langs->trans("MyStock"));

print load_fiche_titre($langs->trans("MyStock"), '', 'object_stock');

if ($socid == 0 && empty($user->admin)) {
    print '<div class="warning">' . $langs->trans("OnlyExternalUsersCanAccess") . '</div>';
    llxFooter();
    $db->close();
    exit;
}

// Get allowed warehouses for the user's company (or all if admin)
$allowed_entrepots = array();
if ($socid > 0) {
    $sql = "SELECT fk_entrepot FROM " . MAIN_DB_PREFIX . "clientstock_access WHERE fk_soc = " . $socid;
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $allowed_entrepots[] = $obj->fk_entrepot;
        }
    }
}

// If external user has no warehouses assigned
if ($socid > 0 && empty($allowed_entrepots)) {
    print '<div class="info">' . $langs->trans("NoAccessFound") . '</div>';
    llxFooter();
    $db->close();
    exit;
}

// Helper function to render Dolibarr-like badges for the reservation status
function get_reservation_status_badge($status) {
    if ($status === '0') {
        return '<span class="badge" style="background-color: #777; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">Non réservé</span>';
    } elseif ($status === '1') {
        return '<span class="badge" style="background-color: #f0ad4e; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">Réservé partiel</span>';
    } elseif ($status === '2') {
        return '<span class="badge" style="background-color: #5bc0de; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">Réservé</span>';
    } elseif ($status === '3') {
        return '<span class="badge" style="background-color: #5cb85c; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">Consommé</span>';
    }
    return '';
}

// 1. Fetch active/consumed reservations (cards) from calcul_stock module
// Grouped by order, displaying the original ordered products (not the components/nomenclature)
$sql_res = "SELECT DISTINCT c.rowid as order_id, c.ref as order_ref, c.date_commande, coef.calc_stock_status,";
if ($socid == 0) {
    $sql_res .= " s.nom as client_name,";
}
$sql_res .= " cd.rowid as line_id, p.ref as product_ref, p.label as product_label, cd.qty";
$sql_res .= " FROM " . MAIN_DB_PREFIX . "calcul_stock_reservation as r";
$sql_res .= " INNER JOIN " . MAIN_DB_PREFIX . "commande as c ON r.fk_commande = c.rowid";
if ($socid == 0) {
    $sql_res .= " INNER JOIN " . MAIN_DB_PREFIX . "societe as s ON c.fk_soc = s.rowid";
}
$sql_res .= " LEFT JOIN " . MAIN_DB_PREFIX . "commande_extrafields as coef ON coef.fk_object = c.rowid";
$sql_res .= " INNER JOIN " . MAIN_DB_PREFIX . "commandedet as cd ON cd.fk_commande = c.rowid";
$sql_res .= " INNER JOIN " . MAIN_DB_PREFIX . "product as p ON cd.fk_product = p.rowid";
$sql_res .= " WHERE r.status IN (0, 1)";
if ($socid > 0) {
    $sql_res .= " AND c.fk_soc = " . $socid;
}
$sql_res .= " ORDER BY c.date_commande DESC, c.ref DESC, p.ref ASC";

$resql_res = $db->query($sql_res);
$order_reservations = array();
if ($resql_res) {
    while ($obj = $db->fetch_object($resql_res)) {
        $order_id = $obj->order_id;
        if (!isset($order_reservations[$order_id])) {
            $order_reservations[$order_id] = array(
                'ref' => $obj->order_ref,
                'date' => $obj->date_commande,
                'status' => $obj->calc_stock_status,
                'client_name' => isset($obj->client_name) ? $obj->client_name : '',
                'items' => array()
            );
        }
        $order_reservations[$order_id]['items'][$obj->line_id] = array(
            'product_ref' => $obj->product_ref,
            'product_label' => $obj->product_label,
            'qty' => $obj->qty
        );
    }
}

// Styling for cards and section titles
print '<style>
.reservation-container {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 30px;
}
.reservation-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px;
    flex: 1 1 calc(25% - 16px);
    min-width: 250px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    transition: transform 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.reservation-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.07), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
}
.reservation-card-title {
    font-size: 15px;
    font-weight: bold;
    color: #1e293b;
    margin: 0 0 6px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.reservation-card-client {
    font-size: 11px;
    color: #64748b;
    font-weight: normal;
}
.reservation-card-date {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 10px;
}
.reservation-card-item-list {
    margin: 8px 0 0 0;
    padding-left: 16px;
    font-size: 12px;
    color: #334155;
}
.reservation-card-item {
    margin-bottom: 4px;
}
.section-title {
    font-size: 18px;
    font-weight: bold;
    color: #0f172a;
    margin: 24px 0 14px 0;
    padding-bottom: 6px;
    border-bottom: 2px solid #cbd5e1;
}
</style>';

// Display top reservations section
print '<div class="section-title">' . $langs->trans("ReservationsTitle") . '</div>';
if (!empty($order_reservations)) {
    print '<div class="reservation-container">';
    foreach ($order_reservations as $o_id => $o_data) {
        print '<div class="reservation-card">';
        print '<div class="reservation-card-title">';
        print '<span>' . htmlspecialchars($o_data['ref']) . '</span>';
        if ($socid == 0 && !empty($o_data['client_name'])) {
            print '<span class="reservation-card-client">' . htmlspecialchars($o_data['client_name']) . '</span>';
        }
        print '</div>';
        
        $formatted_date = dol_print_date($db->jdate($o_data['date']), 'day');
        print '<div class="reservation-card-date">' . $langs->trans("OrderDate") . ': ' . $formatted_date;
        if ($o_data['status'] !== null && $o_data['status'] !== '') {
            print ' &nbsp; ' . get_reservation_status_badge($o_data['status']);
        }
        print '</div>';
        
        print '<ul class="reservation-card-item-list">';
        foreach ($o_data['items'] as $item) {
            print '<li class="reservation-card-item">';
            print '<b>' . htmlspecialchars($item['product_ref']) . '</b>: ' . ((float) round($item['qty'], 4)) . ' pcs';
            print '</li>';
        }
        print '</ul>';
        print '</div>';
    }
    print '</div>';
} else {
    print '<div style="padding:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;color:#64748b;margin-bottom:30px;">';
    print 'Aucune réservation de stock active.';
    print '</div>';
}

// 2. Display Stock Table Section
print '<div class="section-title">' . $langs->trans("Stocks") . '</div>';

$sql = "SELECT p.rowid as fk_product, p.ref, p.label, e.ref as warehouse_ref, e.description as warehouse_label, ps.reel as stock_physique";
$sql .= " FROM " . MAIN_DB_PREFIX . "product_stock as ps";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "product as p ON ps.fk_product = p.rowid";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "entrepot as e ON ps.fk_entrepot = e.rowid";

if ($socid > 0) {
    $sql .= " WHERE ps.fk_entrepot IN (" . implode(',', $allowed_entrepots) . ")";
    $sql .= " AND ps.reel != 0"; // Only show non-zero stock
} else {
    // For admin preview, show all mapped stocks
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "clientstock_access as ca ON ps.fk_entrepot = ca.fk_entrepot";
    $sql .= " WHERE ps.reel != 0";
}

$sql .= " ORDER BY e.description ASC, p.ref ASC";

$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);

    print '<table class="liste centpercent">';
    print '<tr class="liste_titre">';
    print '<td>' . $langs->trans("ProductRef") . '</td>';
    print '<td>' . $langs->trans("ProductLabel") . '</td>';
    print '<td>' . $langs->trans("Warehouse") . '</td>';
    print '<td align="right">' . $langs->trans("PhysicalStock") . '</td>';
    print '</tr>';

    if ($num > 0) {
        $i = 0;
        while ($i < $num) {
            $obj = $db->fetch_object($resql);
            print '<tr class="oddeven">';
            print '<td>' . $obj->ref . '</td>';
            print '<td>' . $obj->label . '</td>';
            print '<td>' . $obj->warehouse_ref . ' - ' . $obj->warehouse_label . '</td>';
            print '<td align="right">' . ((float) round($obj->stock_physique, 4)) . '</td>';
            print '</tr>';
            $i++;
        }
    } else {
        print '<tr><td colspan="4"><span class="opacitymedium">' . $langs->trans("NoRecordFound") . '</span></td></tr>';
    }
    print '</table>';
} else {
    dol_print_error($db);
}

llxFooter();
$db->close();
