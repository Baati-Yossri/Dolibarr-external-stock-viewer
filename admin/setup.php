<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

$langs->loadLangs(array("admin", "clientstock@clientstock"));

if (!$user->admin && empty($user->rights->clientstock->setup))
    accessforbidden();

$action = GETPOST('action', 'aZ09');

// Add new access
if ($action == 'add') {
    if (!verifCond('1')) {
        // Token check handled by Dolibarr's CSRF mechanism
    }

    $fk_soc = GETPOST('fk_soc', 'int');
    $fk_entrepot = GETPOST('fk_entrepot', 'int');

    if ($fk_soc > 0 && $fk_entrepot > 0) {
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "clientstock_access (fk_soc, fk_entrepot, datec, fk_user_creat) VALUES (" . ((int) $fk_soc) . ", " . ((int) $fk_entrepot) . ", '" . $db->idate(dol_now()) . "', " . ((int) $user->id) . ")";
        $resql = $db->query($sql);
        if ($resql) {
            setEventMessages("Accès ajouté avec succès", null, 'mesgs');
        } else {
            if ($db->lasterrno() == 'DB_ERROR_RECORD_ALREADY_EXISTS') {
                setEventMessages("Cet accès existe déjà", null, 'errors');
            } else {
                setEventMessages($db->lasterror(), null, 'errors');
            }
        }
    } else {
        setEventMessages("Veuillez sélectionner un client et un entrepôt", null, 'errors');
    }
}

// Delete access
if ($action == 'delete') {
    $rowid = GETPOST('rowid', 'int');
    if ($rowid > 0) {
        $sql = "DELETE FROM " . MAIN_DB_PREFIX . "clientstock_access WHERE rowid = " . ((int) $rowid);
        $resql = $db->query($sql);
        if ($resql) {
            setEventMessages("Accès supprimé", null, 'mesgs');
        }
    }
}

// View
llxHeader();

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">' . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans("ClientStock Setup"), $linkback, 'title_setup');

print '<br>';

// Form to add
print '<form method="POST" action="' . htmlspecialchars($_SERVER["PHP_SELF"]) . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="add">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans("Client") . '</td>';
print '<td>' . $langs->trans("Warehouse") . '</td>';
print '<td></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>';
$form = new Form($db);
print $form->select_company(0, 'fk_soc', 's.client IN (1,2,3)', 1);
print '</td>';

print '<td>';
$sql_w = "SELECT rowid, ref, lieu FROM " . MAIN_DB_PREFIX . "entrepot WHERE 1 = 1";
if (isset($conf->entity)) {
    $sql_w .= " AND entity = " . ((int) $conf->entity);
}
$sql_w .= " ORDER BY ref";
$resql_w = $db->query($sql_w);
$entrepots = array();
if ($resql_w) {
    while ($obj_w = $db->fetch_object($resql_w)) {
        $entrepots[$obj_w->rowid] = $obj_w->ref . ($obj_w->lieu ? ' - ' . $obj_w->lieu : '');
    }
}
print $form->selectarray('fk_entrepot', $entrepots, 0, 1);
print '</td>';

print '<td align="center"><input type="submit" class="button" value="' . $langs->trans("Add") . '"></td>';
print '</tr>';
print '</table>';
print '</form>';

print '<br><br>';

// List of accesses
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans("Client") . '</td>';
print '<td>' . $langs->trans("Warehouse") . '</td>';
print '<td align="center">' . $langs->trans("Action") . '</td>';
print '</tr>';

$sql = "SELECT a.rowid, a.fk_soc, a.fk_entrepot, s.nom as client_name, e.ref as warehouse_ref, e.lieu as warehouse_label";
$sql .= " FROM " . MAIN_DB_PREFIX . "clientstock_access as a";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON a.fk_soc = s.rowid";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "entrepot as e ON a.fk_entrepot = e.rowid";
$sql .= " ORDER BY s.nom ASC";

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    if ($num > 0) {
        $i = 0;
        while ($i < $num) {
            $obj = $db->fetch_object($resql);
            print '<tr class="oddeven">';
            print '<td>' . htmlspecialchars($obj->client_name) . '</td>';
            print '<td>' . htmlspecialchars($obj->warehouse_ref . ' - ' . $obj->warehouse_label) . '</td>';
            print '<td align="center">';
            print '<a href="' . $_SERVER["PHP_SELF"] . '?action=delete&rowid=' . $obj->rowid . '&token=' . newToken() . '" onclick="return confirm(\'Êtes-vous sûr de vouloir supprimer cet accès ?\')">' . img_delete() . '</a>';
            print '</td>';
            print '</tr>';
            $i++;
        }
    } else {
        print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">Aucun accès configuré</span></td></tr>';
    }
}

print '</table>';

llxFooter();
$db->close();
