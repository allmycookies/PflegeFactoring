async function fetchData() {
    const res = await fetch('?action=get_data');
    const data = await res.json();
    appState.schema = data.schema || [];
    appState.entries = data.entries.map(e => ({...e, data: JSON.parse(e.data)}));
    appState.savedFilters = data.filters;
    if(data.layout) appState.layout = data.layout;

    // Only call render functions if they are available (module might not be loaded if we were doing strict modules)
    // But since we are concatenating via script tags, they should be available.
    if(typeof renderEditorCanvas === 'function') renderEditorCanvas();
    if(typeof renderProperties === 'function') renderProperties(null);
    if(typeof renderDashboardFilters === 'function') renderDashboardFilters();
    if(typeof applyDashboardFilters === 'function') applyDashboardFilters();
    if(typeof updateSavedFiltersDropdown === 'function') updateSavedFiltersDropdown();
}

async function saveSchema() {
    await fetch('?action=save_schema',{
        method:'POST',
        body:JSON.stringify({schema:appState.schema, layout: appState.layout})
    });
    if(typeof renderDashboardFilters === 'function') renderDashboardFilters();
    if(typeof applyDashboardFilters === 'function') applyDashboardFilters();
}

async function saveEntry(formData) {
    const res = await fetch('?action=save_entry', { method: 'POST', body: formData });
    return await res.json();
}

async function deleteEntryApi(id) {
    await fetch('?action=delete_entry', { method: 'POST', body: JSON.stringify({ id }) });
}

async function getHistory(id) {
    const res = await fetch(`?action=get_history&id=${id}`);
    return await res.json();
}

async function saveFilterApi(name, config) {
    await fetch('?action=save_filter', { method: 'POST', body: JSON.stringify({ name, config }) });
}

async function deleteFilterApi(id) {
    await fetch('?action=delete_filter', { method: 'POST', body: JSON.stringify({ id }) });
}
