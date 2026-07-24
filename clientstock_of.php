<?php
require '../../main.inc.php';

$langs->loadLangs(array("clientstock@clientstock", "products", "stocks"));

// Access control
if (empty($user->rights->clientstock->read)) {
    accessforbidden();
}

$socid = $user->socid;
$search_keyword = GETPOST('search_keyword', 'alphanohtml');

llxHeader('', $langs->trans("OfInProduction"));

print load_fiche_titre($langs->trans("OfInProduction"), '', 'object_stock');

if ($socid == 0 && empty($user->admin)) {
    print '<div class="warning">' . $langs->trans("OnlyExternalUsersCanAccess") . '</div>';
    llxFooter();
    $db->close();
    exit;
}

// Search bar form display
print '<form method="GET" action="' . htmlspecialchars($_SERVER["PHP_SELF"]) . '" style="margin-bottom: 20px;" id="searchOFForm">';
print '<div style="display: flex; gap: 12px; align-items: center; background: #f8fafc; padding: 14px 18px; border: 1px solid #cbd5e1; border-radius: 8px; flex-wrap: wrap; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
print '<div style="flex: 1 1 300px; position: relative;">';
print '<input type="text" id="clientstock_of_search" name="search_keyword" value="' . htmlspecialchars($search_keyword) . '" class="flat" placeholder="' . $langs->trans("SearchOFPlaceholder") . '" style="width: 100%; padding: 8px 12px 8px 34px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;" />';
print '<span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px;">🔍</span>';
print '</div>';

print '<div style="display: flex; gap: 8px;">';
print '<input type="submit" class="button" value="' . $langs->trans("Search") . '" style="padding: 8px 16px; margin: 0;" />';
if (!empty($search_keyword)) {
    print '<a href="' . htmlspecialchars($_SERVER["PHP_SELF"]) . '" class="button button-cancel" style="padding: 8px 16px; text-decoration: none; display: inline-block;">' . $langs->trans("ClearFilter") . '</a>';
}
print '</div>';

print '<div id="search_of_count_badge" style="margin-left: auto; font-size: 13px; color: #475569; font-weight: bold;"></div>';
print '</div>';
print '</form>';

// Build query
if ($socid > 0) {
    // Client view - only show OFs linked to their third-party orders and not archived
    $sql = "SELECT of.rowid, of.of_ref, l.production_status, l.control_status, of.datec, c.ref as commande_ref,";
    $sql .= " p.ref as product_ref, p.label as product_label, cd.qty";
    $sql .= " FROM " . MAIN_DB_PREFIX . "prod_of as of";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "commande as c ON of.fk_commande = c.rowid";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "commandedet as cd ON cd.fk_commande = of.fk_commande";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "commandedet_extrafields as cde ON cde.fk_object = cd.rowid AND cde.fusion_group_id = of.fusion_group_id";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "product as p ON cd.fk_product = p.rowid";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "prod_lancement as l ON of.fk_commande = l.fk_commande AND of.fusion_group_id = l.fusion_group_id";
    $sql .= " WHERE c.fk_soc = " . (int) $socid;
    $sql .= " AND coalesce(l.archived, 0) = 0";
    if (!empty($search_keyword)) {
        $search_esc = $db->escape(trim($search_keyword));
        $sql .= " AND (of.of_ref LIKE '%" . $search_esc . "%' OR c.ref LIKE '%" . $search_esc . "%' OR p.ref LIKE '%" . $search_esc . "%' OR p.label LIKE '%" . $search_esc . "%')";
    }
    $sql .= " ORDER BY of.datec DESC, of.of_ref ASC, p.ref ASC";
} else {
    // Admin preview - show all OFs in production with their corresponding client name
    $sql = "SELECT of.rowid, of.of_ref, l.production_status, l.control_status, of.datec, c.ref as commande_ref, s.nom as client_name,";
    $sql .= " p.ref as product_ref, p.label as product_label, cd.qty";
    $sql .= " FROM " . MAIN_DB_PREFIX . "prod_of as of";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "commande as c ON of.fk_commande = c.rowid";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "societe as s ON c.fk_soc = s.rowid";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "commandedet as cd ON cd.fk_commande = of.fk_commande";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "commandedet_extrafields as cde ON cde.fk_object = cd.rowid AND cde.fusion_group_id = of.fusion_group_id";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "product as p ON cd.fk_product = p.rowid";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "prod_lancement as l ON of.fk_commande = l.fk_commande AND of.fusion_group_id = l.fusion_group_id";
    $sql .= " WHERE coalesce(l.archived, 0) = 0";
    if (!empty($search_keyword)) {
        $search_esc = $db->escape(trim($search_keyword));
        $sql .= " AND (of.of_ref LIKE '%" . $search_esc . "%' OR c.ref LIKE '%" . $search_esc . "%' OR s.nom LIKE '%" . $search_esc . "%' OR p.ref LIKE '%" . $search_esc . "%' OR p.label LIKE '%" . $search_esc . "%')";
    }
    $sql .= " ORDER BY of.datec DESC, of.of_ref ASC, p.ref ASC";
}

$resql = $db->query($sql);

function get_prod_status_badge($status) {
    if (empty($status) || $status == 'todo') {
        return '<span class="badge badge-warning" style="background-color: #f0ad4e; color: #fff; padding: 2px 6px; border-radius: 3px;">En attente</span>';
    } elseif ($status == 'encours' || $status == 'en_cours' || $status == 'lance') {
        return '<span class="badge badge-info" style="background-color: #5bc0de; color: #fff; padding: 2px 6px; border-radius: 3px;">En cours</span>';
    } elseif ($status == 'termine') {
        return '<span class="badge badge-success" style="background-color: #5cb85c; color: #fff; padding: 2px 6px; border-radius: 3px;">Terminé</span>';
    }
    return '<span class="badge badge-secondary" style="background-color: #777; color: #fff; padding: 2px 6px; border-radius: 3px;">' . ucfirst(str_replace('_', ' ', $status)) . '</span>';
}

function get_control_status_badge($status) {
    if (empty($status) || $status == 'todo') {
        return '<span class="badge badge-warning" style="background-color: #f0ad4e; color: #fff; padding: 2px 6px; border-radius: 3px;">En attente</span>';
    } elseif ($status == 'encours' || $status == 'en_cours' || $status == 'lance') {
        return '<span class="badge badge-info" style="background-color: #5bc0de; color: #fff; padding: 2px 6px; border-radius: 3px;">En cours</span>';
    } elseif ($status == 'termine') {
        return '<span class="badge badge-success" style="background-color: #5cb85c; color: #fff; padding: 2px 6px; border-radius: 3px;">Terminé</span>';
    }
    return '<span class="badge badge-secondary" style="background-color: #777; color: #fff; padding: 2px 6px; border-radius: 3px;">' . ucfirst(str_replace('_', ' ', $status)) . '</span>';
}

if ($resql) {
    $num = $db->num_rows($resql);

    print '<table class="liste centpercent" id="of_list_table">';
    print '<tr class="liste_titre">';
    if ($socid == 0) {
        print '<td>' . $langs->trans("Client") . '</td>';
    }
    print '<td>' . $langs->trans("OFRef") . '</td>';
    print '<td>' . $langs->trans("OrderRef") . '</td>';
    print '<td>' . $langs->trans("Article") . '</td>';
    print '<td align="right">' . $langs->trans("Quantity") . '</td>';
    print '<td align="center">' . $langs->trans("StatusProd") . '</td>';
    print '<td align="center">' . $langs->trans("StatusControl") . '</td>';
    print '</tr>';

    // Group records by OF in PHP
    $ofs = array();
    if ($num > 0) {
        while ($obj = $db->fetch_object($resql)) {
            $of_ref = $obj->of_ref;
            if (!isset($ofs[$of_ref])) {
                $ofs[$of_ref] = array(
                    'of_ref' => $obj->of_ref,
                    'commande_ref' => $obj->commande_ref,
                    'client_name' => isset($obj->client_name) ? $obj->client_name : '',
                    'production_status' => $obj->production_status,
                    'control_status' => $obj->control_status,
                    'total_qty' => 0,
                    'articles' => array()
                );
            }
            $ofs[$of_ref]['articles'][] = array(
                'ref' => $obj->product_ref,
                'label' => $obj->product_label,
                'qty' => $obj->qty
            );
            $ofs[$of_ref]['total_qty'] += $obj->qty;
        }

        foreach ($ofs as $of) {
            $articles_text = '';
            foreach ($of['articles'] as $art) {
                $articles_text .= $art['ref'] . ' ' . $art['label'] . ' ';
            }
            $search_data = htmlspecialchars(strtolower($of['of_ref'] . ' ' . $of['commande_ref'] . ' ' . $of['client_name'] . ' ' . $articles_text));

            print '<tr class="oddeven of-row" data-search="' . $search_data . '">';
            if ($socid == 0) {
                print '<td>' . htmlspecialchars($of['client_name']) . '</td>';
            }
            print '<td>' . htmlspecialchars($of['of_ref']) . '</td>';
            print '<td>' . htmlspecialchars($of['commande_ref']) . '</td>';
            
            // Build bullet points for articles
            print '<td>';
            foreach ($of['articles'] as $art) {
                print htmlspecialchars($art['ref'] . ' - ' . $art['label']) . ' (<b>' . ((float) round($art['qty'], 4)) . '</b>)<br>';
            }
            print '</td>';
            
            print '<td align="right">' . ((float) round($of['total_qty'], 4)) . '</td>';
            print '<td align="center">' . get_prod_status_badge($of['production_status']) . '</td>';
            print '<td align="center">' . get_control_status_badge($of['control_status']) . '</td>';
            print '</tr>';
        }
    } else {
        $colspan = ($socid == 0) ? 7 : 6;
        print '<tr id="no_of_row"><td colspan="' . $colspan . '"><span class="opacitymedium">' . $langs->trans("NoOFFound") . '</span></td></tr>';
    }
    print '</table>';
} else {
    dol_print_error($db);
}

// Inline JavaScript for Instant Real-Time Search & Live Count
print '<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    var searchInput = document.getElementById("clientstock_of_search");
    var countBadge = document.getElementById("search_of_count_badge");

    function filterOFList() {
        var term = searchInput.value.toLowerCase().trim();
        var rows = document.querySelectorAll(".of-row");
        var visibleCount = 0;

        rows.forEach(function(row) {
            var text = row.getAttribute("data-search") || "";
            if (term === "" || text.indexOf(term) !== -1) {
                row.style.display = "";
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        });

        if (countBadge) {
            countBadge.textContent = visibleCount + " OF(s) trouvé(s)";
        }
    }

    if (searchInput) {
        searchInput.addEventListener("input", filterOFList);
        searchInput.addEventListener("keyup", filterOFList);
    }
    filterOFList();
});
</script>';

llxFooter();
$db->close();
