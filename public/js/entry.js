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

function updatePdfViewer(files) {
    const container = document.getElementById('pdf-viewer-placeholder');
    if (files) {
        const fileList = Array.isArray(files) ? files : [files];

        if (fileList.length === 0) {
            container.innerHTML = `<div class="pdf-placeholder"><i class="bi bi-file-earmark-x fs-1 opacity-50"></i><div class="mt-2 opacity-75">Kein Dokument</div></div>`;
            return;
        }

        if (fileList.length === 1) {
            container.innerHTML = `<iframe src="uploads/${fileList[0]}" class="pdf-frame"></iframe>`;
        } else {
            // Multiple files: Show selector + current file
            let options = fileList.map((f, i) => `<option value="${f}">${f}</option>`).join('');

            // Container for selector
            let selectorHtml = `
                <div class="p-2 bg-light border-bottom d-flex align-items-center">
                    <span class="me-2 small fw-bold">Dokument:</span>
                    <select class="form-select form-select-sm" onchange="document.getElementById('pdf-frame-multi').src='uploads/'+this.value">
                        ${options}
                    </select>
                </div>
                <iframe id="pdf-frame-multi" src="uploads/${fileList[0]}" class="pdf-frame"></iframe>
            `;
            container.innerHTML = selectorHtml;
        }

    } else {
        container.innerHTML = `<div class="pdf-placeholder"><i class="bi bi-file-earmark-x fs-1 opacity-50"></i><div class="mt-2 opacity-75">Kein Dokument</div></div>`;
    }
}

async function removeFileFromEntry(entryId, fieldId, filename) {
    if(!confirm('Datei wirklich löschen?')) return;

    // Find entry
    const entry = appState.entries.find(e => e.id == entryId);
    if (!entry) return;

    // Get current files
    let currentFiles = entry.data[fieldId];
    if (!currentFiles) return;
    if (!Array.isArray(currentFiles)) currentFiles = [currentFiles];

    // Filter out the file
    const newFiles = currentFiles.filter(f => f !== filename);

    // Prepare data update
    // We reuse saveEntry but we need to send the FULL data object with the modified file list
    // because saveEntry merges. If we send just the key, it overwrites/merges correctly?
    // Backend: foreach ($existingData as $key => $val) { if (!isset($jsonData[$key])) $jsonData[$key] = $val; }
    // This means if we send the key in jsonData, it uses our new value. Perfect.

    const formData = new FormData();
    formData.append('id', entryId);

    // We only need to send the updated field
    const updateData = {};
    updateData[fieldId] = newFiles;

    formData.append('data', JSON.stringify(updateData));

    // Call API
    await saveEntry(formData);

    // Refresh
    await fetchData();
    if(appState.editingEntryId) {
        loadHistory(appState.editingEntryId);
        renderEntryForm(); // Re-render form to update list

        // Refresh PDF view
        const newEntry = appState.entries.find(e => e.id == appState.editingEntryId);
        if(newEntry && newEntry.data[fieldId]) updatePdfViewer(newEntry.data[fieldId]);
        else updatePdfViewer(null);
    }
}

async function loadHistory(id) {
    const con = document.getElementById('history-container');
    con.innerHTML = '<div class="spinner-border text-primary spinner-border-sm mt-2"></div>';

    const logs = await getHistory(id);

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
            input.type = 'file'; input.className = 'form-control'; input.name = field.id; input.accept = 'application/pdf'; input.multiple = true;

            if(val) {
                // val can be string or array
                const files = Array.isArray(val) ? val : [val];
                const list = document.createElement('ul'); list.className = "list-group mt-2";
                files.forEach(f => {
                    const li = document.createElement('li'); li.className = "list-group-item d-flex justify-content-between align-items-center p-1 px-2 small";
                    li.innerHTML = `<span><i class="bi bi-file-earmark-pdf text-danger me-2"></i>${f}</span>`;
                    const delBtn = document.createElement('button'); delBtn.className = "btn btn-link text-danger p-0 ms-2"; delBtn.innerHTML = '<i class="bi bi-trash"></i>';
                    delBtn.onclick = (e) => { e.preventDefault(); removeFileFromEntry(appState.editingEntryId, field.id, f); };
                    li.appendChild(delBtn);
                    list.appendChild(li);
                });
                wrapper.appendChild(list);
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
            if (fileInput && fileInput.files.length > 0) {
                // Handle multiple files
                for (let i = 0; i < fileInput.files.length; i++) {
                    formData.append(field.id + '[]', fileInput.files[i]);
                }
            }
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

    const json = await saveEntry(formData);

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

async function deleteEntry(id) {
    if(!confirm('Löschen?')) return;
    await deleteEntryApi(id);
    fetchData();
}
