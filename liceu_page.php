<?php
include 'plugin/function.php';
ob_start();
session_start();
$pageTitle1 = 'High school';
include 'plugin/init.php';
global $con;
global $pageTitle1;
global $stmt1;
global $rows;

$_SESSION['stylecss'] = 'liceu.css';

include 'template/header.php';
$id = $_GET['id'];

        $stmt = $con->prepare("SELECT * FROM " . DB_PREFIX . "numa_liceu  WHERE id_numa_liceu = ? AND stopx = 0");
        $stmt->execute(array($id));
        $rows = $stmt->fetch();
        $s_name = $rows['name'];




          $stmt1 = $con->prepare("SELECT * FROM " . DB_PREFIX . "liceu  WHERE name = ? AND stopx = 0");
          $stmt1->execute(array($s_name));
          $row1 = $stmt1->fetchAll();

          $stmt2 = $con->prepare("SELECT * FROM " . DB_PREFIX . "medie  WHERE name = ? AND stopx = 0");
          $stmt2->execute(array($s_name));
          $row2 = $stmt2->fetchAll();

          $stmt3 = $con->prepare("SELECT * FROM " . DB_PREFIX . "poztion  WHERE name = ? AND stopx = 0");
          $stmt3->execute(array($s_name));
          $row3 = $stmt3->fetchAll();

          $stmt4 = $con->prepare("SELECT DISTINCT profil FROM " . DB_PREFIX . "liceu  WHERE name = ? AND stopx = 0");
          $stmt4->execute(array($s_name));
          $row4 = $stmt4->fetchAll();

function stars_html($n) {
    $n = (int) round($n);
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span style="color:' . ($i <= $n ? '#f5b301' : '#dcdcdc') . '">&#9733;</span>';
    }
    return $out;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$reviewError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_review'])) {
    if (!isset($_SESSION['ID'])) {
        $reviewError = 'Trebuie să fii autentificat pentru a lăsa o recenzie.';
    } elseif (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $reviewError = 'Sesiune expirată. Reîncarcă pagina și încearcă din nou.';
    } else {
        $rating  = (int) ($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($rating < 1 || $rating > 5) {
            $reviewError = 'Alege un rating între 1 și 5 stele.';
        } elseif ($comment === '') {
            $reviewError = 'Scrie un comentariu.';
        } elseif (mb_strlen($comment) > 1000) {
            $reviewError = 'Comentariul este prea lung (maxim 1000 de caractere).';
        } else {
            // One review per user per school — reject a second submission.
            $chk = $con->prepare("SELECT 1 FROM " . DB_PREFIX . "review
                                  WHERE user_id = ? AND id_numa_liceu = ? LIMIT 1");
            $chk->execute([$_SESSION['ID'], $id]);
            if ($chk->fetchColumn()) {
                $reviewError = 'Ai lăsat deja o recenzie pentru acest liceu.';
            } else {
                $ins = $con->prepare("INSERT INTO " . DB_PREFIX . "review
                                          (id_numa_liceu, user_id, rating, comment)
                                      VALUES (?, ?, ?, ?)");
                $ins->execute([$id, $_SESSION['ID'], $rating, $comment]);
                // Post/Redirect/Get: avoids a duplicate submit on page refresh
                header('Location: liceu_page.php?id=' . urlencode($id) . '#reviews');
                exit;
            }
        }
    }
}

$revStmt = $con->prepare("SELECT r.rating, r.comment, r.created_at, u.first_name, u.username
                          FROM " . DB_PREFIX . "review r
                          JOIN " . DB_PREFIX . "user_details u ON r.user_id = u.id
                          WHERE r.id_numa_liceu = ? AND r.is_active = 1
                          ORDER BY r.created_at DESC");
$revStmt->execute([$id]);
$reviews  = $revStmt->fetchAll();
$revCount = count($reviews);
$revAvg   = $revCount ? array_sum(array_column($reviews, 'rating')) / $revCount : 0;

$userHasReviewed = false;
if (isset($_SESSION['ID'])) {
    $chk2 = $con->prepare("SELECT 1 FROM " . DB_PREFIX . "review
                           WHERE user_id = ? AND id_numa_liceu = ? LIMIT 1");
    $chk2->execute([$_SESSION['ID'], $id]);
    $userHasReviewed = (bool) $chk2->fetchColumn();
}

?>
<link rel="stylesheet" href="src/css/liceu_page_mobile.css">

<div class="overlay" id="overlay"></div>

<div class="toast" id="toast">Adăugat la favorite!</div>

<div class="page-wrapper">

  <div class="breadcrumb">
    <a href="index.php">Acasă</a>
    <span>›</span>
        <a href="javascript:history.back()">Înapoi</a>
    <span>›</span>
    <span><?php echo $rows['tip']. ' '.$rows['name']; ?></span>
  </div>

  <div class="product-main">

    <div class="product-top">

      <div class="gallery-col fade-in">
        <div class="main-image-wrap">
          <img id="mainImg"
               src="src/images/liceu/<?php echo $rows['photo'] ?>"
               alt="<?php echo $rows['tip']. ' '.$rows['name']; ?>">
          <div class="main-image-overlay"></div>
        </div>
        <div class="thumb-row">
          <div class="thumb active" onclick="setImg(this,'src/images/liceu/<?php echo $rows['photo'] ?>')">
            <img src="src/images/liceu/<?php echo $rows['photo'] ?>" alt="">
          </div>
          <div class="thumb" onclick="setImg(this,'src/images/liceu/<?php echo $rows['name'].'2.jpg' ?>')">
            <img src="src/images/liceu/<?php echo $rows['name']."2.jpg" ?>" alt="">
          </div>
        </div>
      </div>

      <div class="info-col fade-in fade-in-1">
        <h1 class="school-name"><?php echo $rows['tip']?><br><?php echo $rows['name']?></h1>

        <p class="school-desc"><?= $rows['long_description'] ?>
        </p>

        <div class="quick-stats">
          <div class="stat-box">
            <div class="stat-value"><?php echo  $rows['total_no_student'] ?></div>
            <div class="stat-label">Elevi</div>
          </div>
          <div class="stat-box">
            <div class="stat-value"><?php echo  countspecializare($rows['name']);?></div>
            <div class="stat-label">Specializare</div>
          </div>
          <div class="stat-box">
            <div class="stat-value"><?php echo  media($rows['name']) ?></div>
            <div class="stat-label">Medie min. 2025</div>
          </div>
        </div>


      </div>
    </div>

    <div class="bottom-grid">

      <div class="section-block fade-in fade-in-2">
        <div class="section-header">
          <h2>Specificații</h2>
        </div>
        <div class="section-body" style="padding:0;">
          <table class="specs-table">
            <tr><td>Denumire oficială</td><td><?php echo $rows['tip']. ' '.$rows['name']; ?></td></tr>
            <tr><td>Locație</td><td><?php echo $rows['address']; ?></td></tr>
            <tr><td>Sector</td><td><?php echo $rows['zone']; ?></td></tr>
            <tr><td>Program</td><td>

                    <?php
                    $program_clasa = program_clasa($rows['name']);
                    foreach ($program_clasa as $program) { ?>
                        <div class="pm-item">
                            <span class="pm-name">Clasă 9</span>
                            <span class="pm-val" style="padding-left: 45px;"><?=  '&nbsp;'.$program['program_9'] ?></span>
                        </div>
                        <div class="pm-item">
                            <span class="pm-name">Clasă 10</span>
                            <span class="pm-val" style="padding-left: 45px;"><?= $program['program_10'] ?></span>
                        </div>
                        <div class="pm-item">
                            <span class="pm-name">Clasă 11</span>
                            <span class="pm-val" style="padding-left: 45px;"><?= $program['program_11'] ?></span>
                        </div>
                        <div class="pm-item">
                            <span class="pm-name">Clasă 12</span>
                            <span class="pm-val" style="padding-left: 45px;"><?= $program['program_12'] ?></span>
                        </div>

                    <?php } ?>


            </td></tr>
            <tr><td>Tip liceu</td><td><?php echo $rows['tip']; ?></td></tr>
            <tr><td>Profiluri</td><td>
              <?php foreach($row4 as $row4) { ?>
                <span class="spec-badge"><?php echo $row4['profil'].' '; ?></span>
              <?php } ?>


            </td></tr>
            <tr><td>Număr elevi</td><td><?php echo $rows['total_no_student']?></td></tr>
            <tr><td>Site oficial</td><td><a class="spec-link" href="<?php echo $rows['web_page']; ?>" target="_blank"><?php echo $rows['name']; ?> ↗</a></td></tr>
          </table>
        </div>
      </div>

      <div class="section-block fade-in fade-in-3">
        <div class="section-header">
          <h2>Istoricul Admiterii</h2>
        </div>
        <div class="section-body" style="display:flex;flex-direction:column;gap:24px;">

          <div style="display:flex;gap:8px;border-bottom:2px solid var(--border);padding-bottom:0;">
            <button class="adm-tab active" onclick="switchTab('medii',this)">Arhivă medii</button>
            <button class="adm-tab" onclick="switchTab('pozitii',this)">Arhivă poziții</button>
          </div>

          <div id="tab-medii">
            <p style="font-size:13px;font-weight:600;color:var(--text-muted);margin-bottom:14px;">Ultima medie acceptată pe specializare și an</p>
            <div class="admitere-table-wrap">
              <table class="admitere-table arch-table">
                <thead>
                  <tr>
                    <th style="text-align:left;">Specializare</th>
                    <th>Bilingv</th>
                    <th>2025</th><th>2024</th><th>2023</th><th>2022</th><th>2021</th><th>2020</th>
                  </tr>
                </thead>
                <tbody>
                <?php

                foreach($row2 as $row2){?>



                <tr>
                    <td class="spec-name"><?= $row2['specializare'];?></td>
                    <td class="bil-cell"><?= $row2['bilingv'];?></td>
                    <td data-year="2025"><span class="medie-badge high"><?php echo $row2['u_medie_2025'];?></span></td>
                    <td data-year="2024"><span class="medie-badge high"><?= $row2['u_medie_2024'];?></span></td>
                    <td data-year="2023"><span class="medie-badge high"><?= $row2['u_medie_2023'];?></span></td>
                    <td data-year="2022"><span class="medie-badge high"><?= $row2['u_medie_2022'];?></span></td>
                    <td data-year="2021"><span class="medie-badge high"><?= $row2['u_medie_2021'];?></span></td>
                    <td data-year="2020"><span class="medie-badge high"><?= $row2['u_medie_2020'];?></span></td>
                  </tr>
<?php
}
 ?>
                </tbody>
              </table>
            </div>

          </div>

          <div id="tab-pozitii" style="display:none;">
            <p style="font-size:13px;font-weight:600;color:var(--text-muted);margin-bottom:14px;">Locul în ierarhia din București al ultimului elev admis pe fiecare specializare</p>
            <div class="admitere-table-wrap">
              <table class="admitere-table arch-table">
                <thead>
                  <tr>
                    <th style="text-align:left;">Specializare</th>
                    <th>Bilingv</th>
                    <th>2025</th><th>2024</th><th>2023</th><th>2022</th><th>2021</th><th>2020</th>
                  </tr>
                </thead>
                <tbody>
                 <?php

                foreach($row3 as $row2){?>
                  <tr>
                    <td class="spec-name"><?= $row2['specializare'];?></td>
                    <td class="bil-cell"><?= $row2['bilingv'];?></td>
                    <td data-year="2025"><span class="medie-badge high"><?php echo $row2['u_pozition_2025'];?></span></td>
                    <td data-year="2024"><span class="medie-badge high"><?= $row2['u_pozition_2024'];?></span></td>
                    <td data-year="2023"><span class="medie-badge high"><?= $row2['u_pozition_2023'];?></span></td>
                    <td data-year="2022"><span class="medie-badge high"><?= $row2['u_pozition_2022'];?></span></td>
                    <td data-year="2021"><span class="medie-badge high"><?= $row2['u_pozition_2021'];?></span></td>
                    <td data-year="2020"><span class="medie-badge high"><?= $row2['u_pozition_2020'];?></span></td>
                  </tr>
<?php
}
 ?>
             </tbody>
              </table>
            </div>
            <p style="font-size:12px;color:var(--text-muted);margin-top:12px;">* Pozițiile reprezintă locul în ierarhia generală a candidaților din București.</p>
          </div>

        </div>
      </div>

    </div>

    <div class="section-block fade-in" id="reviews" style="margin-top:24px;">
      <div class="section-header">
        <h2>Recenzii</h2>
      </div>
      <div class="section-body">

        <style>
          .rev-summary{display:flex;align-items:center;gap:14px;margin-bottom:20px;}
          .rev-avg{font-size:34px;font-weight:800;color:#2c3e50;line-height:1;}
          .rev-count{color:#777;font-size:14px;}
          .rev-form{background:#faf7fd;border:1px solid #ece3f5;border-radius:12px;padding:18px;margin-bottom:24px;}
          .rev-form textarea{width:100%;min-height:90px;border:1px solid #d9d2e3;border-radius:8px;padding:10px;font-family:inherit;font-size:14px;resize:vertical;}
          .rev-form .row{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:10px;flex-wrap:wrap;}
          .rev-form button{background:#9661b8;color:#fff;border:0;border-radius:8px;padding:10px 22px;font-weight:700;cursor:pointer;}
          .star-input{display:inline-flex;flex-direction:row-reverse;}
          .star-input input{display:none;}
          .star-input label{font-size:26px;color:#dcdcdc;cursor:pointer;padding:0 1px;transition:.1s;}
          .star-input label:hover,.star-input label:hover ~ label,.star-input input:checked ~ label{color:#f5b301;}
          .rev-list{display:flex;flex-direction:column;gap:14px;}
          .rev-item{border:1px solid #eee;border-radius:10px;padding:14px 16px;}
          .rev-item .head{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;}
          .rev-item .who{font-weight:700;color:#333;}
          .rev-item .date{color:#999;font-size:12px;}
          .rev-item .body{color:#444;font-size:14px;line-height:1.5;white-space:pre-wrap;margin-top:6px;}
          .rev-alert{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:14px;}
          .rev-alert.err{background:#fdecea;color:#c0392b;}
          .rev-login{background:#faf7fd;border:1px dashed #d9c9ea;border-radius:12px;padding:16px;text-align:center;color:#555;margin-bottom:24px;}
          .rev-login a{color:#9661b8;font-weight:700;}
        </style>

        <div class="rev-summary">
          <div class="rev-avg"><?= $revCount ? number_format($revAvg, 1) : '—' ?></div>
          <div>
            <div><?= stars_html($revAvg) ?></div>
            <div class="rev-count"><?= $revCount ?> <?= $revCount === 1 ? 'recenzie' : 'recenzii' ?></div>
          </div>
        </div>

        <?php if ($reviewError): ?>
          <div class="rev-alert err"><?= htmlspecialchars($reviewError) ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['ID']) && !$userHasReviewed): ?>
          <form class="rev-form" method="POST" action="liceu_page.php?id=<?= htmlspecialchars($id) ?>#reviews">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="add_review" value="1">
            <div class="star-input">
              <input type="radio" name="rating" id="s5" value="5"><label for="s5">&#9733;</label>
              <input type="radio" name="rating" id="s4" value="4"><label for="s4">&#9733;</label>
              <input type="radio" name="rating" id="s3" value="3"><label for="s3">&#9733;</label>
              <input type="radio" name="rating" id="s2" value="2"><label for="s2">&#9733;</label>
              <input type="radio" name="rating" id="s1" value="1"><label for="s1">&#9733;</label>
            </div>
            <textarea name="comment" maxlength="1000" placeholder="Scrie părerea ta despre acest liceu..."></textarea>
            <div class="row">
              <span style="font-size:12px;color:#999;">Recenzia ta este publică.</span>
              <button type="submit">Trimite recenzia</button>
            </div>
          </form>
        <?php elseif (!isset($_SESSION['ID'])): ?>
          <div class="rev-login">
            <a href="login.php">Autentifică-te</a> pentru a lăsa o recenzie.
          </div>
        <?php else: ?>
          <div class="rev-login">Ai lăsat deja o recenzie pentru acest liceu. Mulțumim!</div>
        <?php endif; ?>

        <div class="rev-list">
          <?php if (!$revCount): ?>
            <p style="color:#888;">Încă nu există recenzii. Fii primul care lasă o părere!</p>
          <?php else: foreach ($reviews as $rev): ?>
            <div class="rev-item">
              <div class="head">
                <span class="who"><?= htmlspecialchars($rev['first_name'] !== '' ? $rev['first_name'] : $rev['username']) ?></span>
                <span class="date"><?= htmlspecialchars(date('d.m.Y', strtotime($rev['created_at']))) ?></span>
              </div>
              <div><?= stars_html($rev['rating']) ?></div>
              <div class="body"><?= htmlspecialchars($rev['comment']) ?></div>
            </div>
          <?php endforeach; endif; ?>
        </div>

      </div>
    </div>

  </div><!-- /product-main -->

<?php
include 'template/footer.php'; ?>
