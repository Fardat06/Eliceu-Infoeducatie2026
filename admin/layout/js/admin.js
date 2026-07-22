/* admin/layout/js/admin.js — CRUD home_numa_liceu (cheie = name) */
$(function () {

  const API = (typeof window.API !== 'undefined') ? window.API : 'plugin/liceu_api.php';
  const modalEl = document.getElementById('modalForm');

  const modal = {
    show: () => { if (modalEl) { modalEl.hidden = false; document.body.style.overflow = 'hidden'; } },
    hide: () => { if (modalEl) { modalEl.hidden = true;  document.body.style.overflow = ''; } }
  };

  $('#modalForm').on('click', function (ev) {
    if (ev.target.closest('[data-close]')) modal.hide();
  });

  $(document).on('keydown', function (ev) {
    if (ev.key === 'Escape' && modalEl && !modalEl.hidden) modal.hide();
  });

  $('.modal-tab').on('click', function () {
    $('.modal-tab').removeClass('active');
    $('.modal-pane').removeClass('active');
    $(this).addClass('active');
    $('#' + $(this).data('tab')).addClass('active');
  });

  function firstTab() { $('.modal-tab').first().trigger('click'); }

  let selected = new Set();

  function esc(s) {
    return String(s).replace(/[&<>"']/g, c =>
      ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }
  function escRe(s) { return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

  function notify(msg, type = 'success') {
    const id = 'a' + Date.now() + Math.random().toString(36).slice(2, 6);
    $('#alertBox').append(
      `<div id="${id}" class="toast-msg ${type}">
         <span>${msg}</span>
         <button type="button" class="toast-close">&times;</button>
       </div>`);
    const $el = $('#' + id);
    $el.on('click', '.toast-close', () => $el.remove());
    setTimeout(() => $el.fadeOut(200, function () { $(this).remove(); }), 4500);
  }

  function medieClass(v) {
    v = parseFloat(v) || 0;
    if (v >= 8) return 'success';
    if (v >= 6) return 'warning';
    return 'danger';
  }

  const dt = $('#tbl').DataTable({
    ajax: { url: API + '?action=list', dataSrc: 'data' },
    pageLength: 10,
    lengthMenu: [10, 25, 50, 100, 200],
    order: [[2, 'asc']],
    language: {
      search: 'Caută:', searchPlaceholder: 'nume, adresă, tip…',
      lengthMenu: 'Arată _MENU_ înregistrări',
      info: 'Afișate _START_–_END_ din _TOTAL_',
      infoEmpty: 'Nicio înregistrare', infoFiltered: '(filtrate din _MAX_)',
      zeroRecords: 'Niciun rezultat', emptyTable: 'Tabel gol',
      processing: 'Se încarcă…',
      paginate: { first: '«', last: '»', next: '›', previous: '‹' }
    },
    columns: [
      { data: 'name', orderable: false, searchable: false,
        render: n => `<input type="checkbox" class="rowChk" value="${esc(n)}">` },

      { data: 'photo', orderable: false, searchable: false,
        render: p => p
          ? `<img src="${PHOTO_URL + encodeURIComponent(p)}" class="thumb" alt=""
                  onerror="this.replaceWith(document.createTextNode('—'))">`
          : '<span class="muted">—</span>' },

      { data: 'name', render: (n, t, r) => t !== 'display' ? n :
          `<div class="project-title-cell">
             <div class="project-info">
               <div class="project-title-text">${esc(n)}</div>
               <div class="project-meta-text">${esc(r.address || '')}</div>
             </div>
           </div>` },

      { data: 'tip' },
      { data: 'zone' },
      { data: 'no_clase' },
      { data: 'total_no_student' },

      { data: 'avrg_medie',
        render: (v, t) => t !== 'display' ? (parseFloat(v) || 0) :
          `<span class="status-badge ${medieClass(v)}">${(parseFloat(v) || 0).toFixed(2)}</span>` },

      { data: 'position' },

      { data: 'stopx',
        render: (v, t) => {
          const label = (+v === 1) ? 'Ascuns' : 'Activ';
          if (t !== 'display') return label;
          return (+v === 1)
            ? '<span class="status-badge warning">Ascuns</span>'
            : '<span class="status-badge success">Activ</span>';
        } },

      { data: 'name', orderable: false, searchable: false,
        render: n => `
          <div class="row-actions">
            <button class="icon-btn btnEdit" data-name="${esc(n)}" title="Modifică">
              <span class="material-symbols-rounded">edit</span></button>
            <button class="icon-btn btnToggle" data-name="${esc(n)}" title="Ascunde / Afișează">
              <span class="material-symbols-rounded">visibility</span></button>
            <button class="icon-btn danger btnDel" data-name="${esc(n)}" title="Șterge">
              <span class="material-symbols-rounded">delete</span></button>
          </div>` }
    ],
    initComplete: function () { stats(this.api()); }
  });

  function stats(api) {
    const d = api.rows().data().toArray();
    $('#sTotal').text(d.length);
    $('#sActive').text(d.filter(r => +r.stopx === 0).length);
    $('#sHidden').text(d.filter(r => +r.stopx === 1).length);
    $('#sNoPhoto').text(d.filter(r => !r.photo).length);
  }
  dt.on('xhr', () => setTimeout(() => stats(dt), 0));

  
  $('#fTip').on('change', function () {
    dt.column(3).search(this.value ? '^' + escRe(this.value) + '$' : '', true, false).draw();
  });
  $('#fZone').on('change', function () {
    dt.column(4).search(this.value ? '^' + escRe(this.value) + '$' : '', true, false).draw();
  });
  $('#fStop').on('change', function () {
    dt.column(9).search(this.value ? '^' + this.value + '$' : '', true, false).draw();
  });

  $.getJSON(API + '?action=lookups', d => {
    if (!d || !d.ok) return;
    d.tip.forEach(t  => { $('#fTip').append(new Option(t, t)); $('#fTipSel').append(new Option(t, t)); });
    d.zone.forEach(z => { $('#fZone').append(new Option(z, z)); $('#dlZone').append(`<option value="${esc(z)}">`); });
    d.city.forEach(c => $('#dlCity').append(`<option value="${esc(c)}">`));
  });

  /* ---------------- selecție ---------------- */
  function refreshSel() {
    $('#selCount').text(selected.size);
    $('#btnBulkDelete').prop('disabled', selected.size === 0);
  }
  $('#tbl tbody').on('change', '.rowChk', function () {
    this.checked ? selected.add(this.value) : selected.delete(this.value);
    refreshSel();
  });
  $('#chkAll').on('change', function () {
    const on = this.checked;
    $('#tbl tbody .rowChk').each(function () {
      this.checked = on;
      on ? selected.add(this.value) : selected.delete(this.value);
    });
    refreshSel();
  });
  dt.on('draw', function () {
    $('#tbl tbody .rowChk').each(function () { this.checked = selected.has(this.value); });
    refreshSel();
  });

  $('#btnAdd').on('click', function () {
    $('#frm')[0].reset();
    $('#fAction').val('create');
    $('#fOrigName').val('');
    $('#modalTitle').text('Adaugă liceu');
    $('#imgPreview').prop('hidden', true).attr('src', '');
    $('#fCity').val('Bucuresti');
    $('#cShort').text('0');
    firstTab();
    modal.show();
  });

  $('#tbl tbody').on('click', '.btnEdit', function () {
    const name = $(this).data('name');
    $.getJSON(API + '?action=get&name=' + encodeURIComponent(name), d => {
      if (!d.ok) return notify(d.msg, 'danger');
      const r = d.row;

      $('#fAction').val('update');
      $('#fOrigName').val(r.name);
      $('#modalTitle').text('Modifică: ' + r.name);

      if (r.tip && !$('#fTipSel option').filter(function () { return this.value === r.tip; }).length) {
        $('#fTipSel').append(new Option(r.tip, r.tip));
      }

      $('#fName').val(r.name);
      $('#fTipSel').val(r.tip);
      $('#fCity').val(r.city);
      $('#fZoneIn').val(r.zone);
      $('#fAddress').val(r.address);
      $('#fWeb').val(r.web_page);
      $('#fPhoto').val(r.photo);
      $('#fNoClase').val(r.no_clase);
      $('#fTotal').val(r.total_no_student);
      $('#fRomi').val(r.romi_student);
      $('#fCes').val(r.ces_student);
      $('#fMedie').val(r.avrg_medie);
      $('#fPos').val(r.position);
      $('#fShort').val(r.short_description);
      $('#fLong').val(r.long_description);
      $('#fDesc').val(r.description);
      $('#fStopx').prop('checked', +r.stopx === 1);
      $('#cShort').text((r.short_description || '').length);
      $('#fFile').val('');

      if (r.photo) {
        $('#imgPreview').attr('src', PHOTO_URL + encodeURIComponent(r.photo)).prop('hidden', false);
      } else {
        $('#imgPreview').prop('hidden', true);
      }

      firstTab();
      modal.show();
    }).fail(() => notify('Nu s-au putut încărca datele liceului.', 'danger'));
  });

  $('#fPhoto').on('input', function () {
    this.value
      ? $('#imgPreview').attr('src', PHOTO_URL + encodeURIComponent(this.value)).prop('hidden', false)
      : $('#imgPreview').prop('hidden', true);
  });
  $('#fFile').on('change', function () {
    if (!this.files || !this.files[0]) return;
    $('#imgPreview').attr('src', URL.createObjectURL(this.files[0])).prop('hidden', false);
  });
  $('#fShort').on('input', function () { $('#cShort').text(this.value.length); });

$('#frm').on('submit', function (ev) {
    ev.preventDefault();
    if (!this.checkValidity()) { this.reportValidity(); return; }

  
    const orig = $('#fOrigName').val();
    const nou  = $('#fName').val().trim();
    if (orig && nou !== orig) {
      if (!confirm('Redenumești „' + orig + '” în „' + nou + '”.\n\n' +
                   'Numele va fi actualizat automat în tabelele de specializări, medii, ' +
                   'poziții și admitere. Continui?')) {
        return;
      }
    }

    const fd = new FormData(this);
    $('#btnSave').prop('disabled', true).addClass('loading');

    $.ajax({ url: API, method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
      .done(d => {
        if (d.ok) { modal.hide(); notify(d.msg); dt.ajax.reload(null, false); }
        else notify(d.msg, 'danger');
      })
      .fail(x => notify((x.responseJSON && x.responseJSON.msg) || 'Eroare la salvare.', 'danger'))
      .always(() => { $('#btnSave').prop('disabled', false).removeClass('loading'); });
  });

  function post(data, after) {
    data.csrf = $('input[name=csrf]').val();
    $.post(API, data, null, 'json')
      .done(d => {
        notify(d.msg, d.ok ? 'success' : 'warning');
        dt.ajax.reload(null, false);
        if (after) after();
      })
      .fail(x => notify((x.responseJSON && x.responseJSON.msg) || 'Eroare.', 'danger'));
  }

  $('#tbl tbody').on('click', '.btnDel', function () {
    const name = $(this).data('name');
    if (!confirm('Ștergi definitiv „' + name + '”?\nAcțiunea nu poate fi anulată.')) return;
    post({ action: 'delete', name: name }, () => { selected.delete(name); refreshSel(); });
  });

  $('#tbl tbody').on('click', '.btnToggle', function () {
    post({ action: 'toggle_stop', name: $(this).data('name') });
  });

  $('#btnBulkDelete').on('click', function () {
    if (!selected.size) return;
    if (!confirm('Ștergi ' + selected.size + ' liceu(e) selectate?')) return;
    post({ action: 'bulk_delete', names: [...selected] }, () => {
      selected.clear();
      $('#chkAll').prop('checked', false);
      refreshSel();
    });
  });
});

$(function () {
  if (!window.DASH || !window.Chart) return;

  const c1 = document.getElementById('progressChart');
  if (c1) new Chart(c1, {
    type: 'bar',
    data: { labels: DASH.sector.labels, datasets: [{ label: 'Licee', data: DASH.sector.data, borderRadius: 6 }] },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
  });

  const c2 = document.getElementById('categoryChart');
  if (c2) new Chart(c2, {
    type: 'doughnut',
    data: { labels: DASH.tip.labels, datasets: [{ data: DASH.tip.data }] },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } }
    }
  });
});
