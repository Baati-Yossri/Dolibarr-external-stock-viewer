<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/custom/clientstock/lib/clientstock.lib.php';

$langs->loadLangs(array("clientstock@clientstock", "products", "stocks"));

// Access control
if (empty($user->rights->clientstock->read)) {
    accessforbidden();
}

$socid = $user->socid;
$search_keyword = GETPOST('search_keyword', 'alphanohtml');

llxHeader('', $langs->trans("OfInProduction"));

print load_fiche_titre($langs->trans("OfInProduction"), '', 'object_stock');

// Admin preview banner (#15)
if ($socid == 0 && !empty($user->admin)) {
    print '<div style="background: linear-gradient(135deg, #fef3c7, #fde68a); border: 1px solid #f59e0b; border-radius: 8px; padding: 12px 18px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-size: 13px; color: #92400e;">';
    print '<span style="font-size: 18px;">⚠️</span>';
    print '<span>' . $langs->trans("AdminPreviewBanner") . '</span>';
    print '</div>';
}

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
    $sql = "SELECT of.rowid, of.of_ref, of.fusion_group_id, l.production_status, l.control_status, of.datec, c.ref as commande_ref,";
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
        $sql .= " AND (of.of_ref LIKE '%" . $search_esc . "%' OR of.fusion_group_id LIKE '%" . $search_esc . "%' OR c.ref LIKE '%" . $search_esc . "%' OR p.ref LIKE '%" . $search_esc . "%' OR p.label LIKE '%" . $search_esc . "%')";
    }
    $sql .= " ORDER BY of.datec DESC, of.of_ref ASC, p.ref ASC";
} else {
    // Admin preview - show all OFs in production with their corresponding client name
    $sql = "SELECT of.rowid, of.of_ref, of.fusion_group_id, l.production_status, l.control_status, of.datec, c.ref as commande_ref, s.nom as client_name,";
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
        $sql .= " AND (of.of_ref LIKE '%" . $search_esc . "%' OR of.fusion_group_id LIKE '%" . $search_esc . "%' OR c.ref LIKE '%" . $search_esc . "%' OR s.nom LIKE '%" . $search_esc . "%' OR p.ref LIKE '%" . $search_esc . "%' OR p.label LIKE '%" . $search_esc . "%')";
    }
    $sql .= " ORDER BY of.datec DESC, of.of_ref ASC, p.ref ASC";
}

$resql = $db->query($sql);

// Styles for sortable columns, mobile responsiveness, and print (#5, #12, #13)
print '<style>
.sortable-header {
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
}
.sortable-header:hover {
    text-decoration: underline;
}
.sort-arrow { font-size: 10px; margin-left: 4px; opacity: 0.5; }
.sort-arrow.active { opacity: 1; }
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin-bottom: 16px;
}
@media print {
    #searchOFForm, .sort-arrow { display: none !important; }
    table.liste { font-size: 11px; }
    table.liste td { padding: 4px 6px; border: 1px solid #ccc; }
}
</style>';

if ($resql) {
    $num = $db->num_rows($resql);

    // Mobile responsive wrapper (#12)
    print '<div class="table-responsive">';
    print '<table class="liste centpercent" id="of_list_table">';
    print '<tr class="liste_titre">';
    $col_offset = 0;
    if ($socid == 0) {
        print '<td class="sortable-header" data-col="' . $col_offset . '">' . $langs->trans("Client") . ' <span class="sort-arrow">▲▼</span></td>';
        $col_offset++;
    }
    print '<td class="sortable-header" data-col="' . $col_offset . '">' . $langs->trans("OFRef") . ' <span class="sort-arrow">▲▼</span></td>';
    $col_offset++;
    print '<td class="sortable-header" data-col="' . $col_offset . '">' . $langs->trans("OrderRef") . ' <span class="sort-arrow">▲▼</span></td>';
    $col_offset++;
    print '<td>' . $langs->trans("Article") . '</td>';
    $col_offset++;
    print '<td align="right" class="sortable-header" data-col="' . $col_offset . '">' . $langs->trans("Quantity") . ' <span class="sort-arrow">▲▼</span></td>';
    $col_offset++;
    print '<td align="center">' . $langs->trans("StatusProd") . '</td>';
    print '<td align="center">' . $langs->trans("StatusControl") . '</td>';
    print '</tr>';

    // Group records by OF in PHP using rowid
    $ofs = array();
    if ($num > 0) {
        while ($obj = $db->fetch_object($resql)) {
            $of_key = !empty($obj->rowid) ? $obj->rowid : ($obj->of_ref . '_' . $obj->fusion_group_id);
            if (!isset($ofs[$of_key])) {
                $of_ref = trim((string) $obj->of_ref);
                $fusion_id = trim((string) $obj->fusion_group_id);
                $commande_ref = trim((string) $obj->commande_ref);

                $of_display = '';
                if (!empty($of_ref) && $of_ref !== '-') {
                    if (!empty($fusion_id) && $fusion_id !== '-') {
                        $suffix = '-' . $fusion_id;
                        if (substr($of_ref, -strlen($suffix)) === $suffix) {
                            $of_display = substr_replace($of_ref, '/' . $fusion_id, -strlen($suffix));
                        } else {
                            $of_display = $of_ref . '/' . $fusion_id;
                        }
                    } else {
                        $of_display = $of_ref;
                    }
                } elseif (!empty($commande_ref) && !empty($fusion_id) && $fusion_id !== '-') {
                    $of_display = $commande_ref . '/' . $fusion_id;
                } elseif (!empty($fusion_id) && $fusion_id !== '-') {
                    $of_display = $fusion_id;
                } else {
                    $of_display = '-';
                }

                $ofs[$of_key] = array(
                    'rowid' => $obj->rowid,
                    'of_ref' => $of_ref,
                    'fusion_group_id' => $fusion_id,
                    'of_display' => $of_display,
                    'commande_ref' => $obj->commande_ref,
                    'client_name' => isset($obj->client_name) ? $obj->client_name : '',
                    'production_status' => $obj->production_status,
                    'control_status' => $obj->control_status,
                    'total_qty' => 0,
                    'articles' => array()
                );
            }
            $ofs[$of_key]['articles'][] = array(
                'ref' => $obj->product_ref,
                'label' => $obj->product_label,
                'qty' => $obj->qty
            );
            $ofs[$of_key]['total_qty'] += $obj->qty;
        }

        foreach ($ofs as $of) {
            $articles_text = '';
            foreach ($of['articles'] as $art) {
                $articles_text .= $art['ref'] . ' ' . $art['label'] . ' ';
            }
            $search_data = htmlspecialchars(strtolower($of['of_display'] . ' ' . $of['of_ref'] . ' ' . $of['fusion_group_id'] . ' ' . $of['commande_ref'] . ' ' . $of['client_name'] . ' ' . $articles_text));

            print '<tr class="oddeven of-row" data-search="' . $search_data . '">';
            if ($socid == 0) {
                print '<td>' . htmlspecialchars($of['client_name']) . '</td>';
            }
            print '<td>' . htmlspecialchars($of['of_display']) . '</td>';
            print '<td>' . htmlspecialchars($of['commande_ref']) . '</td>';
            
            // Build bullet points for articles
            print '<td>';
            foreach ($of['articles'] as $art) {
                print htmlspecialchars($art['ref'] . ' - ' . $art['label']) . ' (<b>' . ((float) round($art['qty'], 4)) . '</b>)<br>';
            }
            print '</td>';
            
            print '<td align="right">' . ((float) round($of['total_qty'], 4)) . '</td>';
            print '<td align="center">' . clientstock_get_workflow_status_badge($of['production_status']) . '</td>';
            print '<td align="center">' . clientstock_get_workflow_status_badge($of['control_status']) . '</td>';
            print '</tr>';
        }
    } else {
        // Improved empty state (#9)
        $colspan = ($socid == 0) ? 7 : 6;
        print '<tr id="no_of_row"><td colspan="' . $colspan . '" style="text-align: center; padding: 30px;">';
        print '<div style="font-size: 36px; margin-bottom: 8px;">🏭</div>';
        print '<span class="opacitymedium">' . $langs->trans("NoOFFound") . '</span>';
        print '</td></tr>';
    }
    print '</table>';
    print '</div>';
} else {
    dol_print_error($db);
}

// Last updated timestamp (#8)
print '<div style="text-align: right; font-size: 11px; color: #94a3b8; margin-top: 10px; margin-bottom: 16px;">';
print $langs->trans("LastUpdated") . ': ' . dol_print_date(dol_now(), 'dayhour');
print '</div>';

// Inline JavaScript for Instant Real-Time Search, Live Count, and Sortable Columns (#5)
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

    // Sortable columns (#5)
    var sortState = { col: -1, asc: true };
    document.querySelectorAll(".sortable-header").forEach(function(header) {
        header.addEventListener("click", function() {
            var colIdx = parseInt(this.getAttribute("data-col"));
            if (sortState.col === colIdx) {
                sortState.asc = !sortState.asc;
            } else {
                sortState.col = colIdx;
                sortState.asc = true;
            }

            var table = document.getElementById("of_list_table");
            var tbody = table.querySelector("tbody") || table;
            var rows = Array.from(tbody.querySelectorAll("tr.of-row"));

            rows.sort(function(a, b) {
                var aCell = a.cells[colIdx];
                var bCell = b.cells[colIdx];
                if (!aCell || !bCell) return 0;
                var aVal = aCell.textContent.trim();
                var bVal = bCell.textContent.trim();

                // Numeric sort for quantity column
                var numA = parseFloat(aVal.replace(/,/g, ".").replace(/\s/g, ""));
                var numB = parseFloat(bVal.replace(/,/g, ".").replace(/\s/g, ""));
                if (!isNaN(numA) && !isNaN(numB) && aVal.match(/^[\d\s.,]+$/)) {
                    return sortState.asc ? numA - numB : numB - numA;
                }

                aVal = aVal.toLowerCase();
                bVal = bVal.toLowerCase();
                if (aVal < bVal) return sortState.asc ? -1 : 1;
                if (aVal > bVal) return sortState.asc ? 1 : -1;
                return 0;
            });

            rows.forEach(function(row) { tbody.appendChild(row); });

            document.querySelectorAll(".sort-arrow").forEach(function(a) {
                a.className = "sort-arrow";
                a.textContent = "▲▼";
            });
            var activeArrow = header.querySelector(".sort-arrow");
            if (activeArrow) {
                activeArrow.className = "sort-arrow active";
                activeArrow.textContent = sortState.asc ? "▲" : "▼";
            }
        });
    });
});
</script>';

llxFooter();
$db->close();
