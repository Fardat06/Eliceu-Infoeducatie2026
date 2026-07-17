// dragdrop.js — SortableJS reorder for the personal list, saved to the DB.

let sortable = null;

function initDragDrop() {
  const grid = document.getElementById('productsGrid');
  if (!grid || typeof Sortable === 'undefined') return;
  if (sortable) { sortable.destroy(); sortable = null; }   // rebind cleanly after AJAX

  // only the personal list uses sortable cards
  if (!grid.querySelector('.sort-item')) return;

  sortable = Sortable.create(grid, {
    animation: 150,
    draggable: '.sort-item',
    ghostClass: 'hovered',
    scroll: true,             // autoscroll the page near the edges
    scrollSensitivity: 80,    // px from edge that triggers scroll
    scrollSpeed: 12,
    onEnd: saveOrder
  });
}

function currentOrder() {
  return [...document.querySelectorAll('#productsGrid .sort-item')]
    .map(card => card.id)
    .filter(Boolean);
}

function saveOrder() {
  const order = currentOrder();
  if (!order.length) return;
  const csrf = (document.getElementById('csrfToken') || {}).value
            || (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  fetch('plugin/save_order.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
    body: JSON.stringify({ order, csrf_token: csrf, list: 's' })
  })
    .then(r => r.json())
    .then(d => { if (!d.ok) console.error('save order failed:', d.error); })
    .catch(err => console.error('save order error:', err));
}

document.addEventListener('DOMContentLoaded', initDragDrop);

const _loadingEl = document.getElementById('loading');
if (_loadingEl) new MutationObserver(() => initDragDrop()).observe(_loadingEl, { childList: true });

function sendListaEmail(id) {
    var elem  = document.getElementById(id),
        value = elem.value;
   fetch("plugin/sendemaillist.php?id="+elem.id, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `name=${encodeURIComponent(id)}`
  });

}
