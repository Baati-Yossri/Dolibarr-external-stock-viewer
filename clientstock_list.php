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

// Build the query to get stock
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
            print '<td align="right">' . $obj->stock_physique . '</td>';
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
