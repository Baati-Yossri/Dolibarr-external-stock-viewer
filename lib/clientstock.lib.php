<?php
/**
 * ClientStock shared library functions
 * Consolidated badge rendering and utility functions used across the module.
 */

/**
 * Render a Dolibarr-style badge for reservation status
 * @param string $status  Status code: '0'=Non réservé, '1'=Partiel, '2'=Réservé, '3'=Consommé
 * @return string HTML badge
 */
function clientstock_get_reservation_status_badge($status) {
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

/**
 * Render a Dolibarr-style badge for production/control workflow status
 * Used for both production_status and control_status since they share the same values.
 * @param string $status  Status string: 'todo', 'encours', 'en_cours', 'lance', 'termine'
 * @return string HTML badge
 */
function clientstock_get_workflow_status_badge($status) {
    if (empty($status) || $status == 'todo') {
        return '<span class="badge badge-warning" style="background-color: #f0ad4e; color: #fff; padding: 2px 6px; border-radius: 3px;">En attente</span>';
    } elseif ($status == 'encours' || $status == 'en_cours' || $status == 'lance') {
        return '<span class="badge badge-info" style="background-color: #5bc0de; color: #fff; padding: 2px 6px; border-radius: 3px;">En cours</span>';
    } elseif ($status == 'termine') {
        return '<span class="badge badge-success" style="background-color: #5cb85c; color: #fff; padding: 2px 6px; border-radius: 3px;">Terminé</span>';
    }
    return '<span class="badge badge-secondary" style="background-color: #777; color: #fff; padding: 2px 6px; border-radius: 3px;">' . ucfirst(str_replace('_', ' ', $status)) . '</span>';
}

/**
 * Get stock quantity CSS class based on level thresholds
 * @param float $qty  Physical stock quantity
 * @return string CSS class name
 */
function clientstock_get_stock_level_class($qty) {
    if ($qty <= 0) {
        return 'stock-level-critical';
    } elseif ($qty < 10) {
        return 'stock-level-low';
    } elseif ($qty < 50) {
        return 'stock-level-medium';
    }
    return 'stock-level-healthy';
}
