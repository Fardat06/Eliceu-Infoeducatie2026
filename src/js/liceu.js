
const menuBtn = document.getElementById("menuBtn");
const sidebar = document.getElementById("sidebar");
const closeBtn = document.getElementById("closeBtn");

if (menuBtn && sidebar) {
  menuBtn.addEventListener("click", () => {
    sidebar.classList.add("active");
  });
}

if (closeBtn && sidebar) {
  closeBtn.addEventListener("click", () => {
    sidebar.classList.remove("active");
  });
}


  function toggleDetalii(id) {
    const box = document.getElementById(id);
    box.classList.toggle("active");
  }

  window.addEventListener("load", function() {
    const params = new URLSearchParams(window.location.search);
    const profil = params.get("profil");

    if (profil) {
      const detalii = document.getElementById(profil + "-detalii");

      if (detalii) {
        detalii.classList.add("active");

        setTimeout(function() {
          detalii.scrollIntoView({
            behavior: "smooth",
            block: "center"
          });
        }, 200);
      }
    }
  });
const compareGoBtn = document.getElementById("compareGoBtn");
if (compareGoBtn) {
  compareGoBtn.addEventListener("click", () => {
    const ids = JSON.parse(localStorage.getItem("compareIds") || "[]");
    if (ids.length < 2) return;
    localStorage.removeItem("compareIds");
    window.location.href = "compare_general.php?ids=" + ids.join(",");
  });
}

const compareGoBtnLista = document.getElementById("compareGoBtnLista");
if (compareGoBtnLista) {
  compareGoBtnLista.addEventListener("click", () => {
    const ids = JSON.parse(localStorage.getItem("compareIds") || "[]");
    if (ids.length < 2) return;
    localStorage.removeItem("compareIds");
    window.location.href = "compare_specializari.php?ids=" + ids.join(",");
  });
}

function restoreView() {
  const savedView = localStorage.getItem("viewMode") || "grid";
  const grid = document.getElementById("productsGrid");
  if (!grid) return;
  if (savedView === "list") {
    grid.classList.add("list-view");
    grid.classList.remove("grid-view");
    document.getElementById("listViewBtn").classList.add("active");
    document.getElementById("gridViewBtn").classList.remove("active");
  } else {
    grid.classList.add("grid-view");
    grid.classList.remove("list-view");
    document.getElementById("gridViewBtn").classList.add("active");
    document.getElementById("listViewBtn").classList.remove("active");
  }
}

const listViewBtn = document.getElementById("listViewBtn");
if (listViewBtn) {
  listViewBtn.addEventListener("click", () => {
    localStorage.setItem("viewMode", "list");
    document.getElementById("listViewBtn").classList.add("active");
    document.getElementById("gridViewBtn").classList.remove("active");
    document.getElementById("productsGrid").classList.add("list-view");
    document.getElementById("productsGrid").classList.remove("grid-view");
  });
}

const gridViewBtn = document.getElementById("gridViewBtn");
if (gridViewBtn) {
  gridViewBtn.addEventListener("click", () => {
    localStorage.setItem("viewMode", "grid");
    document.getElementById("gridViewBtn").classList.add("active");
    document.getElementById("listViewBtn").classList.remove("active");
    document.getElementById("productsGrid").classList.add("grid-view");
    document.getElementById("productsGrid").classList.remove("list-view");
  });
}

const form = document.getElementById("filterForm_y"); 

function loadResults() {
  const formData = new FormData(form);
  const params = new URLSearchParams(formData);
  
  const sortEl = document.getElementById("sortSelect");
  if (sortEl) params.set("sort", sortEl.value);
  
  window.location.search = params.toString();
}


if (form) form.addEventListener("change", loadResults);

const sortSelect = document.getElementById("sortSelect");
if (sortSelect) {
  sortSelect.addEventListener("change", function () {
    const params = new URLSearchParams(window.location.search);
    
    params.set("sort", this.value);
    
    window.location.search = params.toString();
  });
}
const searchBtn = document.getElementById("searchBtn");
if (searchBtn) {
  searchBtn.addEventListener("click", function () {
    const search = document.getElementById("searchInput").value;
    const params = new URLSearchParams(window.location.search);
    params.set("searchInput", search);
    window.location.search = params.toString();
  });
}
const formx = document.getElementById("filterForm_x");


function loadResults_x() {
  const formData = new FormData(formx);
  const params = new URLSearchParams(formData);
  
  const sortEl = document.getElementById("sortSelect");
  if (sortEl) params.set("sort", sortEl.value);
  window.location.search = params.toString();
}



if (formx) formx.addEventListener("change", loadResults_x);

const sortSelectX = document.getElementById("sortSelect_x");
if (sortSelectX) {
  sortSelectX.addEventListener("change", function () {
    const params = new URLSearchParams(window.location.search);
    params.set("sort", this.value);
    history.pushState(null, "", "?" + params.toString());
    fetch("template/paginationx.php?" + params.toString())
      .then((response) => response.text())
      .then((html) => {
        document.getElementById("loading").innerHTML = html;
        restoreView();
      });
  });
}

const searchBtnX = document.getElementById("searchBtn_x");
if (searchBtnX) {
  searchBtnX.addEventListener("click", function () {
    const search = document.getElementById("searchInput").value;
    const params = new URLSearchParams(window.location.search);
    params.set("searchInput", search);
    window.location.search = params.toString();
  });
}

const clearFilterss = document.getElementById("clearFilterss");
if (clearFilterss) {
  clearFilterss.addEventListener("click", function () {
    window.location.href = "licee_general.php";
  });
}


const searchInput = document.getElementById("searchInput");
if (searchInput) {
  searchInput.addEventListener("keydown", function (e) {
    if (e.key === "Enter") {
      // trigger whichever search button exists on this page
      const btn =
        document.getElementById("searchBtn") ||
        document.getElementById("searchBtn_x");
      if (btn) btn.click();
    }
  });
}

const clearFilters = document.getElementById("clearFilters");
if (clearFilters) {
  clearFilters.addEventListener("click", function () {
    window.location.href = "licee_specializari.php";
  });
}


function getSchoolNameFor(elem) {
  const card = elem.closest(".product-card");
  const t = card && card.querySelector(".card-title");
  return t ? t.textContent.trim() : null;
}

function saveCompareName(id, elem) {
  const name = getSchoolNameFor(elem);
  if (!name) return;
  let names = {};
  try { names = JSON.parse(localStorage.getItem("compareNames") || "{}"); }
  catch (e) { names = {}; }
  names[id] = name;
  localStorage.setItem("compareNames", JSON.stringify(names));
}

function removeCompareName(id) {
  let names = {};
  try { names = JSON.parse(localStorage.getItem("compareNames") || "{}"); }
  catch (e) { names = {}; }
  delete names[id];
  localStorage.setItem("compareNames", JSON.stringify(names));
}

function checkNr(id) {
  const compare_list = localStorage.getItem('compareIds');
  document.cookie = "compareIdsx=" + encodeURIComponent(compare_list);
  const elem = document.getElementById(id);
  if (!elem) return;

  if (elem.classList.contains("green")) {
    elem.classList.remove("green");
    elem.classList.add("red");
    let saved = JSON.parse(localStorage.getItem("compareIds") || "[]");
    saved = saved.filter((item) => item !== id);
    localStorage.setItem("compareIds", JSON.stringify(saved));
    removeCompareName(id);
    document.querySelectorAll(".compare-bar").forEach((el) => {
      el.style.display = "none";
    });
  } else if (
    elem.classList.contains("red") &&
    JSON.parse(localStorage.getItem("compareIds") || "[]").length < 5
  ) {
    elem.classList.remove("red");
    elem.classList.add("green");
    let saved = JSON.parse(localStorage.getItem("compareIds") || "[]");
    if (!saved.includes(id)) saved.push(id);
    localStorage.setItem("compareIds", JSON.stringify(saved));
    saveCompareName(id, elem);
  } else {
    document.querySelectorAll(".compare-bar").forEach((el) => {
      el.style.display = "block";
    });
    alert("Poți selecta maxim 5 licee pentru comparare.");
  }

  if (JSON.parse(localStorage.getItem("compareIds") || "[]").length >= 2) {
    document.querySelectorAll(".compare-bar").forEach((el) => {
      el.style.display = "block";
    });
  }
  if (JSON.parse(localStorage.getItem("compareIds") || "[]").length == 1) {
    document.querySelectorAll(".compare-bar").forEach((el) => {
      el.style.display = "none";
    });
  }
}

document.addEventListener("DOMContentLoaded", function () {
  const saved = JSON.parse(localStorage.getItem("compareIds") || "[]");
  saved.forEach((id) => {
    const elem = document.getElementById(id);

    if (elem) {
      elem.classList.remove("red");
      elem.classList.add("green");
    }
  });
});

function checkNrLista_x(id) {
    var elem  = document.getElementById(id),
        value = elem.value+"_lista";
    var list_id= elem.id.match(/\d/g);
    const color = elem.style.backgroundColor;
if (elem.classList.contains("green")) {
    elem.classList.remove("green");
    elem.classList.add("red");
fetch("plugin/remove_list.php?id="+elem.id.replace('_lista', '')+"&name=general", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `name=${encodeURIComponent(id)}`
  });

}else if (elem.classList.contains("red")) { 
    elem.classList.remove("red");
    elem.classList.add("green");
fetch("plugin/add_list.php?id="+elem.id.replace('_lista', '')+"&name=general", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `name=${encodeURIComponent(id)}`
  });
}

}


function checkNrLista_y(id) {
    var elem  = document.getElementById(id),
        value = elem.value+"_lista";
     var list_id= elem.id.match(/\d/g);
       
     const color = elem.style.backgroundColor;

if (elem.classList.contains("green")) {
    elem.classList.remove("green");
    elem.classList.add("red");
fetch("plugin/remove_list.php?id="+elem.id.replace('_lista', '')+"&name=specializari", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `name=${encodeURIComponent(id)}`
  });

}else if (elem.classList.contains("red")) { 
    elem.classList.remove("red");
    elem.classList.add("green");
fetch("plugin/add_list.php?id="+elem.id.replace('_lista', '')+"&name=specializari", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `name=${encodeURIComponent(id)}`
  });
}

}

(function () {
  const mobileFilterBtn = document.getElementById("mobileFilterBtn");
  const filterDrawer = document.getElementById("filterDrawer");
  const drawerBackdrop = document.getElementById("drawerBackdrop");
  const drawerClose = document.getElementById("drawerClose");
  const drawerPanel = document.getElementById("drawerPanel");

  if (!mobileFilterBtn || !filterDrawer) return;

  function populateDrawer() {
    const sidebar = document.getElementById("filterSidebar");
    if (!sidebar) return;
    if (drawerPanel.querySelector(".filter-sidebar")) return;
    const clone = sidebar.cloneNode(true);
    clone.style.display = "flex";
    clone.style.width = "100%";
    drawerPanel.appendChild(clone);

    clone.querySelectorAll("input[type='checkbox']").forEach((cb) => {
      cb.addEventListener("change", () => {
        const name = cb.getAttribute("name");
        const value = cb.value;
        const real = sidebar.querySelector(
          `input[name="${name}"][value="${value}"]`,
        );
        if (real) {
          real.checked = cb.checked;
          const event = new Event("change", { bubbles: true });
          real.dispatchEvent(event);
        }
      });
    });

    clone.querySelectorAll(".filter-toggle").forEach((toggle) => {
      toggle.addEventListener("click", function () {
        const options =
          this.closest(".filter-card").querySelector(".filter-options");
        options.classList.toggle("hidden");
        this.textContent = options.classList.contains("hidden") ? "▸" : "▾";
      });
    });
  }

  function openDrawer() {
    populateDrawer();
    filterDrawer.classList.add("open");
    document.body.style.overflow = "hidden";
  }

  function closeDrawer() {
    filterDrawer.classList.remove("open");
    document.body.style.overflow = "";
  }

  mobileFilterBtn.addEventListener("click", openDrawer);
  if (drawerBackdrop) drawerBackdrop.addEventListener("click", closeDrawer);
  if (drawerClose) drawerClose.addEventListener("click", closeDrawer);
})();

document.addEventListener("DOMContentLoaded", function () {
  restoreView();

  document.querySelectorAll(".filter-toggle").forEach((toggle) => {
    toggle.addEventListener("click", function () {
      const options =
        this.closest(".filter-card").querySelector(".filter-options");
      options.classList.toggle("hidden");
      this.textContent = options.classList.contains("hidden") ? "▸" : "▾";
    });
  });

  document.querySelectorAll("#subjects option").forEach((option) => {
    option.addEventListener("mousedown", function (e) {
      e.preventDefault();
      this.selected = !this.selected;
    });
  });
});
  function setImg(thumb, src) {
    document.getElementById('mainImg').src = src;
    document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
  }

  const toast = document.getElementById('toast');
  function showToast(msg) {
    toast.textContent =msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
  }

  let compareActive = false;
  function toggleCompare() {
    compareActive = !compareActive;
    const btn = document.getElementById('compareBtn');
    if (compareActive) { btn.textContent = 'Adăugat la comparație'; btn.classList.add('active'); showToast('Adăugat la comparație!'); }
    else { btn.innerHTML = 'Compară'; btn.classList.remove('active'); }
  }

  function switchTab(tab, btn) {
    document.getElementById('tab-medii').style.display   = tab === 'medii'   ? 'block' : 'none';
    document.getElementById('tab-pozitii').style.display = tab === 'pozitii' ? 'block' : 'none';
    document.querySelectorAll('.adm-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  }

  function openMap() {
    window.open('https://maps.google.com/?q=Colegiul+National+Gheorghe+Lazar+Bucuresti', '_blank');
  }

  	const testBtn = document.getElementById("test");
	testBtn.addEventListener("click", function() {
  		window.location.href = "quiz.php";
	});

    function calculeaza() {
    const scoruri = {
      real: 0,
      uman: 0,
      tehnic: 0,
      militar: 0,
      teologic: 0,
      artistic: 0,
      servicii: 0,
      sport: 0,
      pedagogic: 0,
      resurse: 0
    };

    const raspunsuri = document.querySelectorAll("input[type='radio']:checked");

    if (raspunsuri.length < 5) {
      alert("Răspunde la toate cele 5 întrebări.");
      return;
    }

    for (let i = 0; i < raspunsuri.length; i++) {
      scoruri[raspunsuri[i].value]++;
    }

    let rezultat = "real";
    let scorMaxim = scoruri.real;

    for (let profil in scoruri) {
      if (scoruri[profil] > scorMaxim) {
        scorMaxim = scoruri[profil];
        rezultat = profil;
      }
    }

    window.location.href = "rezultat.php?profil=" + rezultat;
  }
