<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PflegeFactoring Manager Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
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

<script src="js/app.js"></script>
<script src="js/api.js"></script>
<script src="js/router.js"></script>
<script src="js/dashboard.js"></script>
<script src="js/entry.js"></script>
<script src="js/editor.js"></script>

</body>
</html>
