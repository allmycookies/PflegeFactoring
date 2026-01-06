<?php

namespace Src\Controller;

use Src\Database;
use Src\Helper\HistoryLogger;
use PDO;
use Exception;

class EntryController {

    private $pdo;
    private $uploadDir;

    public function __construct() {
        $this->pdo = Database::connect();
        $this->uploadDir = dirname(__DIR__, 2) . '/public/uploads/';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function saveEntry() {
        header('Content-Type: application/json');

        $id = $_POST['id'] ?? null;
        $jsonData = json_decode($_POST['data'] ?? '{}', true);
        $schema = json_decode($this->pdo->query("SELECT value FROM config WHERE key = 'form_schema'")->fetchColumn() ?: '[]', true);

        $fieldLabels = [];
        foreach ($schema as $f) $fieldLabels[$f['id']] = $f['label'];

        $existingData = [];
        $isNew = true;

        if ($id && $id !== 'null') {
            $isNew = false;
            $stmt = $this->pdo->prepare("SELECT data FROM entries WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) $existingData = json_decode($row['data'], true);
        }

        // File Upload
        $fileUploaded = false;
        if (!empty($_FILES)) {
            foreach ($_FILES as $fieldId => $fileInfo) {
                // Determine if it is a single file upload or multiple
                // Standard structure for single: ['name' => '...', 'type' => '...', ...]
                // Standard structure for multiple: ['name' => ['...', '...'], 'type' => ['...', '...'], ...]

                $filesToProcess = [];
                if (is_array($fileInfo['name'])) {
                    // Re-organize the multiple files array
                    $count = count($fileInfo['name']);
                    for ($i = 0; $i < $count; $i++) {
                        $filesToProcess[] = [
                            'name' => $fileInfo['name'][$i],
                            'type' => $fileInfo['type'][$i],
                            'tmp_name' => $fileInfo['tmp_name'][$i],
                            'error' => $fileInfo['error'][$i],
                            'size' => $fileInfo['size'][$i]
                        ];
                    }
                } else {
                    $filesToProcess[] = $fileInfo;
                }

                $newFiles = [];
                foreach ($filesToProcess as $file) {
                    if ($file['error'] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        if ($ext !== 'pdf') throw new Exception("Nur PDF Dateien erlaubt!");

                        $filename = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($file['name']));
                        if (move_uploaded_file($file['tmp_name'], $this->uploadDir . $filename)) {
                            $newFiles[] = $filename;
                            $fileUploaded = true;
                        }
                    }
                }

                if (!empty($newFiles)) {
                    // Append to existing files if any
                    // Check jsonData first (in case frontend sent explicit list), then existingData
                    $currentFiles = isset($jsonData[$fieldId]) ? $jsonData[$fieldId] : ($existingData[$fieldId] ?? []);
                    if (!is_array($currentFiles)) {
                        $currentFiles = $currentFiles ? [$currentFiles] : [];
                    }
                    $jsonData[$fieldId] = array_merge($currentFiles, $newFiles);
                }
            }
        }

        // Merge Data
        foreach ($existingData as $key => $val) {
            if (!isset($jsonData[$key])) $jsonData[$key] = $val;
        }

        $finalDataJson = json_encode($jsonData);

        if (!$isNew) {
            // Update
            $stmt = $this->pdo->prepare("UPDATE entries SET data = ? WHERE id = ?");
            $stmt->execute([$finalDataJson, $id]);

            // History
            if ($fileUploaded) HistoryLogger::log($this->pdo, $id, "Neues PDF hochgeladen");

            foreach ($jsonData as $k => $v) {
                $oldV = $existingData[$k] ?? '';
                if (is_array($v)) $v = implode(', ', $v);
                if (is_array($oldV)) $oldV = implode(', ', $oldV);

                if ((string)$v !== (string)$oldV && $k !== 'file') {
                    $label = $fieldLabels[$k] ?? $k;
                    $vShort = strlen($v) > 20 ? substr($v, 0, 20).'...' : $v;
                    $oldShort = strlen($oldV) > 20 ? substr($oldV, 0, 20).'...' : $oldV;
                    HistoryLogger::log($this->pdo, $id, "$label geändert: '$oldShort' -> '$vShort'");
                }
            }

        } else {
            // Insert
            $stmt = $this->pdo->prepare("INSERT INTO entries (data) VALUES (?)");
            $stmt->execute([$finalDataJson]);
            $id = $this->pdo->lastInsertId();
            HistoryLogger::log($this->pdo, $id, "Vorgang erstellt");
            if ($fileUploaded) HistoryLogger::log($this->pdo, $id, "PDF hochgeladen");
        }

        echo json_encode(['status' => 'ok', 'id' => $id]);
    }

    public function deleteEntry() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        $this->pdo->exec("PRAGMA foreign_keys = ON");
        $stmt = $this->pdo->prepare("DELETE FROM entries WHERE id = ?");
        $stmt->execute([$input['id']]);
        $this->pdo->prepare("DELETE FROM entry_history WHERE entry_id = ?")->execute([$input['id']]);
        echo json_encode(['status' => 'ok']);
    }

    public function getData() {
        header('Content-Type: application/json');
        $schema = $this->pdo->query("SELECT value FROM config WHERE key = 'form_schema'")->fetchColumn();
        $layout = $this->pdo->query("SELECT value FROM config WHERE key = 'layout_config'")->fetchColumn();
        $entries = $this->pdo->query("SELECT * FROM entries ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        $filters = $this->pdo->query("SELECT * FROM saved_filters")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'schema' => $schema ? json_decode($schema) : [],
            'layout' => $layout ? json_decode($layout) : ['colLeft' => 6, 'colRight' => 6],
            'entries' => $entries,
            'filters' => $filters
        ]);
    }

    public function getHistory() {
        header('Content-Type: application/json');
        $id = $_GET['id'] ?? 0;
        $history = $this->pdo->prepare("SELECT * FROM entry_history WHERE entry_id = ? ORDER BY created_at DESC");
        $history->execute([$id]);
        echo json_encode($history->fetchAll(PDO::FETCH_ASSOC));
    }
}
