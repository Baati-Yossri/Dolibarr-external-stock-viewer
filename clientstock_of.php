<?php
require '../../main.inc.php';

$langs->loadLangs(array("clientstock@clientstock", "products", "stocks"));

// Access control
if (empty($user->rights->clientstock->read)) {
    accessforbidden();
}

$socid = $user->socid;

llxHeader('', $langs->trans("OfInProduction"));

print load_fiche_titre($langs->trans("OfInProduction"), '', 'object_stock');

if ($socid == 0 && empty($user->admin)) {
    print '<div class="warning">' . $langs->trans("OnlyExternalUsersCanAccess") . '</div>';
    llxFooter();
    $db->close();
    exit;
}

// Build query
if ($socid > 0) {
    // Client view - only show OFs linked to their third-party orders and not archived
    $sql = "SELECT of.rowid, of.of_ref, of.status, of.datec, c.ref as commande_ref,";
    $sql .= " p.ref as product_ref, p.label as product_label, cd.qty";
    $sql .= " FROM " . MAIN_DB_PREFIX . "prod_of as of";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "commande as c ON of.fk_commande = c.rowid";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "commandedet as cd ON cd.fk_commande = of.fk_commande";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "commandedet_extrafields as cde ON cde.fk_object = cd.rowid AND cde.fusion_group_id = of.fusion_group_id";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "product as p ON cd.fk_product = p.rowid";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "prod_lancement as l ON of.fk_commande = l.fk_commande AND of.fusion_group_id = l.fusion_group_id";
    $sql .= " WHERE c.fk_soc = " . $socid;
    $sql .= " AND coalesce(l.archived, 0) = 0";
    $sql .= " ORDER BY of.datec DESC, of.of_ref ASC, p.ref ASC";
} else {
    // Admin preview - show all OFs in production with their corresponding client name
    $sql = "SELECT of.rowid, of.of_ref, of.status, of.datec, c.ref as commande_ref, s.nom as client_name,";
    $sql .= " p.ref as product_ref, p.label as product_label, cd.qty";
    $sql .= " FROM " . MAIN_DB_PREFIX . "prod_of as of";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "commande as c ON of.fk_commande = c.rowid";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "societe as s ON c.fk_soc = s.rowid";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "commandedet as cd ON cd.fk_commande = of.fk_commande";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "commandedet_extrafields as cde ON cde.fk_object = cd.rowid AND cde.fusion_group_id = of.fusion_group_id";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "product as p ON cd.fk_product = p.rowid";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "prod_lancement as l ON of.fk_commande = l.fk_commande AND of.fusion_group_id = l.fusion_group_id";
    $sql .= " WHERE coalesce(l.archived, 0) = 0";
    $sql .= " ORDER BY of.datec DESC, of.of_ref ASC, p.ref ASC";
}

$resql = $db->query($sql);

function get_status_badge($status) {
    if ($status == 'non_lance') {
        return '<span class="badge badge-warning" style="background-color: #f0ad4e; color: #fff; padding: 2px 6px; border-radius: 3px;">En attente</span>';
    } elseif ($status == 'encours' || $status == 'lance') {
        return '<span class="badge badge-info" style="background-color: #5bc0de; color: #fff; padding: 2px 6px; border-radius: 3px;">En cours</span>';
    } elseif ($status == 'termine') {
        return '<span class="badge badge-success" style="background-color: #5cb85c; color: #fff; padding: 2px 6px; border-radius: 3px;">Terminé</span>';
    }
    return '<span class="badge badge-secondary" style="background-color: #777; color: #fff; padding: 2px 6px; border-radius: 3px;">' . ucfirst(str_replace('_', ' ', $status)) . '</span>';
}

if ($resql) {
    $num = $db->num_rows($resql);

    print '<table class="liste centpercent">';
    print '<tr class="liste_titre">';
    if ($socid == 0) {
        print '<td>' . $langs->trans("Client") . '</td>';
    }
    print '<td>' . $langs->trans("OFRef") . '</td>';
    print '<td>' . $langs->trans("OrderRef") . '</td>';
    print '<td>' . $langs->trans("Article") . '</td>';
    print '<td align="right">' . $langs->trans("Quantity") . '</td>';
    print '<td align="center">' . $langs->trans("Status") . '</td>';
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
                    'status' => $obj->status,
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
            print '<tr class="oddeven">';
            if ($socid == 0) {
                print '<td>' . htmlspecialchars($of['client_name']) . '</td>';
            }
            print '<td>' . htmlspecialchars($of['of_ref']) . '</td>';
            print '<td>' . htmlspecialchars($of['commande_ref']) . '</td>';
            
            // Build bullet points for articles
            print '<td>';
            foreach ($of['articles'] as $art) {
                print htmlspecialchars($art['ref'] . ' - ' . $art['label']) . ' (<b>' . $art['qty'] . '</b>)<br>';
            }
            print '</td>';
            
            print '<td align="right">' . $of['total_qty'] . '</td>';
            print '<td align="center">' . get_status_badge($of['status']) . '</td>';
            print '</tr>';
        }
    } else {
        $colspan = ($socid == 0) ? 6 : 5;
        print '<tr><td colspan="' . $colspan . '"><span class="opacitymedium">' . $langs->trans("NoOFFound") . '</span></td></tr>';
    }
    print '</table>';
} else {
    dol_print_error($db);
}

llxFooter();
$db->close();
