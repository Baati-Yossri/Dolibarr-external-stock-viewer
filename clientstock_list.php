<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

$langs->loadLangs(array("clientstock@clientstock", "products", "stocks"));

// Access control
if (empty($user->rights->clientstock->read))
    accessforbidden();

$socid = $user->socid;

$search_keyword = GETPOST('search_keyword', 'alphanohtml');
$search_entrepot = GETPOST('search_entrepot', 'int');

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
    $sql = "SELECT fk_entrepot FROM " . MAIN_DB_PREFIX . "clientstock_access WHERE fk_soc = " . (int) $socid;
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $allowed_entrepots[] = (int) $obj->fk_entrepot;
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

// Fetch list of accessible warehouses for dropdown
$warehouses_list = array();
$sql_w = "SELECT rowid, ref, description FROM " . MAIN_DB_PREFIX . "entrepot";
if ($socid > 0 && !empty($allowed_entrepots)) {
    $sql_w .= " WHERE rowid IN (" . implode(',', $allowed_entrepots) . ")";
} else {
    $sql_w .= " WHERE rowid IN (SELECT fk_entrepot FROM " . MAIN_DB_PREFIX . "clientstock_access)";
}
$sql_w .= " ORDER BY description ASC";
$resql_w = $db->query($sql_w);
if ($resql_w) {
    while ($obj_w = $db->fetch_object($resql_w)) {
        $warehouses_list[$obj_w->rowid] = $obj_w->ref . ' - ' . $obj_w->description;
    }
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

// Function to calculate and update order reservation status on-the-fly to guarantee absolute accuracy
function clientstock_calculate_order_reservation_status($db, $order_id) {
    require_once DOL_DOCUMENT_ROOT . '/custom/factory/class/factory.class.php';
    require_once DOL_DOCUMENT_ROOT . '/custom/calcul_stock/class/calculstockreservation.class.php';
    require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
    require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';

    $object = new Commande($db);
    if ($object->fetch($order_id) <= 0) return '0';
    
    $factory = new Factory($db);
    $reservation_static = new CalculStockReservation($db);
    
    $num_components = 0;
    $num_consumed = 0;
    $num_reserved = 0;
    $num_partially_reserved = 0;
    $num_unreserved = 0;

    foreach ($object->lines as $line) {
        if (!empty($line->fk_product)) {
            $components = $factory->getChildsArbo($line->fk_product);
            if (!empty($components) && is_array($components)) {
                foreach ($components as $compId => $compData) {
                    $needed = $compData[1] * $line->qty;
                    $num_components++;
                    
                    if ($reservation_static->fetchByLineAndProduct($line->id, $compId) > 0) {
                        if ($reservation_static->status == 1) {
                            $num_consumed++;
                        } elseif ($reservation_static->qty >= ($needed - 0.0001)) {
                            $num_reserved++;
                        } elseif ($reservation_static->qty > 0) {
                            $num_partially_reserved++;
                        } else {
                            $num_unreserved++;
                        }
                    } else {
                        $num_unreserved++;
                    }
                }
            }
        }
    }
    
    $status_code = '0'; // Non réservé
    if ($num_components > 0) {
        if ($num_consumed == $num_components) {
            $status_code = '3'; // Consommé
        } elseif (($num_consumed + $num_reserved) == $num_components) {
            $status_code = '2'; // Réservé
        } elseif ($num_consumed > 0 || $num_reserved > 0 || $num_partially_reserved > 0) {
            $status_code = '1'; // Réservé partiellement
        }
    }

    return $status_code;
}

// Search bar form display
print '<form method="GET" action="' . htmlspecialchars($_SERVER["PHP_SELF"]) . '" style="margin-bottom: 20px;" id="searchStockForm">';
print '<div style="display: flex; gap: 12px; align-items: center; background: #f8fafc; padding: 14px 18px; border: 1px solid #cbd5e1; border-radius: 8px; flex-wrap: wrap; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
print '<div style="flex: 1 1 280px; position: relative;">';
print '<input type="text" id="clientstock_search" name="search_keyword" value="' . htmlspecialchars($search_keyword) . '" class="flat" placeholder="' . $langs->trans("SearchPlaceholder") . '" style="width: 100%; padding: 8px 12px 8px 34px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;" />';
print '<span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px;">🔍</span>';
print '</div>';

print '<div>';
print '<select name="search_entrepot" id="clientstock_entrepot" class="flat" style="padding: 8px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px;">';
print '<option value="0">-- ' . $langs->trans("AllWarehouses") . ' --</option>';
foreach ($warehouses_list as $w_id => $w_label) {
    $selected = ($search_entrepot == $w_id) ? ' selected="selected"' : '';
    print '<option value="' . $w_id . '"' . $selected . '>' . htmlspecialchars($w_label) . '</option>';
}
print '</select>';
print '</div>';

print '<div style="display: flex; gap: 8px;">';
print '<input type="submit" class="button" value="' . $langs->trans("Search") . '" style="padding: 8px 16px; margin: 0;" />';
if (!empty($search_keyword) || !empty($search_entrepot)) {
    print '<a href="' . htmlspecialchars($_SERVER["PHP_SELF"]) . '" class="button button-cancel" style="padding: 8px 16px; text-decoration: none; display: inline-block;">' . $langs->trans("ClearFilter") . '</a>';
}
print '</div>';

print '<div id="search_count_badge" style="margin-left: auto; font-size: 13px; color: #475569; font-weight: bold;"></div>';
print '</div>';
print '</form>';

// 1. Fetch active/consumed reservations (cards) from calcul_stock module
$sql_res = "SELECT DISTINCT c.rowid as order_id, c.ref as order_ref, c.date_commande,";
if ($socid == 0) {
    $sql_res .= " s.nom as client_name,";
}
$sql_res .= " cd.rowid as line_id, p.ref as product_ref, p.label as product_label, cd.qty";
$sql_res .= " FROM " . MAIN_DB_PREFIX . "calcul_stock_reservation as r";
$sql_res .= " INNER JOIN " . MAIN_DB_PREFIX . "commande as c ON r.fk_commande = c.rowid";
if ($socid == 0) {
    $sql_res .= " INNER JOIN " . MAIN_DB_PREFIX . "societe as s ON c.fk_soc = s.rowid";
}
$sql_res .= " INNER JOIN " . MAIN_DB_PREFIX . "commandedet as cd ON cd.fk_commande = c.rowid";
$sql_res .= " INNER JOIN " . MAIN_DB_PREFIX . "product as p ON cd.fk_product = p.rowid";
$sql_res .= " WHERE r.status IN (0, 1)";
if ($socid > 0) {
    $sql_res .= " AND c.fk_soc = " . (int) $socid;
}
if (!empty($search_keyword)) {
    $search_esc = $db->escape(trim($search_keyword));
    $sql_res .= " AND (c.ref LIKE '%" . $search_esc . "%' OR p.ref LIKE '%" . $search_esc . "%' OR p.label LIKE '%" . $search_esc . "%')";
}
$sql_res .= " ORDER BY c.date_commande DESC, c.ref DESC, p.ref ASC";

$resql_res = $db->query($sql_res);
$order_reservations = array();
if ($resql_res) {
    while ($obj = $db->fetch_object($resql_res)) {
        $order_id = $obj->order_id;
        if (!isset($order_reservations[$order_id])) {
            $calculated_status = clientstock_calculate_order_reservation_status($db, $order_id);
            $order_reservations[$order_id] = array(
                'ref' => $obj->order_ref,
                'date' => $obj->date_commande,
                'status' => $calculated_status,
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
    print '<div class="reservation-container" id="reservation_container">';
    foreach ($order_reservations as $o_id => $o_data) {
        print '<div class="reservation-card" data-search="' . htmlspecialchars(strtolower($o_data['ref'] . ' ' . $o_data['client_name'])) . '">';
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
            print '<li class="reservation-card-item" data-item-search="' . htmlspecialchars(strtolower($item['product_ref'] . ' ' . $item['product_label'])) . '">';
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

$sql = "SELECT p.rowid as fk_product, p.ref, p.label, e.rowid as entrepot_id, e.ref as warehouse_ref, e.description as warehouse_label, ps.reel as stock_physique";
$sql .= " FROM " . MAIN_DB_PREFIX . "product_stock as ps";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "product as p ON ps.fk_product = p.rowid";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "entrepot as e ON ps.fk_entrepot = e.rowid";

if ($socid > 0) {
    $sql .= " WHERE ps.fk_entrepot IN (" . implode(',', $allowed_entrepots) . ")";
    $sql .= " AND ps.reel != 0";
} else {
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "clientstock_access as ca ON ps.fk_entrepot = ca.fk_entrepot";
    $sql .= " WHERE ps.reel != 0";
}

if ($search_entrepot > 0) {
    $sql .= " AND ps.fk_entrepot = " . (int) $search_entrepot;
}

if (!empty($search_keyword)) {
    $search_esc = $db->escape(trim($search_keyword));
    $sql .= " AND (p.ref LIKE '%" . $search_esc . "%' OR p.label LIKE '%" . $search_esc . "%' OR e.ref LIKE '%" . $search_esc . "%' OR e.description LIKE '%" . $search_esc . "%')";
}

$sql .= " ORDER BY e.description ASC, p.ref ASC";

$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);

    print '<table class="liste centpercent" id="stock_list_table">';
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
            $search_data = htmlspecialchars(strtolower($obj->ref . ' ' . $obj->label . ' ' . $obj->warehouse_ref . ' ' . $obj->warehouse_label));
            print '<tr class="oddeven stock-row" data-entrepot-id="' . $obj->entrepot_id . '" data-search="' . $search_data . '">';
            print '<td>' . htmlspecialchars($obj->ref) . '</td>';
            print '<td>' . htmlspecialchars($obj->label) . '</td>';
            print '<td>' . htmlspecialchars($obj->warehouse_ref . ' - ' . $obj->warehouse_label) . '</td>';
            print '<td align="right">' . ((float) round($obj->stock_physique, 4)) . '</td>';
            print '</tr>';
            $i++;
        }
    } else {
        print '<tr id="no_stock_row"><td colspan="4"><span class="opacitymedium">' . $langs->trans("NoRecordFound") . '</span></td></tr>';
    }
    print '</table>';
} else {
    dol_print_error($db);
}

// Inline JavaScript for Instant Real-Time Search & Live Count
print '<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    var searchInput = document.getElementById("clientstock_search");
    var entrepotSelect = document.getElementById("clientstock_entrepot");
    var countBadge = document.getElementById("search_count_badge");

    function filterStockList() {
        var term = searchInput.value.toLowerCase().trim();
        var selectedEntrepot = entrepotSelect.value;
        var rows = document.querySelectorAll(".stock-row");
        var visibleCount = 0;

        rows.forEach(function(row) {
            var text = row.getAttribute("data-search") || "";
            var entrepotId = row.getAttribute("data-entrepot-id") || "";

            var matchesTerm = (term === "" || text.indexOf(term) !== -1);
            var matchesEntrepot = (selectedEntrepot === "0" || entrepotId === selectedEntrepot);

            if (matchesTerm && matchesEntrepot) {
                row.style.display = "";
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        });

        // Filter reservation cards as well
        var cards = document.querySelectorAll(".reservation-card");
        cards.forEach(function(card) {
            var cardText = card.getAttribute("data-search") || "";
            var items = card.querySelectorAll("[data-item-search]");
            var itemMatches = false;
            items.forEach(function(item) {
                if ((item.getAttribute("data-item-search") || "").indexOf(term) !== -1) {
                    itemMatches = true;
                }
            });

            if (term === "" || cardText.indexOf(term) !== -1 || itemMatches) {
                card.style.display = "";
            } else {
                card.style.display = "none";
            }
        });

        if (countBadge) {
            countBadge.textContent = visibleCount + " produit(s) trouvé(s)";
        }
    }

    if (searchInput) {
        searchInput.addEventListener("input", filterStockList);
        searchInput.addEventListener("keyup", filterStockList);
    }
    if (entrepotSelect) {
        entrepotSelect.addEventListener("change", filterStockList);
    }
    filterStockList();
});
</script>';

llxFooter();
$db->close();
