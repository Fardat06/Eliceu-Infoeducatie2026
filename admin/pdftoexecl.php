<?php
ob_start();
require_once __DIR__ . '/plugin/admin_init.php';
ob_clean();

if (!function_exists('csrf_token')) {
    die('admin_init.php nu a fost încărcat.');
}
if (!isset($_SESSION['username-x'])) {
    header('Location: index.php');
    exit();
}

$csrf = csrf_token();
$pageTitle = 'Date specializări';

include __DIR__ . '/template/header.php';
?>
<style>
  :root{--accent:#2c3e50;--line:#e3e6ea;}
  *{box-sizing:border-box;}
  body{font-family:Arial,Helvetica,sans-serif;background:#f4f6f8;color:#1f2329;margin:0;}
  .wrap{max-width:1000px;margin:auto;background:#fff;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.08);padding:28px;}
  h1{font-size:20px;margin:0 0 4px;color:var(--accent);}
  p.sub{margin:0 0 20px;color:#6b7280;font-size:14px;}
  .modebar{display:flex;gap:16px;margin:0 0 18px;padding:12px 14px;background:#f7f9fb;border:1px solid var(--line);border-radius:10px;font-size:14px;}
  .modebar label{display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:600;}
  .modebar input[type=radio]{accent-color:var(--accent);}
  .drop{border:2px dashed #c3ccd6;border-radius:10px;padding:28px;text-align:center;color:#6b7280;cursor:pointer;transition:.15s;}
  .drop:hover,.drop.over{border-color:var(--accent);background:#f7f9fb;}
  .row{display:flex;gap:10px;align-items:center;margin-top:16px;flex-wrap:wrap;}
  button{background:var(--accent);color:#fff;border:0;border-radius:8px;padding:11px 18px;font-size:14px;cursor:pointer;}
  button:disabled{opacity:.5;cursor:not-allowed;}
  .bar{height:8px;background:#eef1f4;border-radius:6px;overflow:hidden;margin-top:16px;display:none;}
  .bar > div{height:100%;width:0;background:var(--accent);transition:width .2s;}
  .status{font-size:13px;color:#6b7280;margin-top:8px;min-height:18px;}
  table{border-collapse:collapse;width:100%;margin-top:18px;font-size:12px;}
  th,td{border:1px solid var(--line);padding:5px 7px;text-align:left;vertical-align:top;}
  th{background:#f0f3f6;position:sticky;top:0;}
  .scroll{max-height:380px;overflow:auto;margin-top:10px;border:1px solid var(--line);border-radius:8px;}
  .note{font-size:12px;color:#9aa3ad;margin-top:14px;}
  .fix-h{font-size:15px;color:#c0392b;margin:0 0 10px;}
  .fix-list{display:flex;flex-direction:column;gap:8px;}
  .fix-row{display:flex;align-items:center;gap:12px;background:#fff7f6;border:1px solid #f3d6d2;border-radius:8px;padding:10px 12px;}
  .fix-input{flex:1;border:1px solid #d9d2e3;border-radius:6px;padding:8px 10px;font-size:13px;}
</style>

<div class="dashboard-container">
  <?php include __DIR__ . '/template/sidebar.php'; ?>
  <div class="dashboard-sidebar-overlay" id="dashboardSidebarOverlay"></div>

  <main class="dashboard-main">
    <?php include __DIR__ . '/template/header_main.php'; ?>

    <div class="dashboard-content" style="border-top-left-radius:0px;">

<div class="wrap">
  <h1>Broșură admitere București → Excel / MySQL</h1>
  <p class="sub">Extrage date din broșura PDF, direct în browser.</p>

  <div class="modebar">
    <label><input type="radio" name="mode" value="admitere" checked>
      Admitere 2026 <small style="color:#6b7280;font-weight:400">(specializări, medii, codificări)</small>
    </label>
    <label><input type="radio" name="mode" value="schools">
      Listă licee <small style="color:#6b7280;font-weight:400">(rețea unități — sector, adresă, telefon)</small>
    </label>
  </div>

  <div class="drop" id="drop">Trage PDF-ul aici sau apasă pentru a alege fișierul
    <input type="file" id="file" accept="application/pdf" hidden>
  </div>

  <div class="row">
    <button id="convert" disabled style="width: 32%;" >Convertește</button>
    <button id="download" disabled style="width: 32%;">Descarcă Excel</button>
    <button id="save" disabled style="width: 32%;">Salvează în MySQL</button>
    <span id="fname" class="status"></span>
  </div>

  <div class="bar" id="bar"><div></div></div>
  <div class="status" id="status"></div>

  <div id="fixWrap" style="display:none;margin-top:18px;">
    <h2 class="fix-h">Școli fără nume — completează înainte de import</h2>
    <div id="fixList" class="fix-list"></div>
  </div>

  <div class="scroll" id="previewWrap" style="display:none;">
    <table id="preview"><thead></thead><tbody></tbody></table>
  </div>
  <div class="note">Pozițiile coloanelor sunt calibrate pentru macheta broșurii 2026. Dacă un alt PDF are alt aranjament, pragurile din <code>colOf</code> / <code>colOfSchool</code> trebuie ajustate.</div>
</div>

    </div>
  </main>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";

let rows    = [];   // ADMITERE: array of arrays  |  SCHOOLS: array of objects
let pdfFile = null;
let mode    = 'admitere';

const $ = id => document.getElementById(id);
const drop = $('drop'), fileInput = $('file');

document.querySelectorAll('input[name=mode]').forEach(r => {
  r.onchange = () => {
    mode = r.value;
    rows = [];
    $('download').disabled = true;
    $('save').disabled     = true;
    $('previewWrap').style.display = 'none';
    $('fixWrap').style.display     = 'none';
    $('status').textContent = '';
    $('bar').style.display  = 'none';
    $('bar').firstElementChild.style.width = '0%';
  };
});

drop.onclick = () => fileInput.click();
drop.ondragover  = e => { e.preventDefault(); drop.classList.add('over'); };
drop.ondragleave = ()=> drop.classList.remove('over');
drop.ondrop = e => {
  e.preventDefault(); drop.classList.remove('over');
  if (e.dataTransfer.files[0]) setFile(e.dataTransfer.files[0]);
};
fileInput.onchange = e => { if (e.target.files[0]) setFile(e.target.files[0]); };

function setFile(f) {
  pdfFile = f;
  $('fname').textContent = f.name;
  $('convert').disabled  = false;
  $('download').disabled = true;
  $('save').disabled     = true;
}

function colOf(x){
  if(x<55)  return 'nr';
  if(x<248) return 'name';
  if(x<278) return 'clase';
  if(x<312) return 'total';
  if(x<345) return 'romi';
  if(x<382) return 'ces';
  if(x<430) return 'media';
  if(x<476) return 'cod';
  return 'obs';
}
const numish = s => /^\d+(\.\d+)?$/.test((s||'').trim());

const HEADERS_ADM = ["Nr","Tip scoala","Nume scoala","Filiera","Profil","Specializare","Mentiune",
  "Clase","Total locuri","Locuri romi","Locuri CES","Media ultimului admis","Codificare","Observatii","Specializare (complet)"];

function splitSchool(s){
  s = (s||'').toString();
  const m = s.match(/[„\u201C\u201D\u201E\u2018\u2019"']/);
  if (!m) return [s.trim(), ''];
  const i = m.index;
  const name = s.slice(i).replace(/[„\u201C\u201D\u201E\u2018\u2019"']/g, '').trim();
  return [s.slice(0, i).trim(), name];
}
const QUAL = /^(bilingv|intensiv)/i;
const normSpec = t => (t||'').replace(/\s+/g,' ').trim().replace(/(\p{L})\s*-\s*(?=\p{L})/gu,'$1-');
function splitSpec(s){
  const p = (s||'').split(' - ').map(x=>x.trim()).filter(Boolean);
  const fil = p[0]||'', prof = p[1]||'', detail = p.slice(2);
  const core = normSpec(detail.filter(d=>!QUAL.test(d)).join(' - '));
  const ment = detail.filter(d=>QUAL.test(d)).map(normSpec).join(' - ');
  return [fil, prof, core, ment];
}
const toNum = x => { x=(x||'').replace(',','.').trim(); return /^\d+(\.\d+)?$/.test(x)?parseFloat(x):(x||''); };

function parsePageAdmitere(items, pageHeight){
  let ws = items.map(it=>({x:it.transform[4], top:pageHeight-it.transform[5], text:it.str}))
                .filter(w=>w.text.trim()!=='' && w.top>130);
  if (!ws.length) return [];
  ws.sort((a,b)=> a.top-b.top || a.x-b.x);
  let lines=[], cur=[], cy=null;
  for (const w of ws){
    if (cy===null || Math.abs(w.top-cy)<=3.5) cur.push(w);
    else { lines.push(cur); cur=[w]; }
    cy = w.top;
  }
  if (cur.length) lines.push(cur);
  const L = lines.map(ln => {
    const d = {top:Math.min(...ln.map(w=>w.top)), name:[], obs:[], nums:{}};
    ln.slice().sort((a,b)=>a.x-b.x).forEach(w => {
      const c = colOf(w.x);
      if (c==='name') d.name.push(w.text);
      else if (c==='obs') d.obs.push(w.text);
      else if (c==='nr') {}
      else d.nums[c] = w.text.trim();
    });
    d.name = d.name.join(' ').trim();
    d.obs  = d.obs.join(' ').trim();
    return d;
  });
  const data = L.filter(d => 'clase' in d.nums);
  data.forEach(d => { d._name=d.name?[[d.top,d.name]]:[]; d._obs=d.obs?[[d.top,d.obs]]:[]; });
  L.filter(d => !('clase' in d.nums)).forEach(t => {
    if (!data.length) return;
    let nd = data.reduce((a,b) => Math.abs(b.top-t.top)<Math.abs(a.top-t.top)?b:a);
    if (Math.abs(nd.top-t.top)<=22){
      if (t.name) nd._name.push([t.top, t.name]);
      if (t.obs)  nd._obs.push([t.top, t.obs]);
    }
  });
  const recs=[]; let school=null;
  data.sort((a,b)=>a.top-b.top).forEach(d => {
    const name = d._name.sort((a,b)=>a[0]-b[0]).map(f=>f[1]).join(' ').trim();
    const obs  = d._obs.sort((a,b)=>a[0]-b[0]).map(f=>f[1]).join(' ').trim();
    const n = d.nums;
    if (!('cod' in n)){ if (numish(n.clase) && name) school = name; }
    else if (/^\d+$/.test(n.cod) && school){
      recs.push([school, name, n.clase||'', n.total||'', n.romi||'', n.ces||'', n.media||'', n.cod, obs]);
    }
  });
  return recs;
}

function buildRecords(){
  return rows.map((r, i) => {
    const [tip, nume] = splitSchool(r[0]);
    const [fil, prof, spec, ment] = splitSpec(r[1]);
    return {
      nr:i+1, tip_scoala:tip, nume_scoala:nume, filiera:fil, profil:prof,
      specializare:spec, mentiune:ment, clase:r[2], total_locuri:r[3],
      locuri_romi:r[4], locuri_ces:r[5], media_ultimului_admis:r[6],
      codificare:r[7], observatii:r[8], specializare_complet:r[1]
    };
  });
}

function renderNameFixes(){
  const recs = buildRecords();
  const empties = [...new Set(recs.filter(r=>r.nume_scoala==='').map(r=>r.tip_scoala))];
  const wrap=$('fixWrap'), list=$('fixList');
  if (!empties.length){ wrap.style.display='none'; list.innerHTML=''; return; }
  list.innerHTML = empties.map(tip => {
    const attr = tip.replace(/"/g,'&quot;');
    return `<div class="fix-row" data-tip="${attr}">`+
      `<input class="fix-input fix-tip-input" value="${attr}" placeholder="Tip școală...">`+
      `<input class="fix-input fix-nume-input" placeholder="Nume școală...">`+
      `</div>`;
  }).join('');
  wrap.style.display = 'block';
}

function applyNameFixes(recs){
  const map = {};
  document.querySelectorAll('#fixList .fix-row').forEach(row => {
    map[row.dataset.tip] = {
      tip:  row.querySelector('.fix-tip-input').value.trim(),
      nume: row.querySelector('.fix-nume-input').value.trim()
    };
  });
  recs.forEach(r => {
    if (r.nume_scoala==='' && map[r.tip_scoala]){
      const f = map[r.tip_scoala];
      if (f.nume) r.nume_scoala = f.nume;
      if (f.tip)  r.tip_scoala  = f.tip;
    }
  });
  return recs;
}

function renderPreviewAdmitere(){
  const thead=$('preview').querySelector('thead'), tbody=$('preview').querySelector('tbody');
  thead.innerHTML = '<tr>'+HEADERS_ADM.map(h=>`<th>${h}</th>`).join('')+'</tr>';
  tbody.innerHTML = rows.slice(0,40).map((r,i)=>{
    const [tip,nume] = splitSchool(r[0]);
    const [fil,prof,spec,ment] = splitSpec(r[1]);
    const cells=[i+1,tip,nume,fil,prof,spec,ment,r[2],r[3],r[4],r[5],r[6],r[7],r[8],r[1]];
    return '<tr>'+cells.map(c=>`<td>${(c??'').toString().replace(/</g,'&lt;')}</td>`).join('')+'</tr>';
  }).join('');
  $('previewWrap').style.display = 'block';
}

const HEADERS_SCH = ["Nr","Tip scoala","Nume scoala","Adresa","Telefon","Puncte de reper","Sector"];

function colOfSchool(x){
  if (x < 85)  return 'nr';
  if (x < 258) return 'name';
  if (x < 405) return 'adresa';
  if (x < 505) return 'telefon';
  return 'puncte';
}

function joinCol(arr){
  arr.sort((a,b) => a.top-b.top || a.x-b.x);
  const parts=[]; let cur=[], cy=null;
  for (const w of arr){
    if (cy===null || Math.abs(w.top-cy)<=3.5) cur.push(w);
    else { parts.push(cur.map(x=>x.text).join(' ')); cur=[w]; }
    cy = w.top;
  }
  if (cur.length) parts.push(cur.map(x=>x.text).join(' '));
  return parts.join(' ').replace(/\s+/g,' ').trim();
}

function parsePageSchools(items, pageWidth, pageHeight, state){
  let ws = items.map((it, i) => ({
    id: i,
    x:   it.transform[4],
    top: pageHeight - it.transform[5],
    text: it.str
  })).filter(w => w.text.trim() !== '');
  if (!ws.length) return [];

  const flat = ws.map(w=>w.text).join(' ');
  if (!/Unitatea de învă[țţ]ăm[âa]nt/i.test(flat) && !/LICEAL - DIN MUNICIPIUL/i.test(flat)) return [];

  ws = ws.filter(w => !(
    /^\d+$/.test(w.text.trim())
    && w.top > pageHeight - 70
    && Math.abs(w.x - pageWidth/2) < 60
  ));

  const sectors = [], sectorIds = new Set();
  ws.forEach(w => {
    const t = w.text.trim();
    const combined = t.match(/^SECTOR\s+([1-6])$/);
    if (combined){
      sectors.push({top: w.top, sector: parseInt(combined[1])});
      sectorIds.add(w.id);
    } else if (t === 'SECTOR'){
      const nb = ws.find(o => o.id !== w.id
                               && Math.abs(o.top - w.top) <= 3
                               && o.x > w.x
                               && /^[1-6]$/.test(o.text.trim()));
      if (nb){
        sectors.push({top: w.top, sector: parseInt(nb.text.trim())});
        sectorIds.add(w.id); sectorIds.add(nb.id);
      }
    }
  });
  sectors.sort((a,b) => a.top-b.top);

  const anchors = ws
    .filter(w => w.x < 85 && /^\d+\.$/.test(w.text.trim()))
    .map(w => ({top: w.top, nr: parseInt(w.text), id: w.id}))
    .sort((a,b) => a.top-b.top);

  if (!anchors.length){
    if (sectors.length) state.currentSector = sectors[sectors.length-1].sector;
    return [];
  }
  const anchorIds = new Set(anchors.map(a => a.id));

  const contentTops = [...new Set(
    ws.filter(w => !sectorIds.has(w.id) && !anchorIds.has(w.id)).map(w => w.top)
  )].sort((a,b) => a-b);

  const rowBottom = [];
  for (let i=0; i<anchors.length; i++){
    if (i+1 >= anchors.length){ rowBottom.push(pageHeight-20); continue; }
    const lo=anchors[i].top, hi=anchors[i+1].top;
    const local = contentTops.filter(t => t > lo-3 && t < hi+3);
    if (local.length < 2){ rowBottom.push((lo+hi)/2); continue; }
    let best=(lo+hi)/2, maxGap=0;
    for (let k=1; k<local.length; k++){
      const g = local[k]-local[k-1];
      if (g > maxGap){ maxGap=g; best=(local[k-1]+local[k])/2; }
    }
    rowBottom.push(best);
  }

  const rows = [];
  for (let i=0; i<anchors.length; i++){
    const rowTop = anchors[i].top;
    const prior = sectors.filter(s => s.top < rowTop);
    if (prior.length) state.currentSector = prior[prior.length-1].sector;
    const sect = state.currentSector;

    let bandTop;
    if (i === 0){
      const prevSecTop = sectors.filter(s => s.top < rowTop).reduce((m,s)=>Math.max(m,s.top), 0);
      bandTop = Math.max(90, prevSecTop + 5);
    } else {
      bandTop = rowBottom[i-1];
    }
    const bandBottom = rowBottom[i];

    const groups = {name:[], adresa:[], telefon:[], puncte:[]};
    for (const w of ws){
      if (sectorIds.has(w.id) || anchorIds.has(w.id)) continue;
      if (w.top < bandTop || w.top >= bandBottom) continue;
      if (w.x < 85) continue;
      const col = colOfSchool(w.x);
      if (col in groups) groups[col].push(w);
    }

    rows.push({
      nr:          anchors[i].nr,
      nume_scoala: joinCol(groups.name),
      adresa:      joinCol(groups.adresa),
      telefon:     joinCol(groups.telefon),
      puncte_reper:joinCol(groups.puncte),
      sector:      sect,
    });
  }

  if (sectors.length) state.currentSector = sectors[sectors.length-1].sector;
  return rows;
}

function renderPreviewSchools(){
  const thead=$('preview').querySelector('thead'), tbody=$('preview').querySelector('tbody');
  thead.innerHTML='<tr>'+HEADERS_SCH.map(h=>`<th>${h}</th>`).join('')+'</tr>';
  tbody.innerHTML = rows.map((r, idx) => {
    const [tip, nume] = splitSchool(r.nume_scoala);
    // cache initial split so save can fall back when no edits were made
    if (r._tip  === undefined) r._tip  = tip;
    if (r._nume === undefined) r._nume = nume;
    const missing  = r._nume === '';
    const rowStyle = missing ? 'background:#fff7f0;' : '';
    const tipV  = (r._tip  ||'').replace(/"/g,'&quot;');
    const numeV = (r._nume ||'').replace(/"/g,'&quot;');
    return `<tr data-idx="${idx}" data-nr="${r.nr}" style="${rowStyle}">
      <td style="text-align:center;color:#888;font-size:11px;">${r.nr}</td>
      <td><input class="sch-tip"  data-idx="${idx}" value="${tipV}"
            style="width:100%;border:1px solid #d9d2e3;border-radius:4px;padding:3px 6px;font-size:11px;"></td>
      <td><input class="sch-nume" data-idx="${idx}" value="${numeV}"
            style="width:100%;border:1px solid ${missing?'#e74c3c':'#d9d2e3'};border-radius:4px;padding:3px 6px;font-size:11px;"
            placeholder="${missing?'Completează numele...':''}"></td>
      <td style="font-size:11px;">${(r.adresa||'').replace(/</g,'&lt;')}</td>
      <td style="font-size:11px;">${(r.telefon||'').replace(/</g,'&lt;')}</td>
      <td style="font-size:11px;color:#888;">${(r.puncte_reper||'').substring(0,60).replace(/</g,'&lt;')}${(r.puncte_reper||'').length>60?'…':''}</td>
      <td style="text-align:center;">${r.sector??''}</td>
    </tr>`;
  }).join('');
  $('previewWrap').style.display='block';

  $('preview').querySelector('tbody').addEventListener('input', e => {
    const idx = parseInt(e.target.dataset.idx, 10);
    if (isNaN(idx)) return;
    if (e.target.classList.contains('sch-tip'))  rows[idx]._tip  = e.target.value;
    if (e.target.classList.contains('sch-nume')){
      rows[idx]._nume = e.target.value;
      const empty = !e.target.value.trim();
      e.target.style.borderColor = empty ? '#e74c3c' : '#d9d2e3';
      e.target.closest('tr').style.background = empty ? '#fff7f0' : '';
    }
  });
}

function renderSchoolFixes(){
  // pre-split every row so _tip/_nume are initialised
  rows.forEach(r => {
    if (r._tip  === undefined){ const [t,n]=splitSchool(r.nume_scoala); r._tip=t; r._nume=n; }
  });
  const empties = rows.filter(r => r._nume.trim() === '');
  const wrap=$('fixWrap'), list=$('fixList');
  if (!empties.length){ wrap.style.display='none'; list.innerHTML=''; return; }

  list.innerHTML = empties.map(r => {
    const attr = (r._tip||'').replace(/"/g,'&quot;');
    return `<div class="fix-row" data-nr="${r.nr}">` +
      `<input class="fix-input fix-sch-tip"  value="${attr}" placeholder="Tip scoala...">` +
      `<input class="fix-input fix-sch-nume" placeholder="Nume scoala...">` +
      `</div>`;
  }).join('');

  list.querySelectorAll('.fix-row').forEach(row => {
    const nr = parseInt(row.dataset.nr, 10);
    const rec = rows.find(r => r.nr === nr);
    if (!rec) return;

    row.querySelector('.fix-sch-tip').addEventListener('input', e => {
      rec._tip = e.target.value;
      syncPreviewRow(nr);
    });
    row.querySelector('.fix-sch-nume').addEventListener('input', e => {
      rec._nume = e.target.value;
      syncPreviewRow(nr);
      row.style.display = e.target.value.trim() ? 'none' : '';
      if ([...list.querySelectorAll('.fix-row')].every(r=>r.style.display==='none'))
        wrap.style.display='none';
    });
  });

  wrap.style.display = 'block';
}
function syncPreviewRow(nr){
  const tr = $('preview').querySelector(`tr[data-nr="${nr}"]`);
  if (!tr) return;
  const rec = rows.find(r => r.nr === nr);
  if (!rec) return;
  const tipIn  = tr.querySelector('.sch-tip');
  const numeIn = tr.querySelector('.sch-nume');
  if (tipIn)  tipIn.value  = rec._tip  || '';
  if (numeIn){ numeIn.value = rec._nume || '';
               numeIn.style.borderColor = rec._nume.trim() ? '#d9d2e3' : '#e74c3c';
               tr.style.background = rec._nume.trim() ? '' : '#fff7f0'; }
}

$('convert').onclick = async () => {
  if (!pdfFile) return;
  rows = [];
  $('convert').disabled = true; $('download').disabled = true; $('save').disabled = true;
  $('bar').style.display = 'block';
  const fill = $('bar').firstElementChild;
  const buf  = await pdfFile.arrayBuffer();
  const pdf  = await pdfjsLib.getDocument({data:buf}).promise;
  const state = {currentSector: null};   // used by schools mode

  for (let p=1; p<=pdf.numPages; p++){
    const page = await pdf.getPage(p);
    const vp   = page.getViewport({scale:1});
    const tc   = await page.getTextContent();
    if (mode === 'admitere'){
      rows.push(...parsePageAdmitere(tc.items, vp.height));
    } else {
      rows.push(...parsePageSchools(tc.items, vp.width, vp.height, state));
    }
    fill.style.width = (p/pdf.numPages*100)+'%';
    const label = mode==='admitere' ? 'specializări' : 'școli';
    $('status').textContent = `Procesare pagina ${p}/${pdf.numPages}… ${label} găsite: ${rows.length}`;
  }

  if (mode === 'admitere'){
    $('status').textContent = `Gata: ${rows.length} specializări, ${new Set(rows.map(r=>r[0])).size} școli.`;
    renderPreviewAdmitere();
    renderNameFixes();
    $('fixWrap').style.display = document.querySelectorAll('#fixList .fix-row').length ? 'block' : 'none';
  } else {
    const bySector = {};
    rows.forEach(r => { bySector[r.sector] = (bySector[r.sector]||0)+1; });
    const parts = Object.keys(bySector).sort().map(s=>`S${s}:${bySector[s]}`).join('  ');
    $('status').textContent = `Gata: ${rows.length} școli.  ${parts}`;
    renderPreviewSchools();
    renderSchoolFixes();
  }

  $('download').disabled = rows.length === 0;
  $('save').disabled     = rows.length === 0;
  $('convert').disabled  = false;
};

$('download').onclick = () => {
  if (mode === 'admitere'){
    const aoa = [HEADERS_ADM];
    rows.forEach((r,i) => {
      const [tip,nume] = splitSchool(r[0]);
      const [fil,prof,spec,ment] = splitSpec(r[1]);
      aoa.push([i+1,tip,nume,fil,prof,spec,ment,toNum(r[2]),toNum(r[3]),toNum(r[4]),toNum(r[5]),toNum(r[6]),r[7],r[8],r[1]]);
    });
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    ws['!cols'] = [5,26,24,18,12,24,22,7,11,11,11,13,11,28,40].map(w=>({wch:w}));
    ws['!freeze'] = {xSplit:0, ySplit:1};
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Admitere 2026");
    XLSX.writeFile(wb, "Admitere_2026_Bucuresti.xlsx");
  } else {
    const aoa = [HEADERS_SCH];
    rows.forEach(r => {
      const tip  = (r._tip  !== undefined) ? r._tip  : splitSchool(r.nume_scoala)[0];
      const nume = (r._nume !== undefined) ? r._nume : splitSchool(r.nume_scoala)[1];
      aoa.push([r.nr, tip, nume, r.adresa, r.telefon, r.puncte_reper, r.sector]);
    });
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    ws['!cols'] = [5,26,40,32,22,60,8].map(w=>({wch:w}));
    ws['!freeze'] = {xSplit:0, ySplit:1};
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Licee 2026");
    XLSX.writeFile(wb, "Licee_Bucuresti_2026.xlsx");
  }
};

$('save').onclick = async () => {
  if (!rows.length) return;
  $('save').disabled = true;
  $('status').textContent = 'Se salvează în baza de date…';
  try {
    const endpoint = mode==='admitere' ? 'plugin/import_admitere.php' : 'plugin/import_school.php';
    const payload  = mode==='admitere'
      ? applyNameFixes(buildRecords())
      : rows.map(r => {
          const tip  = (r._tip  !== undefined) ? r._tip  : splitSchool(r.nume_scoala)[0];
          const nume = (r._nume !== undefined) ? r._nume : splitSchool(r.nume_scoala)[1];
          return { ...r, tip_scoala: tip, nume_scoala: nume };
        });

    const res = await fetch(endpoint, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({rows: payload})
    });
    const d = await res.json();
    if (d.ok){
      const noun       = mode==='admitere' ? 'specializări'    : 'școli';
      const skipReason = mode==='admitere' ? 'fără codificare' : 'fără nume';
      $('status').textContent = `Salvat în MySQL: ${d.processed} ${noun}`+
        (d.skipped ? ` (${d.skipped} ignorate, ${skipReason})` : '')+'.';
    } else {
      $('status').textContent = 'Eroare la salvare: '+(d.error||'necunoscută');
    }
  } catch(e){
    $('status').textContent = 'Eroare rețea: '+e.message;
  }
  $('save').disabled = false;
};
</script>
<?php
include __DIR__ . '/template/footer.php';
ob_end_flush();
