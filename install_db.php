<?php
require '../../main.inc.php';

$sql = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."clientstock_access (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  fk_soc integer NOT NULL,
  fk_entrepot integer NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  datec datetime,
  fk_user_creat integer,
  fk_user_modif integer
) ENGINE=InnoDB;";

$res = $db->query($sql);
if (!$res) {
    echo "Error creating table: " . $db->lasterror() . "\n";
} else {
    echo "Table created successfully.\n";
}

$sql2 = "ALTER TABLE ".MAIN_DB_PREFIX."clientstock_access ADD UNIQUE INDEX uk_clientstock_access (fk_soc, fk_entrepot);";
$res2 = $db->query($sql2);
if (!$res2) {
    echo "Index might already exist or error: " . $db->lasterror() . "\n";
} else {
    echo "Index created successfully.\n";
}
