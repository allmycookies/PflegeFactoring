<?php
/**
 * RECHNUNGSRÜCKLÄUFER MANAGEMENT SYSTEM - V7.1 (Fix Editor & Split View)
 */

$dbFile = __DIR__ . '/database.sqlite';
$uploadDir = __DIR__ . '/uploads/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- DB SETUP ---
$pdo->exec("CREATE TABLE IF NOT EXISTS config (key TEXT PRIMARY KEY, value TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS entries (id INTEGER PRIMARY KEY AUTOINCREMENT, data TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE IF NOT EXISTS saved_filters (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, filter_config TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS entry_history (id INTEGER PRIMARY KEY AUTOINCREMENT, entry_id INTEGER, message TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(entry_id) REFERENCES entries(id) ON DELETE CASCADE)");

// --- HELPER FUNCTION: HISTORY LOGGING ---
function logHistory($pdo, $entryId, $message) {
    $stmt = $pdo->prepare("INSERT INTO entry_history (entry_id, message) VALUES (?, ?)");
    $stmt->execute([$entryId, $message]);
}

// --- API ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        if ($action === 'save_schema') {
            header('Content-Type: application/json');
            
            // Schema speichern
            $stmt = $pdo->prepare("INSERT INTO config (key, value) VALUES ('form_schema', ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
            $stmt->execute([json_encode($input['schema'])]);
            
            // Layout Konfiguration speichern (NEU)
            if (isset($input['layout'])) {
                $stmt = $pdo->prepare("INSERT INTO config (key, value) VALUES ('layout_config', ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
                $stmt->execute([json_encode($input['layout'])]);
            }

            echo json_encode(['status' => 'ok']);
        } 
        elseif ($action === 'save_entry') {
            header('Content-Type: application/json');
            
            $id = $_POST['id'] ?? null;
            $jsonData = json_decode($_POST['data'] ?? '{}', true);
            $schema = json_decode($pdo->query("SELECT value FROM config WHERE key = 'form_schema'")->fetchColumn() ?: '[]', true);
            
            $fieldLabels = [];
            foreach ($schema as $f) $fieldLabels[$f['id']] = $f['label'];

            $existingData = [];
            $isNew = true;

            if ($id && $id !== 'null') {
                $isNew = false;
                $stmt = $pdo->prepare("SELECT data FROM entries WHERE id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) $existingData = json_decode($row['data'], true);
            }

            // File Upload
            $fileUploaded = false;
            if (!empty($_FILES)) {
                foreach ($_FILES as $fieldId => $file) {
                    if ($file['error'] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        if ($ext !== 'pdf') throw new Exception("Nur PDF Dateien erlaubt!");
                        
                        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($file['name']));
                        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                            $jsonData[$fieldId] = $filename;
                            $fileUploaded = true;
                        }
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
                $stmt = $pdo->prepare("UPDATE entries SET data = ? WHERE id = ?");
                $stmt->execute([$finalDataJson, $id]);
                
                // History
                if ($fileUploaded) logHistory($pdo, $id, "Neues PDF hochgeladen");
                
                foreach ($jsonData as $k => $v) {
                    $oldV = $existingData[$k] ?? '';
                    if (is_array($v)) $v = implode(', ', $v);
                    if (is_array($oldV)) $oldV = implode(', ', $oldV);
                    
                    if ((string)$v !== (string)$oldV && $k !== 'file') {
                        $label = $fieldLabels[$k] ?? $k;
                        $vShort = strlen($v) > 20 ? substr($v, 0, 20).'...' : $v;
                        $oldShort = strlen($oldV) > 20 ? substr($oldV, 0, 20).'...' : $oldV;
                        logHistory($pdo, $id, "$label geändert: '$oldShort' -> '$vShort'");
                    }
                }

            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO entries (data) VALUES (?)");
                $stmt->execute([$finalDataJson]);
                $id = $pdo->lastInsertId();
                logHistory($pdo, $id, "Vorgang erstellt");
                if ($fileUploaded) logHistory($pdo, $id, "PDF hochgeladen");
            }

            echo json_encode(['status' => 'ok', 'id' => $id]);
        }
        elseif ($action === 'delete_entry') {
            header('Content-Type: application/json');
            $pdo->exec("PRAGMA foreign_keys = ON"); 
            $stmt = $pdo->prepare("DELETE FROM entries WHERE id = ?");
            $stmt->execute([$input['id']]);
            $pdo->prepare("DELETE FROM entry_history WHERE entry_id = ?")->execute([$input['id']]);
            echo json_encode(['status' => 'ok']);
        }
        elseif ($action === 'save_filter') {
            header('Content-Type: application/json');
            $stmt = $pdo->prepare("INSERT INTO saved_filters (name, filter_config) VALUES (?, ?)");
            $stmt->execute([$input['name'], json_encode($input['config'])]);
            echo json_encode(['status' => 'ok', 'id' => $pdo->lastInsertId()]);
        }
        elseif ($action === 'delete_filter') {
            header('Content-Type: application/json');
            $stmt = $pdo->prepare("DELETE FROM saved_filters WHERE id = ?");
            $stmt->execute([$input['id']]);
            echo json_encode(['status' => 'ok']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    if ($_GET['action'] === 'get_data') {
        $schema = $pdo->query("SELECT value FROM config WHERE key = 'form_schema'")->fetchColumn();
        $layout = $pdo->query("SELECT value FROM config WHERE key = 'layout_config'")->fetchColumn();
        $entries = $pdo->query("SELECT * FROM entries ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        $filters = $pdo->query("SELECT * FROM saved_filters")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'schema' => $schema ? json_decode($schema) : [],
            'layout' => $layout ? json_decode($layout) : ['colLeft' => 6, 'colRight' => 6], // Standard 50/50
            'entries' => $entries,
            'filters' => $filters
        ]);
    }
    elseif ($_GET['action'] === 'get_history') {
        $id = $_GET['id'] ?? 0;
        $history = $pdo->prepare("SELECT * FROM entry_history WHERE entry_id = ? ORDER BY created_at DESC");
        $history->execute([$id]);
        echo json_encode($history->fetchAll(PDO::FETCH_ASSOC));
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PflegeFactoring Manager Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        :root { --sidebar-width: 260px; --bg-color: #f4f6f8; --primary-color: #0d6efd; }
        body { background-color: var(--bg-color); font-family: 'Inter', system-ui, -apple-system, sans-serif; overflow: hidden; height: 100vh; }
        
        /* Layout Structure */
        #sidebar-wrapper { width: var(--sidebar-width); position: fixed; top: 0; left: 0; height: 100vh; background: #212529; color: #fff; z-index: 1000; overflow-y: auto; }
        #page-content-wrapper { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); height: 100vh; display: flex; flex-direction: column; }
        
        .sidebar-heading { padding: 1.5rem 1.25rem; font-size: 1.2rem; font-weight: bold; background: #1b1e21; }
        .list-group-item { background: transparent; color: #adb5bd; border: none; padding: 15px 20px; cursor: pointer; }
        .list-group-item:hover, .list-group-item.active { background: #343a40; color: #fff; border-left: 4px solid var(--primary-color); }
        .list-group-item i { margin-right: 10px; width: 20px; text-align: center; }

        /* Helpers */
        .w-custom-12 { width: 12.5%; flex: 0 0 12.5%; } .w-custom-25 { width: 25%; flex: 0 0 25%; }
        .w-custom-33 { width: 33.3333%; flex: 0 0 33.3333%; } .w-custom-50 { width: 50%; flex: 0 0 50%; }
        .w-custom-100 { width: 100%; flex: 0 0 100%; }
        .hidden { display: none !important; }

        /* EDITOR CSS */
        .canvas-area { background-color: #ffffff; background-image: radial-gradient(#e5e7eb 1px, transparent 1px); background-size: 20px 20px; border: 1px solid #dee2e6; min-height: 600px; padding: 30px; border-radius: 8px; }
        .editor-row-container { border: 1px dashed #ced4da; border-radius: 8px; padding: 10px; margin-bottom: 15px; background: rgba(255,255,255,0.6); position: relative; }
        .form-element-wrapper { background: white; border: 1px solid #e0e0e0; border-left: 4px solid var(--primary-color); border-radius: 6px; padding: 12px; cursor: grab; transition: all 0.2s; height: 100%; box-shadow: 0 2px 4px rgba(0,0,0,0.03); }
        .form-element-wrapper:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.08); }
        .form-element-wrapper.selected { border-color: var(--primary-color); background: #f8fbff; outline: 2px solid rgba(13,110,253,0.3); }
        .form-element-wrapper.drag-over-target { border-top: 4px solid #198754 !important; background: #e8f5e9 !important; transform: scale(1.02); }
        .type-money { border-left-color: #198754; } .type-text { border-left-color: #0d6efd; } .type-date { border-left-color: #ffc107; } .type-file { border-left-color: #6610f2; }
        
        /* Draggable Items in Palette (Damit es nicht kaputt aussieht) */
        .draggable-item { padding: 10px; margin-bottom: 8px; background: #fff; border: 1px solid #dee2e6; border-radius: 4px; cursor: grab; font-size: 0.9rem; }
        .draggable-item:hover { background-color: #e9ecef; border-color: #adb5bd; }

        /* SPLIT VIEW STYLES */
        .detail-pane { height: 100%; overflow-y: auto; background: #fff; border-right: 1px solid #dee2e6; padding: 0; }
        .pdf-pane { height: 100%; background: #525659; padding: 0; overflow: hidden; display: flex; flex-direction: column; }
        .pdf-frame { width: 100%; height: 100%; border: none; flex-grow: 1; }
        .pdf-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #fff; }
        
        /* TIMELINE STYLES */
        .history-timeline { list-style: none; padding: 0; margin-top: 1rem; position: relative; }
        .history-item { padding-left: 20px; position: relative; margin-bottom: 15px; border-left: 2px solid #e0e0e0; }
        .history-item::before { content: ''; position: absolute; left: -5px; top: 0; width: 8px; height: 8px; border-radius: 50%; background: var(--primary-color); }
        .history-date { font-size: 0.75rem; color: #999; }
        .history-msg { font-size: 0.9rem; color: #333; font-weight: 500; }
        
        /* Scroll Helper for normal pages inside flex layout */
        .scroll-container { overflow-y: auto; height: 100%; padding: 20px; }
    </style>
</head>
<body>

<div class="d-flex" id="wrapper">
    <div id="sidebar-wrapper">
        <div class="sidebar-heading text-uppercase">Pflege<span class="text-primary">Factor</span></div>
        <div class="list-group list-group-flush">
            <button onclick="router('dashboard')" class="list-group-item list-group-item-action active" id="nav-dashboard"><i class="bi bi-speedometer2"></i> Dashboard</button>
            <button onclick="startNewEntry()" class="list-group-item list-group-item-action" id="nav-entry"><i class="bi bi-plus-circle"></i> Neuer Rückläufer</button>
            <button onclick="router('editor')" class="list-group-item list-group-item-action" id="nav-editor"><i class="bi bi-layers"></i> Formular Editor</button>
        </div>
    </div>

    <div id="page-content-wrapper">
        
        <main id="view-dashboard" class="container-fluid fade-in scroll-container">
            <div class="d-flex justify-content-between align-items-center mb-4"><h2 class="h4 mb-0 text-gray-800">Übersicht</h2></div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-funnel"></i> Smart Filter</h6>
                        <div>
                            <select id="saved-filters-select" class="form-select form-select-sm d-inline-block w-auto" onchange="loadFilterPreset(this.value)"><option value="">-- Ansicht laden --</option></select>
                            <button class="btn btn-sm btn-outline-danger ms-1" onclick="deleteCurrentFilter()"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end" id="dashboard-filters"></div>
                    <div class="mt-3 pt-3 border-top d-flex justify-content-between">
                        <button class="btn btn-outline-secondary btn-sm" onclick="resetFilters()">Reset</button>
                        <button class="btn btn-primary btn-sm" onclick="saveCurrentFilter()">Ansicht speichern</button>
                    </div>
                </div>
            </div>

            <div class="row mb-4" id="dashboard-kpis"></div>

            <div class="card shadow-sm mb-5">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light"><tr id="table-head"></tr></thead>
                            <tbody id="table-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>

        <main id="view-entry" class="container-fluid p-0 hidden h-100">
            <div class="row g-0 h-100">
                <div id="split-left" class="detail-pane">
                    <div class="p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                            <div>
                                <h5 class="mb-0" id="entry-title">Rückläufer</h5>
                                <span class="badge bg-primary mt-1" id="entry-id-badge">Neu</span>
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="router('dashboard')"><i class="bi bi-arrow-left"></i> Zurück</button>
                                <button type="button" onclick="document.getElementById('save-btn-hidden').click()" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Speichern</button>
                            </div>
                        </div>

                        <form id="dynamic-form" onsubmit="submitEntry(event)" enctype="multipart/form-data">
                            <div id="form-render-area" class="row g-3"></div>
                            <button type="submit" id="save-btn-hidden" class="d-none"></button>
                        </form>

                        <div class="mt-5">
                            <h6 class="border-bottom pb-2 fw-bold text-muted text-uppercase small"><i class="bi bi-clock-history"></i> Verlauf</h6>
                            <div id="history-container"></div>
                        </div>
                    </div>
                </div>

                <div id="split-right" class="pdf-pane">
                    <div id="pdf-viewer-placeholder" class="h-100 w-100"></div>
                </div>
            </div>
        </main>

        <main id="view-editor" class="container-fluid hidden scroll-container">
            <div class="row h-100">
                <div class="col-md-3 col-xl-2">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-header bg-white fw-bold">Palette</div>
                        <div class="card-body overflow-auto bg-light">
                            <div class="draggable-item" draggable="true" ondragstart="dragStartPalette(event, 'text')" onclick="addField('text')"><i class="bi bi-type"></i> Textzeile</div>
                            <div class="draggable-item" draggable="true" ondragstart="dragStartPalette(event, 'textarea')" onclick="addField('textarea')"><i class="bi bi-justify-left"></i> Textfeld</div>
                            <div class="draggable-item" draggable="true" ondragstart="dragStartPalette(event, 'money')" onclick="addField('money')"><i class="bi bi-currency-euro"></i> Betrag</div>
                            <div class="draggable-item" draggable="true" ondragstart="dragStartPalette(event, 'number')" onclick="addField('number')"><i class="bi bi-123"></i> Ganzzahl</div>
                            <div class="draggable-item" draggable="true" ondragstart="dragStartPalette(event, 'date')" onclick="addField('date')"><i class="bi bi-calendar"></i> Datum</div>
                            <div class="draggable-item" draggable="true" ondragstart="dragStartPalette(event, 'select')" onclick="addField('select')"><i class="bi bi-menu-button-wide"></i> Dropdown</div>
                            <div class="draggable-item" draggable="true" ondragstart="dragStartPalette(event, 'checkbox')" onclick="addField('checkbox')"><i class="bi bi-check-square"></i> Checkbox</div>
                            <div class="draggable-item" draggable="true" ondragstart="dragStartPalette(event, 'file')" onclick="addField('file')"><i class="bi bi-file-earmark-pdf"></i> PDF Upload</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-xl-7">
                    <div class="d-flex justify-content-between mb-2"><h5 class="text-muted">Formular Layout</h5></div>
                    <div class="canvas-area" id="editor-canvas" ondragover="allowDrop(event)" ondrop="dropOnCanvas(event)" onclick="selectField(null)"></div>
                </div>
                
                <div class="col-md-3 col-xl-3">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-header bg-white fw-bold">Eigenschaften</div>
                        <div class="card-body overflow-auto" id="prop-content"></div>
                        <div class="card-footer bg-white"><button class="btn btn-success w-100" onclick="saveSchema()"><i class="bi bi-save"></i> Speichern</button></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// STATE
let appState = { 
    schema: [], 
    entries: [], 
    savedFilters: [], 
    layout: { colLeft: 6, colRight: 6 }, // Standard 50%
    editingEntryId: null, 
    selectedFieldId: null, 
    activeFilters: {} 
};

document.addEventListener('DOMContentLoaded', () => fetchData());

async function fetchData() {
    const res = await fetch('?action=get_data');
    const data = await res.json();
    appState.schema = data.schema || [];
    appState.entries = data.entries.map(e => ({...e, data: JSON.parse(e.data)}));
    appState.savedFilters = data.filters;
    if(data.layout) appState.layout = data.layout;
    
    renderEditorCanvas();
    renderProperties(null);
    renderDashboardFilters();
    applyDashboardFilters();
    updateSavedFiltersDropdown();
}

function router(view) {
    document.querySelectorAll('main').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.list-group-item').forEach(el => el.classList.remove('active'));
    document.getElementById('view-' + view).classList.remove('hidden');
    document.getElementById('nav-' + view).classList.add('active');
    
    // Layout anwenden wenn Entry View geöffnet wird
    if(view === 'entry') {
        const left = document.getElementById('split-left');
        const right = document.getElementById('split-right');
        left.className = `detail-pane col-md-${appState.layout.colLeft}`;
        right.className = `pdf-pane col-md-${appState.layout.colRight}`;
    }
    
    if (view === 'dashboard') { renderDashboardFilters(); applyDashboardFilters(); }
}

// --- ENTRY MANAGEMENT ---
function startNewEntry() {
    appState.editingEntryId = null;
    document.getElementById('entry-title').innerText = "Neuer Rückläufer";
    document.getElementById('entry-id-badge').innerText = "Neu";
    renderEntryForm();
    updatePdfViewer(null);
    document.getElementById('history-container').innerHTML = '<div class="text-muted small mt-2">Historie beginnt nach dem Speichern.</div>';
    router('entry');
}

function editEntry(id) {
    appState.editingEntryId = id;
    document.getElementById('entry-title').innerText = "Vorgang bearbeiten";
    document.getElementById('entry-id-badge').innerText = "#" + id;
    renderEntryForm();
    
    // PDF suchen
    const entry = appState.entries.find(e => e.id === id);
    let pdfFile = null;
    if(entry) {
        const fileField = appState.schema.find(f => f.type === 'file');
        if(fileField && entry.data[fileField.id]) {
            pdfFile = entry.data[fileField.id];
        }
    }
    updatePdfViewer(pdfFile);
    loadHistory(id);
    router('entry');
}

function updatePdfViewer(filename) {
    const container = document.getElementById('pdf-viewer-placeholder');
    if (filename) {
        container.innerHTML = `<iframe src="uploads/${filename}" class="pdf-frame"></iframe>`;
    } else {
        container.innerHTML = `<div class="pdf-placeholder"><i class="bi bi-file-earmark-x fs-1 opacity-50"></i><div class="mt-2 opacity-75">Kein Dokument</div></div>`;
    }
}

async function loadHistory(id) {
    const con = document.getElementById('history-container');
    con.innerHTML = '<div class="spinner-border text-primary spinner-border-sm mt-2"></div>';
    
    const res = await fetch(`?action=get_history&id=${id}`);
    const logs = await res.json();
    
    if(logs.length === 0) {
        con.innerHTML = '<div class="text-muted small mt-2">Keine Änderungen bisher.</div>';
        return;
    }
    
    let html = '<ul class="history-timeline">';
    logs.forEach(log => {
        const date = new Date(log.created_at).toLocaleString('de-DE');
        html += `
            <li class="history-item">
                <div class="history-msg">${log.message}</div>
                <div class="history-date">${date}</div>
            </li>`;
    });
    html += '</ul>';
    con.innerHTML = html;
}

// --- FORM RENDERING ---
function renderEntryForm() {
    const container = document.getElementById('form-render-area');
    container.innerHTML = '';
    let currentData = {};
    if (appState.editingEntryId) {
        const entry = appState.entries.find(e => e.id === appState.editingEntryId);
        if (entry) currentData = entry.data;
    }
    
    appState.schema.forEach(field => {
        const wrapper = document.createElement('div');
        wrapper.className = `w-custom-${field.width}`;
        const label = document.createElement('label');
        label.className = "form-label fw-bold small text-uppercase mb-1";
        label.innerText = field.label;
        wrapper.appendChild(label);
        
        let input;
        const val = currentData[field.id] || '';
        
        if (field.type === 'file') {
            input = document.createElement('input');
            input.type = 'file'; input.className = 'form-control'; input.name = field.id; input.accept = 'application/pdf';
            if(val) {
                const hint = document.createElement('div'); hint.className = "small text-success mt-1"; hint.innerText = "Vorhanden: " + val;
                wrapper.appendChild(hint);
            }
        } else if (field.type === 'textarea') {
            input = document.createElement('textarea'); input.className = "form-control"; input.rows = 3; input.name = field.id; input.value = val;
        } else if (field.type === 'select') {
            input = document.createElement('select'); input.className = "form-select"; input.name = field.id;
            input.innerHTML = '<option value="">Wählen...</option>' + (field.options||'').split(',').map(o=>`<option value="${o.trim()}" ${val==o.trim()?'selected':''}>${o.trim()}</option>`).join('');
        } else if (field.type === 'radio' || field.type === 'checkbox') {
            input = document.createElement('div');
            (field.options||'').split(',').forEach(o => {
                const opt = o.trim();
                const div = document.createElement('div'); div.className = "form-check form-check-inline";
                const i = document.createElement('input'); i.className = "form-check-input";
                i.type = field.type; i.name = field.id + (field.type==='checkbox'?'[]':''); i.value = opt;
                if ((Array.isArray(val) && val.includes(opt)) || val === opt) i.checked = true;
                div.append(i, Object.assign(document.createElement('label'), {className:'form-check-label ms-1', innerText:opt}));
                input.appendChild(div);
            });
        } else {
            input = document.createElement('input'); input.className = "form-control"; input.name = field.id; input.value = val;
            if(field.type === 'date') input.type = 'date';
            if(['number','money','decimal'].includes(field.type)) { input.type = 'number'; input.step = '0.01'; }
        }
        if(input) wrapper.appendChild(input); 
        container.appendChild(wrapper);
    });
}

async function submitEntry(e) {
    e.preventDefault();
    const formData = new FormData();
    const rawForm = new FormData(e.target);
    const dataObj = {};

    appState.schema.forEach(field => {
        if (field.type === 'file') {
            const fileInput = document.querySelector(`input[name="${field.id}"]`);
            if (fileInput && fileInput.files.length > 0) formData.append(field.id, fileInput.files[0]);
        } else if(field.type === 'checkbox') {
            const checked = []; document.querySelectorAll(`input[name="${field.id}[]"]:checked`).forEach(el => checked.push(el.value)); 
            dataObj[field.id] = checked;
        } else if (field.type === 'radio') {
            const checked = document.querySelector(`input[name="${field.id}"]:checked`); 
            dataObj[field.id] = checked ? checked.value : '';
        } else {
            dataObj[field.id] = rawForm.get(field.id);
        }
    });

    formData.append('id', appState.editingEntryId || '');
    formData.append('data', JSON.stringify(dataObj));

    const res = await fetch('?action=save_entry', { method: 'POST', body: formData });
    const json = await res.json();
    
    await fetchData(); 
    
    if(appState.editingEntryId) {
        loadHistory(appState.editingEntryId);
        // Refresh PDF view if file changed
        const newEntry = appState.entries.find(e => e.id == appState.editingEntryId);
        const fileField = appState.schema.find(f => f.type === 'file');
        if(newEntry && fileField && newEntry.data[fileField.id]) updatePdfViewer(newEntry.data[fileField.id]);
    } else {
        if(json.id) editEntry(json.id);
    }
}

async function deleteEntry(id) { if(!confirm('Löschen?')) return; await fetch('?action=delete_entry', { method: 'POST', body: JSON.stringify({ id }) }); fetchData(); }

// --- EDITOR LOGIC ---
let draggedItemIndex=null, draggedFromPaletteType=null;
function dragStartPalette(e,t){draggedFromPaletteType=t;draggedItemIndex=null;e.dataTransfer.effectAllowed='copy';}
function dragStartCanvas(e,i){draggedItemIndex=i;draggedFromPaletteType=null;e.dataTransfer.effectAllowed='move';e.target.classList.add('dragging');e.dataTransfer.setData('text/plain',i);}
function dragEndCanvas(e){e.target.classList.remove('dragging');document.querySelectorAll('.form-element-wrapper').forEach(el=>el.classList.remove('drag-over-target'));}
function dragEnterCanvas(e,i){e.preventDefault();if(draggedItemIndex!==i)e.currentTarget.classList.add('drag-over-target');}
function dragLeaveCanvas(e){e.currentTarget.classList.remove('drag-over-target');}
function allowDrop(e){e.preventDefault();}
function dropOnElement(e,targetIndex){e.preventDefault();e.stopPropagation();e.currentTarget.classList.remove('drag-over-target');
    if(draggedFromPaletteType) addField(draggedFromPaletteType, targetIndex);
    else if(draggedItemIndex!==null&&draggedItemIndex!==targetIndex){const item=appState.schema.splice(draggedItemIndex,1)[0];appState.schema.splice(targetIndex,0,item);renderEditorCanvas();if(appState.selectedFieldId===item.id)selectField(item.id);}
    draggedItemIndex=null;draggedFromPaletteType=null;}
function dropOnCanvas(e){e.preventDefault();if(draggedFromPaletteType)addField(draggedFromPaletteType);draggedItemIndex=null;draggedFromPaletteType=null;}

function addField(type,index=null){
    const id='field_'+Date.now();
    const newField={id:id,type:type,label:type==='file'?'PDF Datei':'Neues Feld',width:'100',options:'Option A, Option B',dashboardList:type!=='file',dashboardSum:false,dashboardFilter:false};
    if(index!==null)appState.schema.splice(index,0,newField);else appState.schema.push(newField);
    selectField(id);renderEditorCanvas();
}
function renderEditorCanvas(){
    const c=document.getElementById('editor-canvas');c.innerHTML='';
    if(appState.schema.length===0){c.innerHTML='<div class="text-center text-muted mt-5"><h5>Leer - Elemente hierher ziehen</h5></div>';return;}
    let row=document.createElement('div');row.className='row g-2 editor-row-container';c.appendChild(row);let wSum=0;
    appState.schema.forEach((f,idx)=>{
        let w=f.width==='100'?100:f.width==='50'?50:f.width==='33'?33.33:f.width==='25'?25:12.5;
        if(wSum+w>100.1){row=document.createElement('div');row.className='row g-2 editor-row-container';c.appendChild(row);wSum=0;}
        const wrap=document.createElement('div');wrap.className=`w-custom-${f.width}`;wSum+=w;
        let tClass='type-text';if(['money','number','decimal'].includes(f.type))tClass='type-money';if(f.type==='date')tClass='type-date';if(f.type==='file')tClass='type-file';
        const inner=document.createElement('div');inner.className=`form-element-wrapper ${tClass} ${appState.selectedFieldId===f.id?'selected':''}`;inner.draggable=true;
        inner.ondragstart=(e)=>dragStartCanvas(e,idx);inner.ondragend=(e)=>dragEndCanvas(e);inner.ondragenter=(e)=>dragEnterCanvas(e,idx);inner.ondragleave=(e)=>dragLeaveCanvas(e);inner.ondragover=(e)=>allowDrop(e);inner.ondrop=(e)=>dropOnElement(e,idx);inner.onclick=(e)=>{e.stopPropagation();selectField(f.id);};
        inner.innerHTML=`<div class="d-flex justify-content-between align-items-start mb-2" style="pointer-events:none;"><div class="fw-bold text-dark text-truncate" style="max-width:80%">${f.label}</div><div class="badge bg-light text-secondary border">${f.width}%</div></div><div class="d-flex justify-content-between align-items-end mt-2" style="pointer-events:none;"><small class="text-muted text-uppercase" style="font-size:10px">${f.type}</small></div>`;
        wrap.appendChild(inner);row.appendChild(wrap);
    });
}
function selectField(id){appState.selectedFieldId=id;renderEditorCanvas();renderProperties(id);}

function renderProperties(id){
    const c=document.getElementById('prop-content');
    
    if(!id) {
        // GLOBAL LAYOUT SETTINGS
        c.innerHTML = `
            <div class="alert alert-light border small text-muted">Kein Feld ausgewählt.<br>Hier können globale Einstellungen vorgenommen werden.</div>
            <h6 class="fw-bold border-bottom pb-2">Detailansicht Layout</h6>
            <div class="mb-3">
                <label class="form-label small fw-bold">Verhältnis Details : PDF</label>
                <select class="form-select" onchange="updateGlobalLayout(this.value)">
                    <option value="6" ${appState.layout.colLeft==6?'selected':''}>50% : 50%</option>
                    <option value="5" ${appState.layout.colLeft==5?'selected':''}>40% : 60%</option>
                    <option value="4" ${appState.layout.colLeft==4?'selected':''}>33% : 67%</option>
                    <option value="3" ${appState.layout.colLeft==3?'selected':''}>25% : 75%</option>
                    <option value="7" ${appState.layout.colLeft==7?'selected':''}>60% : 40%</option>
                    <option value="8" ${appState.layout.colLeft==8?'selected':''}>67% : 33%</option>
                </select>
            </div>
        `;
        return;
    }

    const f=appState.schema.find(f=>f.id===id);if(!f)return;
    c.innerHTML=`
        <div class="mb-3"><label class="form-label fw-bold small">Beschriftung</label><input type="text" class="form-control" value="${f.label}" oninput="updateProp('${id}','label',this.value)"></div>
        <div class="mb-3"><label class="form-label fw-bold small">Typ</label><select class="form-select" onchange="updateProp('${id}','type',this.value)">${['text','textarea','money','number','decimal','date','select','radio','checkbox','file'].map(t=>`<option value="${t}" ${f.type===t?'selected':''}>${t}</option>`).join('')}</select></div>
        <div class="mb-3"><label class="form-label fw-bold small">Breite</label><div class="btn-group w-100"><button class="btn btn-outline-secondary btn-sm" onclick="updateProp('${id}','width','50')">1/2</button><button class="btn btn-outline-secondary btn-sm" onclick="updateProp('${id}','width','100')">1/1</button></div></div>
        ${['select','radio','checkbox'].includes(f.type)?`<div class="mb-3"><label class="form-label fw-bold small">Optionen</label><textarea class="form-control" oninput="updateProp('${id}','options',this.value)">${f.options||''}</textarea></div>`:''}
        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" ${f.dashboardList?'checked':''} onchange="updateProp('${id}','dashboardList',this.checked)"><label class="form-check-label">In Liste</label></div>
        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" ${f.dashboardFilter?'checked':''} onchange="updateProp('${id}','dashboardFilter',this.checked)"><label class="form-check-label">Als Filter</label></div>
        <div class="mt-4"><button class="btn btn-outline-danger w-100" onclick="deleteField('${id}')">Entfernen</button></div>`;
}
function updateProp(id,k,v){const f=appState.schema.find(x=>x.id===id);f[k]=v;renderEditorCanvas();if(k==='type')renderProperties(id);}
function updateGlobalLayout(val) {
    appState.layout.colLeft = parseInt(val);
    appState.layout.colRight = 12 - appState.layout.colLeft;
}
function deleteField(id){if(!confirm('Löschen?'))return;appState.schema=appState.schema.filter(f=>f.id!==id);appState.selectedFieldId=null;renderProperties(null);renderEditorCanvas();}
async function saveSchema(){
    await fetch('?action=save_schema',{
        method:'POST',
        body:JSON.stringify({schema:appState.schema, layout: appState.layout})
    });
    renderDashboardFilters();
    applyDashboardFilters();
}

// --- DASHBOARD FILTERS ---
function renderDashboardFilters(){
    const c=document.getElementById('dashboard-filters');c.innerHTML='';
    appState.schema.filter(f=>f.dashboardFilter).forEach(f=>{
        const col=document.createElement('div');col.className='col-md-3 col-sm-6';
        let vals=new Set();appState.entries.forEach(e=>{let v=e.data[f.id];if(v)Array.isArray(v)?v.forEach(x=>vals.add(x)):vals.add(v);});
        col.innerHTML=`<select class="form-select form-select-sm ${appState.activeFilters[f.id]?'bg-primary text-white':''}" onchange="appState.activeFilters['${f.id}']=this.value;applyDashboardFilters();renderDashboardFilters()"><option value="">${f.label}</option>${Array.from(vals).sort().map(v=>`<option value="${v}" ${appState.activeFilters[f.id]==v?'selected':''}>${v}</option>`).join('')}</select>`;
        c.appendChild(col);
    });
}
function applyDashboardFilters(){
    const head=document.getElementById('table-head'), body=document.getElementById('table-body');
    head.innerHTML='<th>ID</th>'+appState.schema.filter(f=>f.dashboardList).map(f=>`<th>${f.label}</th>`).join('')+'<th class="text-end">Aktion</th>';
    const rows=appState.entries.filter(e=>{for(const[k,v]of Object.entries(appState.activeFilters)){if(!v)continue;let d=e.data[k];if(!d)return false;if(Array.isArray(d)){if(!d.includes(v))return false;}else{if(String(d)!==String(v))return false;}}return true;});
    body.innerHTML=rows.length?rows.map(e=>`<tr><td>${e.id}</td>`+appState.schema.filter(f=>f.dashboardList).map(f=>{let v=e.data[f.id];if(f.type==='money'&&v)v=parseFloat(v).toFixed(2)+' €';if(f.type==='file'&&v)v='<i class="bi bi-file-pdf text-danger"></i> PDF';if(Array.isArray(v))v=v.join(', ');return `<td>${v||''}</td>`;}).join('')+`<td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="editEntry(${e.id})"><i class="bi bi-pencil"></i></button> <button class="btn btn-sm btn-outline-danger" onclick="deleteEntry(${e.id})"><i class="bi bi-trash"></i></button></td></tr>`).join(''):'<tr><td colspan="100" class="text-center py-4 text-muted">Keine Ergebnisse</td></tr>';
    document.getElementById('dashboard-kpis').innerHTML=appState.schema.filter(f=>f.dashboardSum).map(f=>`<div class="col-md-3"><div class="card shadow-sm kpi-card p-3"><h6 class="text-primary small fw-bold">SUMME ${f.label}</h6><h3 class="mb-0 text-dark">${rows.reduce((a,c)=>a+(parseFloat(c.data[f.id])||0),0).toFixed(2)} €</h3></div></div>`).join('');
}
async function saveCurrentFilter(){const n=prompt("Name:");if(!n)return;await fetch('?action=save_filter',{method:'POST',body:JSON.stringify({name:n,config:appState.activeFilters})});fetchData();}
async function deleteCurrentFilter(){if(!document.getElementById('saved-filters-select').value)return;await fetch('?action=delete_filter',{method:'POST',body:JSON.stringify({id:document.getElementById('saved-filters-select').value})});fetchData();}
function updateSavedFiltersDropdown(){document.getElementById('saved-filters-select').innerHTML='<option value="">Ansicht laden...</option>'+appState.savedFilters.map(f=>`<option value="${f.id}">${f.name}</option>`).join('');}
function loadFilterPreset(id){const f=appState.savedFilters.find(x=>x.id==id);if(!f)return;appState.activeFilters=JSON.parse(f.filter_config);renderDashboardFilters();applyDashboardFilters();}
function resetFilters(){appState.activeFilters={};renderDashboardFilters();applyDashboardFilters();document.getElementById('saved-filters-select').value='';}
</script>
</body>
</html>