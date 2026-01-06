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
    const c=document.getElementById('editor-canvas');
    if(!c) return;
    c.innerHTML='';
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
    if(!c) return;

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
