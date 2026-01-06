<?php

namespace Src\Controller;

use Src\Database;
use PDO;

class FilterController {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::connect();
    }

    public function saveFilter() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        $stmt = $this->pdo->prepare("INSERT INTO saved_filters (name, filter_config) VALUES (?, ?)");
        $stmt->execute([$input['name'], json_encode($input['config'])]);
        echo json_encode(['status' => 'ok', 'id' => $this->pdo->lastInsertId()]);
    }

    public function deleteFilter() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        $stmt = $this->pdo->prepare("DELETE FROM saved_filters WHERE id = ?");
        $stmt->execute([$input['id']]);
        echo json_encode(['status' => 'ok']);
    }
}
