<?php
// Output buffering start
include_once 'plugin/function.php';
//   ob_start("sanitize_output"); // Output buffering start
ob_start(); 

 
$pageTitle1 = 'High school';
/*
include 'plugin/init.php';
global $con;
global $pageTitle1;
global $stmt1;
global $rows;
*/
unset($_SESSION['pagename']);
unset($_SESSION['stylecss']);
$_SESSION['stylecss']  = 'style.css';
$_SESSION['pagename']  = 'page-home';
include 'template/header.php';
?>

  <section class="hero">
    <div>
      <h1>Găsește liceul potrivit pentru tine.</h1>
      <p>Compară licee, vezi mediile și formează-ți lista!</p>
      <button class="cta" onclick="window.location.href='licee_general.php'">Vezi licee</button>
    </div>
  </section>

  <section class="profile-section">
    <div class="profile-left">

    <h2>Ce profil ți se potrivește?</h2>

    <p>
      Descoperă specializările disponibile în liceele din București
      și vezi mai ușor ce ți se potrivește. Acestea sunt printre cele mai populare:
    </p>

    <div class="profile-tags">
      <div class="profile-tag">
        <span>Real</span>
        <small>matematică • informatică • științe</small>
      </div>

      <div class="profile-tag">
        <span>Uman</span>
        <small>limbi • istorie • geografie</small>
      </div>

      <div class="profile-tag">
        <span>Economic</span>
        <small>business • contabilitate</small>
      </div>

      <div class="profile-tag">
        <span>Vocațional</span>
        <small>arte • sport • gastronomie</small>
      </div>
    </div>

    <button class="profile-btn" onclick="window.location.href='specializari.php'">
      Explorează profilurile 
    </button>
  </div>

  <div class="stats">
    <div class="stat-card">

      <div>
        <div class="stat-number">70+</div>
        <div class="stat-text">licee din București</div>
      </div>
    </div>

    <div class="stat-card">

      <div>
        <div class="stat-number">450+</div>
        <div class="stat-text">specializări disponibile</div>
      </div>
    </div>

    <div class="stat-card">

      <div>
        <div class="stat-number">200+</div>
        <div class="stat-text">clase pentru viitorii elevi</div>
      </div>
    </div>
  </div>
</section>


  <section class="events">
    <h2>EVENIMENTE ȘI NOUTĂȚI</h2>

    <div class="cards">
      <div class="card">
        Ziua porților deschise la Colegiul Național „Grigore Moisil”, 16 iunie 2025.
      </div>

      <div class="card">
        În curând pe Ǝliceu: va fi posibilă realizarea listei ideale utilizatorilor cu AI.
      </div>

      <div class="card">
        Calendarul admiterii 2025 va fi actualizat constant pentru elevii de clasa a VIII-a.
      </div>

      <div class="card">
        Platforma va include recomandări personalizate în funcție de medie, profil și preferințe.
      </div>
    </div>
  </section>

<?php
include 'template/footer.php';
  ob_end_flush();
?>