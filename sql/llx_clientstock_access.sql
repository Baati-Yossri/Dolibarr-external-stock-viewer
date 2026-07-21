CREATE TABLE llx_clientstock_access (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  fk_soc integer NOT NULL,
  fk_entrepot integer NOT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  datec datetime,
  fk_user_creat integer,
  fk_user_modif integer
) ENGINE=innodb;
