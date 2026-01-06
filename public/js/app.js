let appState = {
    schema: [],
    entries: [],
    savedFilters: [],
    layout: { colLeft: 6, colRight: 6 }, // Standard 50%
    editingEntryId: null,
    selectedFieldId: null,
    activeFilters: {}
};

document.addEventListener('DOMContentLoaded', () => {
    fetchData();
});
