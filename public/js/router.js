function router(view) {
    document.querySelectorAll('main').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.list-group-item').forEach(el => el.classList.remove('active'));

    const viewEl = document.getElementById('view-' + view);
    if(viewEl) viewEl.classList.remove('hidden');

    const navEl = document.getElementById('nav-' + view);
    if(navEl) navEl.classList.add('active');

    // Layout anwenden wenn Entry View geöffnet wird
    if(view === 'entry') {
        const left = document.getElementById('split-left');
        const right = document.getElementById('split-right');
        if(left && right) {
            left.className = `detail-pane col-md-${appState.layout.colLeft}`;
            right.className = `pdf-pane col-md-${appState.layout.colRight}`;
        }
    }

    if (view === 'dashboard' && typeof renderDashboardFilters === 'function') {
        renderDashboardFilters();
        applyDashboardFilters();
    }
}
