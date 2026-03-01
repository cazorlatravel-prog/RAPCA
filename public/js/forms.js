/**
 * RAPCA - Data Collection Forms (VP, EL, EI)
 * Handles form generation, autocomplete, calculations, save/load
 */
(function() {
    'use strict';

    const PLANTAS = [
        'Arbutus unedo','Asparagus acutifolius','Chamaerops humilis','Cistus sp.',
        'Crataegus monogyna','Cytisus sp.','Daphne gnidium','Dittrichia viscosa',
        'Foeniculum vulgare','Genista sp.','Halimium sp.','Helichrysum stoechas',
        'Juncus spp.','Juniperus sp.','Lavandula latifolia','Myrtus communis',
        'Olea europaea var. sylvestris','Phillyrea angustifolia','Phlomis purpurea',
        'Pistacia lentiscus','Quercus coccifera','Quercus ilex','Quercus sp.',
        'Retama sphaerocarpa','Rhamnus sp.','Rosa sp.','Rosmarinus officinalis',
        'Rubus ulmifolius','Salvia rosmarinus','Spartium junceum','Thymus sp.','Ulex sp.'
    ];

    let currentAC = null; // { input, list }
    let eiTransecto = 1;
    let formFotos = { vp: {general:[], w1:[], w2:[]}, el: {general:[], w1:[], w2:[]}, ei: {general:[], w1:[], w2:[]} };

    // ───────────────────────────────────────────
    // NOTA OPTIONS (0-5) for selects
    // ───────────────────────────────────────────
    function notaOptions() {
        return '<option value="">-</option><option>0</option><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option>';
    }

    // ───────────────────────────────────────────
    // GENERATE DYNAMIC FORM SECTIONS
    // ───────────────────────────────────────────
    function generatePlantas() {
        const container = document.getElementById('ei-plantas-section');
        if (!container) return;
        let html = '';
        for (let i = 1; i <= 10; i++) {
            html += `<div class="form-planta-box">
                <div class="form-planta-header">
                    <span class="form-planta-num">${i}</span>
                    <div class="form-autocomplete-wrap" style="flex:1">
                        <input type="text" id="formei-planta${i}" placeholder="Planta..." autocomplete="off" class="form-input" onfocus="RapcaForms.showAC(this)" oninput="RapcaForms.filterAC(this)">
                        <div class="form-ac-list" id="ac-formei-planta${i}"></div>
                    </div>
                </div>
                <div class="form-notas-grid form-notas-grid-10">`;
            for (let n = 1; n <= 10; n++) {
                html += `<div class="form-nota-item"><label>${n}</label><select id="formei-planta${i}-n${n}" onchange="RapcaForms.updatePlantStats()">${notaOptions()}</select></div>`;
            }
            html += '</div></div>';
        }
        container.innerHTML = html;
    }

    function generatePalatables() {
        const container = document.getElementById('ei-palatables-section');
        if (!container) return;
        let html = '';
        for (let i = 1; i <= 3; i++) {
            html += `<div class="form-palatable-box">
                <div class="form-autocomplete-wrap">
                    <label class="form-label">Planta ${i}</label>
                    <input type="text" id="formei-palatable${i}" placeholder="Planta..." autocomplete="off" class="form-input" onfocus="RapcaForms.showAC(this)" oninput="RapcaForms.filterAC(this)">
                    <div class="form-ac-list" id="ac-formei-palatable${i}"></div>
                </div>
                <div class="form-notas-grid form-notas-grid-15" style="margin-top:10px">`;
            for (let n = 1; n <= 15; n++) {
                html += `<div class="form-nota-item"><label>${n}</label><select id="formei-palatable${i}-n${n}" onchange="RapcaForms.updatePalatableStats()">${notaOptions()}</select></div>`;
            }
            html += `</div><div class="form-palatable-media" id="formei-media-pal${i}"></div></div>`;
        }
        container.innerHTML = html;
    }

    function generateHerbaceas(prefix) {
        const container = document.getElementById(prefix + '-herb-grid');
        if (!container) return;
        let html = '';
        for (let i = 1; i <= 7; i++) {
            html += `<div class="form-nota-item"><label>H${i}</label><select id="form${prefix}-herb${i}" onchange="RapcaForms.updateHerbStats('${prefix}')">${notaOptions()}</select></div>`;
        }
        container.innerHTML = html;
    }

    // ───────────────────────────────────────────
    // STATISTICS
    // ───────────────────────────────────────────
    function updatePlantStats() {
        let count = 0, sum = 0;
        for (let i = 1; i <= 10; i++) {
            for (let n = 1; n <= 10; n++) {
                const el = document.getElementById(`formei-planta${i}-n${n}`);
                if (el && el.value !== '') { count++; sum += parseInt(el.value); }
            }
        }
        const cntEl = document.getElementById('formei-cnt-plantas');
        const mediaEl = document.getElementById('formei-media-plantas');
        if (cntEl) cntEl.textContent = count;
        if (mediaEl) mediaEl.textContent = count > 0 ? 'x\u0304 ' + (sum / count).toFixed(1) : 'x\u0304 -';
    }

    function updatePalatableStats() {
        let totalCount = 0, totalSum = 0;
        for (let i = 1; i <= 3; i++) {
            let c = 0, s = 0;
            for (let n = 1; n <= 15; n++) {
                const el = document.getElementById(`formei-palatable${i}-n${n}`);
                if (el && el.value !== '') { c++; s += parseInt(el.value); totalCount++; totalSum += parseInt(el.value); }
            }
            const mediaEl = document.getElementById(`formei-media-pal${i}`);
            if (mediaEl) mediaEl.textContent = c > 0 ? 'Media: ' + (s / c).toFixed(1) : '';
        }
        const globalEl = document.getElementById('formei-media-palatables');
        if (globalEl) globalEl.textContent = totalCount > 0 ? 'x\u0304 ' + (totalSum / totalCount).toFixed(1) : 'x\u0304 -';
    }

    function updateHerbStats(prefix) {
        let c = 0, s = 0;
        for (let i = 1; i <= 7; i++) {
            const el = document.getElementById(`form${prefix}-herb${i}`);
            if (el && el.value !== '') { c++; s += parseInt(el.value); }
        }
        const mediaEl = document.getElementById(`form${prefix}-media-herb`);
        if (mediaEl) mediaEl.textContent = c > 0 ? 'x\u0304 ' + (s / c).toFixed(1) : 'x\u0304 -';
    }

    function updateMatorral(prefix) {
        const c1 = parseFloat(document.getElementById(`form${prefix}-mat1cob`)?.value) || 0;
        const c2 = parseFloat(document.getElementById(`form${prefix}-mat2cob`)?.value) || 0;
        const a1 = parseFloat(document.getElementById(`form${prefix}-mat1alt`)?.value) || 0;
        const a2 = parseFloat(document.getElementById(`form${prefix}-mat2alt`)?.value) || 0;
        const e1 = document.getElementById(`form${prefix}-mat1esp`)?.value?.trim() || '';
        const e2 = document.getElementById(`form${prefix}-mat2esp`)?.value?.trim() || '';

        const hC1 = document.getElementById(`form${prefix}-mat1cob`)?.value !== '';
        const hC2 = document.getElementById(`form${prefix}-mat2cob`)?.value !== '';
        const hA1 = document.getElementById(`form${prefix}-mat1alt`)?.value !== '';
        const hA2 = document.getElementById(`form${prefix}-mat2alt`)?.value !== '';

        let mC = '-', mA = '-', vol = '-';
        const nC = (hC1 ? 1 : 0) + (hC2 ? 1 : 0);
        const nA = (hA1 ? 1 : 0) + (hA2 ? 1 : 0);
        if (nC > 0) mC = ((c1 + c2) / nC).toFixed(1);
        if (nA > 0) mA = ((a1 + a2) / nA).toFixed(1);
        if (mC !== '-' && mA !== '-') {
            vol = ((parseFloat(mC) / 100) * (parseFloat(mA) / 100) * 10000).toFixed(1);
        }

        const cobEl = document.getElementById(`form${prefix}-mediaCob`);
        const altEl = document.getElementById(`form${prefix}-mediaAlt`);
        const volEl = document.getElementById(`form${prefix}-volumen`);
        const espEl = document.getElementById(`form${prefix}-especies`);
        if (cobEl) cobEl.textContent = mC;
        if (altEl) altEl.textContent = mA;
        if (volEl) volEl.textContent = vol;
        const especies = [];
        if (e1) especies.push(e1);
        if (e2 && e2 !== e1) especies.push(e2);
        if (espEl) espEl.textContent = 'Especies: ' + (especies.length > 0 ? especies.join(', ') : '-');
    }

    // ───────────────────────────────────────────
    // AUTOCOMPLETE
    // ───────────────────────────────────────────
    function showAC(input) {
        const listId = 'ac-' + input.id;
        const list = document.getElementById(listId);
        if (!list) return;
        currentAC = { input, list };
        renderACList(input.value);
        list.classList.add('show');
    }

    function filterAC(input) {
        if (currentAC && currentAC.input === input) {
            renderACList(input.value);
        }
    }

    function renderACList(filter) {
        if (!currentAC) return;
        const fl = filter.toLowerCase();
        let html = '';
        const matches = PLANTAS.filter(p => p.toLowerCase().includes(fl));
        matches.forEach(p => {
            html += `<div class="form-ac-item" onclick="RapcaForms.selectAC('${p.replace(/'/g, "\\'")}')">${p}</div>`;
        });
        currentAC.list.innerHTML = html || '<div class="form-ac-item" style="color:#999">Sin resultados</div>';
    }

    function selectAC(value) {
        if (currentAC) {
            currentAC.input.value = value;
            currentAC.list.classList.remove('show');
            // Trigger matorral recalc if relevant
            if (currentAC.input.id.includes('mat')) {
                const prefix = currentAC.input.id.includes('el') ? 'el' : 'ei';
                updateMatorral(prefix);
            }
            currentAC = null;
        }
    }

    // Close autocomplete on outside click
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.form-autocomplete-wrap')) {
            document.querySelectorAll('.form-ac-list').forEach(l => l.classList.remove('show'));
        }
    });

    // ───────────────────────────────────────────
    // TRANSECTO
    // ───────────────────────────────────────────
    function setTransecto(n) {
        eiTransecto = n;
        document.querySelectorAll('.form-transecto-btn').forEach(btn => {
            btn.classList.toggle('active', parseInt(btn.dataset.t) === n);
        });
        const label = document.getElementById('ei-transecto-label');
        if (label) label.textContent = n;
    }

    // ───────────────────────────────────────────
    // FORM PHOTO BUTTONS
    // ───────────────────────────────────────────
    function initPhotoButtons() {
        document.querySelectorAll('[data-form-photo]').forEach(btn => {
            btn.addEventListener('click', () => {
                const key = btn.dataset.formPhoto; // e.g. "vp-general", "ei-w1"
                const [formType, subtype] = key.split('-');
                openFormCamera(formType, subtype);
            });
        });
    }

    function openFormCamera(formType, subtype) {
        // Use the existing camera system, but store the photo reference in form data
        const infraName = window._rapcaState?.infraName || 'INFRA';
        const infraId = window._rapcaState?.infraId;
        if (!infraId) {
            if (window.showToast) window.showToast('Selecciona una infraestructura primero', 'warning');
            return;
        }

        // Generate filename
        const prefix = formType.toUpperCase();
        const arr = formFotos[formType]?.[subtype] || [];
        const num = arr.length + 1;
        const suffix = subtype === 'general' ? num : subtype.toUpperCase() + '_' + num;
        const filename = `${infraName}_${prefix}_${suffix}`;

        // Store pending form photo context
        window._pendingFormPhoto = { formType, subtype, filename };

        // Open the regular camera (aleatorio mode, captures GPS etc.)
        if (typeof window._openCameraForForm === 'function') {
            window._openCameraForForm(formType, subtype);
        }
    }

    function addFormPhoto(formType, subtype, photoCode) {
        if (!formFotos[formType]) formFotos[formType] = { general: [], w1: [], w2: [] };
        if (!formFotos[formType][subtype]) formFotos[formType][subtype] = [];
        formFotos[formType][subtype].push(photoCode);

        // Update the UI list
        const listId = `form${formType}-${subtype === 'general' ? 'fotos' : subtype}-list`;
        const listEl = document.getElementById(listId);
        if (listEl) {
            const tag = document.createElement('span');
            tag.className = 'form-foto-tag';
            tag.textContent = photoCode;
            listEl.appendChild(tag);
        }
    }

    // ───────────────────────────────────────────
    // COLLECT FORM DATA
    // ───────────────────────────────────────────
    function getVPData() {
        return {
            tipo_formulario: 'vp',
            fecha: document.getElementById('formvp-fecha')?.value || '',
            unidad: document.getElementById('formvp-unidad')?.value?.trim() || '',
            pastoreo: [
                document.getElementById('formvp-past1')?.value || '',
                document.getElementById('formvp-past2')?.value || '',
                document.getElementById('formvp-past3')?.value || '',
            ],
            observacion_pastoreo: {
                senal: document.getElementById('formvp-senal')?.value || '',
                veredas: document.getElementById('formvp-veredas')?.value || '',
                cagarrutas: document.getElementById('formvp-cagarrutas')?.value || '',
            },
            fotos: formFotos.vp.general,
            fotos_comp: { w1: formFotos.vp.w1, w2: formFotos.vp.w2 },
            observaciones: document.getElementById('formvp-obs')?.value || '',
        };
    }

    function getELData() {
        const herbaceas = [];
        for (let i = 1; i <= 7; i++) {
            herbaceas.push(document.getElementById(`formel-herb${i}`)?.value || '');
        }

        return {
            tipo_formulario: 'el',
            fecha: document.getElementById('formel-fecha')?.value || '',
            unidad: document.getElementById('formel-unidad')?.value?.trim() || '',
            pastoreo: [
                document.getElementById('formel-past1')?.value || '',
                document.getElementById('formel-past2')?.value || '',
                document.getElementById('formel-past3')?.value || '',
            ],
            herbaceas,
            matorral: getMatorralData('el'),
            fotos: formFotos.el.general,
            fotos_comp: { w1: formFotos.el.w1, w2: formFotos.el.w2 },
            observaciones: document.getElementById('formel-obs')?.value || '',
        };
    }

    function getEIData() {
        const plantas = [];
        for (let i = 1; i <= 10; i++) {
            const notas = [];
            for (let n = 1; n <= 10; n++) {
                notas.push(document.getElementById(`formei-planta${i}-n${n}`)?.value || '');
            }
            let c = 0, s = 0;
            notas.forEach(v => { if (v !== '') { c++; s += parseInt(v); } });
            plantas.push({
                nombre: document.getElementById(`formei-planta${i}`)?.value || '',
                notas,
                media: c > 0 ? (s / c).toFixed(2) : '',
            });
        }

        const palatables = [];
        for (let i = 1; i <= 3; i++) {
            const notas = [];
            for (let n = 1; n <= 15; n++) {
                notas.push(document.getElementById(`formei-palatable${i}-n${n}`)?.value || '');
            }
            let c = 0, s = 0;
            notas.forEach(v => { if (v !== '') { c++; s += parseInt(v); } });
            palatables.push({
                nombre: document.getElementById(`formei-palatable${i}`)?.value || '',
                notas,
                media: c > 0 ? (s / c).toFixed(2) : '',
            });
        }

        const herbaceas = [];
        for (let i = 1; i <= 7; i++) {
            herbaceas.push(document.getElementById(`formei-herb${i}`)?.value || '');
        }

        // Media calculations
        let pC = 0, pS = 0;
        plantas.forEach(p => p.notas.forEach(v => { if (v !== '') { pC++; pS += parseInt(v); } }));
        let paC = 0, paS = 0;
        palatables.forEach(p => p.notas.forEach(v => { if (v !== '') { paC++; paS += parseInt(v); } }));
        let hC = 0, hS = 0;
        herbaceas.forEach(v => { if (v !== '') { hC++; hS += parseInt(v); } });

        return {
            tipo_formulario: 'ei',
            fecha: document.getElementById('formei-fecha')?.value || '',
            unidad: document.getElementById('formei-unidad')?.value?.trim() || '',
            transecto: 'T' + eiTransecto,
            plantas,
            plantas_media: pC > 0 ? (pS / pC).toFixed(2) : '',
            palatables,
            palatables_media: paC > 0 ? (paS / paC).toFixed(2) : '',
            pastoreo: [
                document.getElementById('formei-past1')?.value || '',
                document.getElementById('formei-past2')?.value || '',
                document.getElementById('formei-past3')?.value || '',
            ],
            herbaceas,
            herbaceas_media: hC > 0 ? (hS / hC).toFixed(2) : '',
            matorral: getMatorralData('ei'),
            fotos: formFotos.ei.general,
            fotos_comp: { w1: formFotos.ei.w1, w2: formFotos.ei.w2 },
            observaciones: document.getElementById('formei-obs')?.value || '',
        };
    }

    function getMatorralData(prefix) {
        const c1 = document.getElementById(`form${prefix}-mat1cob`)?.value || '';
        const a1 = document.getElementById(`form${prefix}-mat1alt`)?.value || '';
        const e1 = document.getElementById(`form${prefix}-mat1esp`)?.value || '';
        const c2 = document.getElementById(`form${prefix}-mat2cob`)?.value || '';
        const a2 = document.getElementById(`form${prefix}-mat2alt`)?.value || '';
        const e2 = document.getElementById(`form${prefix}-mat2esp`)?.value || '';

        const nC = (c1 !== '' ? 1 : 0) + (c2 !== '' ? 1 : 0);
        const nA = (a1 !== '' ? 1 : 0) + (a2 !== '' ? 1 : 0);
        const mediaCob = nC > 0 ? (((parseFloat(c1) || 0) + (parseFloat(c2) || 0)) / nC).toFixed(1) : '';
        const mediaAlt = nA > 0 ? (((parseFloat(a1) || 0) + (parseFloat(a2) || 0)) / nA).toFixed(1) : '';
        const volumen = mediaCob && mediaAlt ? ((parseFloat(mediaCob) / 100) * (parseFloat(mediaAlt) / 100) * 10000).toFixed(1) : '';

        return {
            punto1: { cobertura: c1, altura: a1, especie: e1 },
            punto2: { cobertura: c2, altura: a2, especie: e2 },
            mediaCob, mediaAlt, volumen,
        };
    }

    // ───────────────────────────────────────────
    // SAVE FORM (posts to API)
    // ───────────────────────────────────────────
    async function saveForm(tipo) {
        let data;
        if (tipo === 'vp') data = getVPData();
        else if (tipo === 'el') data = getELData();
        else if (tipo === 'ei') data = getEIData();
        else return;

        const infraId = window._rapcaState?.infraId;
        const usuarioId = window.RAPCA?.usuarioId;
        const lat = window._rapcaState?.gps?.lat;
        const lon = window._rapcaState?.gps?.lon;

        if (!infraId) {
            if (window.showToast) window.showToast('Selecciona una infraestructura', 'error');
            return;
        }

        // Build form submission
        const formData = new FormData();
        formData.append('infra_id', infraId);
        formData.append('usuario_id', usuarioId);
        formData.append('lat_real', lat || 0);
        formData.append('lon_real', lon || 0);
        formData.append('estado_incidencia', tipo === 'vp' ? 'vp' : 'ev');
        formData.append('tipo_foto', 'aleatorio');
        formData.append('observaciones', data.observaciones || '');
        formData.append('nombre_archivo', `FORM_${tipo.toUpperCase()}_${data.unidad || 'NA'}_${Date.now()}`);
        formData.append('datos_tecnicos', JSON.stringify(data));

        try {
            // Save to server via existing registros API or subir.php
            const endpoint = window.RAPCA?.endpoints?.registrosMapa || 'api/registros_mapa.php';
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'guardar_formulario',
                    infra_id: infraId,
                    usuario_id: usuarioId,
                    lat_real: lat || 0,
                    lon_real: lon || 0,
                    estado_incidencia: tipo === 'vp' ? 'vp' : 'ev',
                    datos_tecnicos: data,
                    observaciones: data.observaciones || '',
                    nombre_archivo: `FORM_${tipo.toUpperCase()}_${data.unidad || 'NA'}`,
                }),
            });

            const result = await res.json();
            if (result.ok) {
                if (window.showToast) window.showToast(`${tipo.toUpperCase()} guardado correctamente`, 'success');
            } else {
                throw new Error(result.error || 'Error al guardar');
            }
        } catch (err) {
            // Save locally as fallback
            saveFormLocal(tipo, data);
            if (window.showToast) window.showToast(`${tipo.toUpperCase()} guardado localmente (pendiente de sincronizar)`, 'info');
        }

        // Handle EI transecto progression
        if (tipo === 'ei') {
            if (eiTransecto >= 3) {
                clearForm('ei', true);
                if (window.showToast) window.showToast('Unidad completada (T1-T3)', 'info');
            } else {
                clearForm('ei', false);
                setTransecto(eiTransecto + 1);
            }
        } else {
            clearForm(tipo, true);
        }

        // Return to ficha
        if (window._showScreen) window._showScreen('ficha');
    }

    function saveFormLocal(tipo, data) {
        const key = 'rapca_forms_pending';
        const pending = JSON.parse(localStorage.getItem(key) || '[]');
        pending.push({
            id: Date.now(),
            tipo,
            data,
            infraId: window._rapcaState?.infraId,
            usuarioId: window.RAPCA?.usuarioId,
            timestamp: new Date().toISOString(),
        });
        localStorage.setItem(key, JSON.stringify(pending));
    }

    // ───────────────────────────────────────────
    // CLEAR FORM
    // ───────────────────────────────────────────
    function clearForm(tipo, full) {
        if (tipo === 'vp') {
            if (full) {
                document.getElementById('formvp-fecha').value = new Date().toISOString().split('T')[0];
                document.getElementById('formvp-unidad').value = '';
            }
            ['formvp-past1','formvp-past2','formvp-past3','formvp-senal','formvp-veredas','formvp-cagarrutas','formvp-obs'].forEach(id => {
                const el = document.getElementById(id); if (el) el.value = '';
            });
            ['formvp-fotos-list','formvp-w1-list','formvp-w2-list'].forEach(id => {
                const el = document.getElementById(id); if (el) el.innerHTML = '';
            });
            formFotos.vp = { general: [], w1: [], w2: [] };
        } else if (tipo === 'el') {
            if (full) {
                document.getElementById('formel-fecha').value = new Date().toISOString().split('T')[0];
                document.getElementById('formel-unidad').value = '';
            }
            ['formel-past1','formel-past2','formel-past3','formel-obs'].forEach(id => {
                const el = document.getElementById(id); if (el) el.value = '';
            });
            for (let i = 1; i <= 7; i++) { const el = document.getElementById(`formel-herb${i}`); if (el) el.value = ''; }
            ['formel-mat1cob','formel-mat1alt','formel-mat1esp','formel-mat2cob','formel-mat2alt','formel-mat2esp'].forEach(id => {
                const el = document.getElementById(id); if (el) el.value = '';
            });
            ['formel-fotos-list','formel-w1-list','formel-w2-list'].forEach(id => {
                const el = document.getElementById(id); if (el) el.innerHTML = '';
            });
            formFotos.el = { general: [], w1: [], w2: [] };
            updateHerbStats('el');
            updateMatorral('el');
        } else if (tipo === 'ei') {
            if (full) {
                document.getElementById('formei-fecha').value = new Date().toISOString().split('T')[0];
                document.getElementById('formei-unidad').value = '';
                setTransecto(1);
            }
            // Clear plantas
            for (let i = 1; i <= 10; i++) {
                const el = document.getElementById(`formei-planta${i}`); if (el) el.value = '';
                for (let n = 1; n <= 10; n++) {
                    const nel = document.getElementById(`formei-planta${i}-n${n}`); if (nel) nel.value = '';
                }
            }
            // Clear palatables
            for (let i = 1; i <= 3; i++) {
                const el = document.getElementById(`formei-palatable${i}`); if (el) el.value = '';
                for (let n = 1; n <= 15; n++) {
                    const nel = document.getElementById(`formei-palatable${i}-n${n}`); if (nel) nel.value = '';
                }
                const mediaEl = document.getElementById(`formei-media-pal${i}`); if (mediaEl) mediaEl.textContent = '';
            }
            ['formei-past1','formei-past2','formei-past3','formei-obs'].forEach(id => {
                const el = document.getElementById(id); if (el) el.value = '';
            });
            for (let i = 1; i <= 7; i++) { const el = document.getElementById(`formei-herb${i}`); if (el) el.value = ''; }
            ['formei-mat1cob','formei-mat1alt','formei-mat1esp','formei-mat2cob','formei-mat2alt','formei-mat2esp'].forEach(id => {
                const el = document.getElementById(id); if (el) el.value = '';
            });
            ['formei-fotos-list','formei-w1-list','formei-w2-list'].forEach(id => {
                const el = document.getElementById(id); if (el) el.innerHTML = '';
            });
            formFotos.ei = { general: [], w1: [], w2: [] };
            updatePlantStats();
            updatePalatableStats();
            updateHerbStats('ei');
            updateMatorral('ei');
        }
    }

    // ───────────────────────────────────────────
    // SECTION TOGGLE
    // ───────────────────────────────────────────
    function initToggles() {
        document.querySelectorAll('.form-card-toggle').forEach(el => {
            el.addEventListener('click', () => {
                const targetId = el.dataset.toggle;
                const section = document.getElementById(targetId);
                if (section) {
                    section.classList.toggle('collapsed');
                    const icon = el.querySelector('.form-toggle-icon');
                    if (icon) icon.style.transform = section.classList.contains('collapsed') ? 'rotate(-90deg)' : '';
                }
            });
        });
    }

    // ───────────────────────────────────────────
    // INIT
    // ───────────────────────────────────────────
    function init() {
        const today = new Date().toISOString().split('T')[0];
        const vpFecha = document.getElementById('formvp-fecha');
        const elFecha = document.getElementById('formel-fecha');
        const eiFecha = document.getElementById('formei-fecha');
        if (vpFecha) vpFecha.value = today;
        if (elFecha) elFecha.value = today;
        if (eiFecha) eiFecha.value = today;

        generatePlantas();
        generatePalatables();
        generateHerbaceas('el');
        generateHerbaceas('ei');
        initToggles();
        initPhotoButtons();

        // Transecto buttons
        document.querySelectorAll('.form-transecto-btn').forEach(btn => {
            btn.addEventListener('click', () => setTransecto(parseInt(btn.dataset.t)));
        });

        // Save buttons
        const btnSaveVP = document.getElementById('btn-guardar-vp');
        if (btnSaveVP) btnSaveVP.addEventListener('click', () => saveForm('vp'));
        const btnSaveEL = document.getElementById('btn-guardar-el');
        if (btnSaveEL) btnSaveEL.addEventListener('click', () => saveForm('el'));
        const btnSaveEI = document.getElementById('btn-guardar-ei');
        if (btnSaveEI) btnSaveEI.addEventListener('click', () => saveForm('ei'));

        // Back buttons
        const btnVPBack = document.getElementById('btn-formvp-back');
        if (btnVPBack) btnVPBack.addEventListener('click', () => { if (window._showScreen) window._showScreen('ficha'); });
        const btnELBack = document.getElementById('btn-formel-back');
        if (btnELBack) btnELBack.addEventListener('click', () => { if (window._showScreen) window._showScreen('ficha'); });
        const btnEIBack = document.getElementById('btn-formei-back');
        if (btnEIBack) btnEIBack.addEventListener('click', () => { if (window._showScreen) window._showScreen('ficha'); });
    }

    // ───────────────────────────────────────────
    // EXPOSE API
    // ───────────────────────────────────────────
    window.RapcaForms = {
        init,
        showAC,
        filterAC,
        selectAC,
        updatePlantStats,
        updatePalatableStats,
        updateHerbStats,
        updateMatorral,
        addFormPhoto,
        getVPData,
        getELData,
        getEIData,
        setTransecto,
        formFotos,
    };

})();
