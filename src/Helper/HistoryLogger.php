<?php

namespace Src\Helper;

use PDO;

class HistoryLogger {
    public static function log($pdo, $entryId, $message) {
        $stmt = $pdo->prepare("INSERT INTO entry_history (entry_id, message) VALUES (?, ?)");
        $stmt->execute([$entryId, $message]);
    }
}
