<?php
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modClientStock extends DolibarrModules
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->numero = 500000;
        $this->rights_class = 'clientstock';
        $this->family = 'products';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Allows external clients to view their assigned stock from specific warehouses.";
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->picto = 'object_stock';
        $this->module_parts = array(
            'menus' => 1,
        );
        $this->dirs = array("/clientstock/sql/");
        $this->config_page_url = array(DOL_URL_ROOT . "/custom/clientstock/admin/setup.php");

        $this->depends = array("modProduct", "modStock");
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array("clientstock@clientstock");

        $this->dictionaries = array();

        // Permissions
        $this->rights = array();
        $this->rights[1][0] = 500001;
        $this->rights[1][1] = 'Read Client Stock';
        $this->rights[1][2] = 'r';
        $this->rights[1][3] = 1;
        $this->rights[1][4] = 'read';

        $this->rights[2][0] = 500002;
        $this->rights[2][1] = 'Setup Client Stock access (Admin)';
        $this->rights[2][2] = 'a';
        $this->rights[2][3] = 0;
        $this->rights[2][4] = 'setup';

        // Menus
        $this->menu = array();

        $this->menu[0] = array(
            'fk_menu' => '',
            'type' => 'top',
            'titre' => 'ClientStock',
            'mainmenu' => 'clientstock',
            'leftmenu' => '',
            'url' => '/custom/clientstock/clientstock_list.php',
            'langs' => 'clientstock@clientstock',
            'position' => 1000,
            'enabled' => '1',
            'perms' => '$user->rights->clientstock->read',
            'target' => '',
            'user' => 2 // 2 = Visible to external users (and internal)
        );

        $this->menu[1] = array(
            'fk_menu' => 'fk_mainmenu=clientstock',
            'type' => 'left',
            'titre' => 'MyStock',
            'mainmenu' => 'clientstock',
            'leftmenu' => 'clientstock_list',
            'url' => '/custom/clientstock/clientstock_list.php',
            'langs' => 'clientstock@clientstock',
            'position' => 100,
            'enabled' => '1',
            'perms' => '$user->rights->clientstock->read',
            'target' => '',
            'user' => 2 // 2 = Visible to external users (and internal)
        );
    }

    public function init($options = '')
    {
        $sql = array();
        return $this->_init($sql, $options);
    }

    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
