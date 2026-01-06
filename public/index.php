<?php
/**
 * RECHNUNGSRÜCKLÄUFER MANAGEMENT SYSTEM - Refactored
 */

// Simple Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Src\\';
    $base_dir = dirname(__DIR__) . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

use Src\Controller\EntryController;
use Src\Controller\SchemaController;
use Src\Controller\FilterController;

// Handle API Requests
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'save_schema') {
            (new SchemaController())->saveSchema();
        } elseif ($action === 'save_entry') {
            (new EntryController())->saveEntry();
        } elseif ($action === 'delete_entry') {
            (new EntryController())->deleteEntry();
        } elseif ($action === 'save_filter') {
            (new FilterController())->saveFilter();
        } elseif ($action === 'delete_filter') {
            (new FilterController())->deleteFilter();
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($action === 'get_data') {
            (new EntryController())->getData();
        } elseif ($action === 'get_history') {
            (new EntryController())->getHistory();
        }
    }
    exit;
}

// Serve Main View
require dirname(__DIR__) . '/src/View/main.php';
