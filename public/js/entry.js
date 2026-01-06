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
