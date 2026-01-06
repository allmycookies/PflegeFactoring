<?php

namespace Src\Controller;

use Src\Database;
use PDO;

class SchemaController {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::connect();
    }

    public function saveSchema() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        // Schema speichern
        $stmt = $this->pdo->prepare("INSERT INTO config (key, value) VALUES ('form_schema', ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
        $stmt->execute([json_encode($input['schema'])]);

        // Layout Konfiguration speichern
        if (isset($input['layout'])) {
            $stmt = $this->pdo->prepare("INSERT INTO config (key, value) VALUES ('layout_config', ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
            $stmt->execute([json_encode($input['layout'])]);
        }

        echo json_encode(['status' => 'ok']);
    }
}
