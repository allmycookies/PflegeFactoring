// --- DASHBOARD FILTERS ---
function renderDashboardFilters(){
    const c=document.getElementById('dashboard-filters');
    if(!c) return;
    c.innerHTML='';
    appState.schema.filter(f=>f.dashboardFilter).forEach(f=>{
        const col=document.createElement('div');col.className='col-md-3 col-sm-6';
        let vals=new Set();appState.entries.forEach(e=>{let v=e.data[f.id];if(v)Array.isArray(v)?v.forEach(x=>vals.add(x)):vals.add(v);});
        col.innerHTML=`<select class="form-select form-select-sm ${appState.activeFilters[f.id]?'bg-primary text-white':''}" onchange="appState.activeFilters['${f.id}']=this.value;applyDashboardFilters();renderDashboardFilters()"><option value="">${f.label}</option>${Array.from(vals).sort().map(v=>`<option value="${v}" ${appState.activeFilters[f.id]==v?'selected':''}>${v}</option>`).join('')}</select>`;
        c.appendChild(col);
    });
}

function applyDashboardFilters(){
    const head=document.getElementById('table-head'), body=document.getElementById('table-body');
    if(!head || !body) return;
    head.innerHTML='<th>ID</th>'+appState.schema.filter(f=>f.dashboardList).map(f=>`<th>${f.label}</th>`).join('')+'<th class="text-end">Aktion</th>';
    const rows=appState.entries.filter(e=>{for(const[k,v]of Object.entries(appState.activeFilters)){if(!v)continue;let d=e.data[k];if(!d)return false;if(Array.isArray(d)){if(!d.includes(v))return false;}else{if(String(d)!==String(v))return false;}}return true;});
    body.innerHTML=rows.length?rows.map(e=>`<tr><td>${e.id}</td>`+appState.schema.filter(f=>f.dashboardList).map(f=>{let v=e.data[f.id];if(f.type==='money'&&v)v=parseFloat(v).toFixed(2)+' €';if(f.type==='file'&&v)v='<i class="bi bi-file-pdf text-danger"></i> PDF';if(Array.isArray(v))v=v.join(', ');return `<td>${v||''}</td>`;}).join('')+`<td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="editEntry(${e.id})"><i class="bi bi-pencil"></i></button> <button class="btn btn-sm btn-outline-danger" onclick="deleteEntry(${e.id})"><i class="bi bi-trash"></i></button></td></tr>`).join(''):'<tr><td colspan="100" class="text-center py-4 text-muted">Keine Ergebnisse</td></tr>';

    const kpis = document.getElementById('dashboard-kpis');
    if(kpis) {
        kpis.innerHTML=appState.schema.filter(f=>f.dashboardSum).map(f=>`<div class="col-md-3"><div class="card shadow-sm kpi-card p-3"><h6 class="text-primary small fw-bold">SUMME ${f.label}</h6><h3 class="mb-0 text-dark">${rows.reduce((a,c)=>a+(parseFloat(c.data[f.id])||0),0).toFixed(2)} €</h3></div></div>`).join('');
    }
}

async function saveCurrentFilter(){
    const n=prompt("Name:");
    if(!n)return;
    await saveFilterApi(n, appState.activeFilters);
    fetchData();
}

async function deleteCurrentFilter(){
    const el = document.getElementById('saved-filters-select');
    if(!el || !el.value)return;
    await deleteFilterApi(el.value);
    fetchData();
}

function updateSavedFiltersDropdown(){
    const el = document.getElementById('saved-filters-select');
    if(!el) return;
    el.innerHTML='<option value="">Ansicht laden...</option>'+appState.savedFilters.map(f=>`<option value="${f.id}">${f.name}</option>`).join('');
}

function loadFilterPreset(id){
    const f=appState.savedFilters.find(x=>x.id==id);
    if(!f)return;
    appState.activeFilters=JSON.parse(f.filter_config);
    renderDashboardFilters();
    applyDashboardFilters();
}

function resetFilters(){
    appState.activeFilters={};
    renderDashboardFilters();
    applyDashboardFilters();
    const el = document.getElementById('saved-filters-select');
    if(el) el.value='';
}
