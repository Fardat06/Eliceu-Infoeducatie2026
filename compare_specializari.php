<?php
include 'plugin/function.php';
//   ob_start("sanitize_output"); // Output buffering start
ob_start(); // Output buffering start
$pageTitle1 = 'High school';
include 'plugin/init.php';
global $con;
global $pageTitle1;
global $stmt1;
global $rows;
global $where;
global $and;
unset($_SESSION['pagename']);
unset($_SESSION['stylecss']);
$_SESSION['pagename'] = '';
$_SESSION['stylecss'] = 'compare_mobile.css';


$stmt = "SELECT h1.* , h2.u_medie_2025, h2.p_medie_2025 , h3.photo, h3.no_clase , h3.total_no_student, h3.web_page  , h3.id_numa_liceu , h4.locuri_2025
 FROM " . DB_PREFIX . "liceu h1
INNER JOIN " . DB_PREFIX . "medie h2 ON h1.id = h2.id_medie
INNER JOIN " . DB_PREFIX . "numa_liceu  h3 ON h1.name = h3.name
INNER JOIN " . DB_PREFIX . "locuri  h4 ON h1.id = h4.id_locuri

WHERE h1.stopx = 0";

unset($_SESSION['pagename']);
unset($_SESSION['stylecss']);
$_SESSION['stylecss']  = 'compare.css';
$_SESSION['pagename']  = '';

include 'template/header.php';
if (isset($_GET['ids'])) {
    $raw_ids = $_GET['ids'];
    $ids_array = explode(',', $raw_ids);
    $ids_array = array_map('intval', $ids_array);


    $profilPlaceholders = [];

    foreach ($ids_array as $index => $id) {
        $key = "'" . $id . "'";

        $profilPlaceholders[] = $key;

        $params[$key] = $id;
    }

    $stmt .= " AND id IN (" . implode(",", $profilPlaceholders) . ")";

    $stmt2 = $con->prepare($stmt);
    $stmt2->execute();
    $row = $stmt2->fetchAll();
    $rows = $stmt2->rowCount();




} else {
    echo "No IDs found in the URL.";
}




//echo print_r($row);

?>
<!-- Mobile overlay stylesheet (shared with compare_general.php). -->
<link rel="stylesheet" href="src/css/compare_mobile.css">

<div class="overlay" id="overlay"></div>

<div class="toast" id="toast"></div>

<div class="page-wrapper">
    <div class="breadcrumb">
        <a href="index.php">Acasă</a>
        <span>›</span>
        <a href="javascript:history.back()">Toate Liceele</a>
        <span>›</span>
        <span>Comparare</span>
    </div>

    <div class="compare-banner">
        <div>
            <h1>Compară Licee</h1>
            <p>Adaugă până la 5 licee și compară criteriile importante pentru tine.</p>
        </div>
        <a href="javascript:history.back()" class="back-link">← Înapoi la licee</a>

    </div>

    <div class="compare-container">


        <!-- TABLE -->
        <div id="compareTableWrap" class="compare-table-wrap">
            <table class="compare-table">
                <thead>
                    <tr class="school-header">
                        <td>Licee comparate</td>
                        <?php for ($i = 0; $i < $rows; $i++) { ?>
                            <td>
                                <div class="school-card-head">
                                    
                                
                                <img class="school-cover" src="src/images/liceu/<?=  $row[$i][18] ?>"
                                        alt="<?= $row[$i][1] . ' ' . $row[$i][2] ?>" loading="lazy"
                                        onerror="this.style.display='none'">
                                    <div class="school-head-name"><?= $row[$i][1] . ' ' . $row[$i][2] ?></div>


                                </div>
                            </td>

                            <?php if ($i+1 == $rows ) {
                                echo '</tr></thead><tbody><tr class="section-divider"><td colspan="6">Informații Generale</td></tr><tr><td class="row-label">Specializare</td>';
                            }
                        }
                        for ($i = 0; $i < $rows; $i++) {
                            $all_profil = allprofil($row[$i][2]);
                            ?>
                            <td class="data-cell ">
                                <div class="profil-medie-list">
                                    <div class="pm-item">
                                        <span class="pm-name"><?= $row[$i][4].' '.$row[$i][6].' '. $row[$i][7] ?></span>
                                    </div>
                                </div>


            </div>
            </td>
            <?php if ($i+1 == $rows ) {
                echo '</tr><tr><td class="row-label">Program</td>';
            }
                        }

                        for ($i = 0; $i < $rows; $i++) {?>
            <td class="data-cell ">

                <div class="profil-medie-list">


                   
                        <div class="pm-item">
                            <span class="pm-name">Clasă 9</span>
                            <span class="pm-val"><?= $row[$i][11] ?></span>
                        </div>
                        <div class="pm-item">
                            <span class="pm-name">Clasă 10</span>
                            <span class="pm-val"><?= $row[$i][12] ?></span>
                        </div>
                        <div class="pm-item">
                            <span class="pm-name">Clasă 11 </span>
                            <span class="pm-val"><?= $row[$i][13] ?></span>
                        </div>
                        <div class="pm-item">
                            <span class="pm-name">Clasă 12 </span>
                            <span class="pm-val"><?= $row[$i][14] ?></span>
                        </div>

                   
                </div>

            </td>
            <!--<td class="data-cell" style="color:var(--text-muted);font-size:20px;">â€”</td>-->
            <?php if ($i+1 == $rows ) {
                echo '</tr><tr><td class="row-label">Sector</td>';
            }
                        } ?>

        <?php for ($i = 0; $i < $rows; $i++) { ?>
            <td class="data-cell "><?php echo $row[$i][10] ?></td>
            <?php if ($i+1 == $rows ) {
                echo '</tr></tr><tr class="section-divider"><td colspan="6">Admitere</td></tr><tr><td class="row-label">Medie admitere</td>';
            }
        }




        for ($i = 0; $i < $rows; $i++) { ?>
            <td class="data-cell ">
                <span class="medie-val"><?= $row[$i][16] .' - ' . $row[$i][17] ?> </span>
            </td>
            <?php if ($i+1 == $rows ) {
                echo '</tr><tr class="section-divider"><td colspan="6">Dimensiune</td></tr><tr><td class="row-label">Nr. Elevi</td>';
            }
        }




        for ($i = 0; $i < $rows; $i++) { ?>
            <td class="data-cell"><?php echo $row[$i][20] /$row[$i][19]  ?></td>
            <!--<td class="data-cell" style="color:var(--text-muted);font-size:20px;">â€”</td>-->
            <?php if ($i+1 == $rows ) {
                echo '</tr><tr><td class="row-label">Nr. Elevi Romi</td>';
            }
        }

        for ($i = 0; $i < $rows; $i++) { ?>
            <td class="data-cell">1</td>
            <!--<td class="data-cell" style="color:var(--text-muted);font-size:20px;">â€”</td>-->
            <?php if ($i+1 == $rows ) {
                echo '</tr><tr><td class="row-label">Nr. Elevi CES</td>';
            }
        }

        for ($i = 0; $i < $rows; $i++) { ?>
            <td class="data-cell">1</td>
            <!--<td class="data-cell" style="color:var(--text-muted);font-size:20px;">â€”</td>-->
            <?php if ($i+1 == $rows ) {
                echo '</tr><tr class="section-divider"><td colspan="6">Contact</td></tr><tr><td class="row-label">Adresă</td>';
            }
        }


   
     

        for ($i = 0; $i < $rows; $i++) { ?>
        <td class="data-cell" style="font-size:12px"><?= $row[$i][9] ?></td>
        <!--<<td class="data-cell">â€”</td>-->
        <?php if ($i+1 == $rows ) {
            echo '</tr><tr><td class="row-label">Website</td>';
        }
        }
        for ($i = 0; $i < $rows; $i++) { ?>
        <td class="data-cell"><a href="<?= $row[$i][21] ?>" target="_blank"
                style="color:var(--purple);font-size:12px;font-weight:700"><?= $row[$i][2] ?></a></td>
        <!--<<td class="data-cell">â€”</td>-->
        <?php if ($i+1 == $rows ) {
            echo '</tr><tr class="cta-row">
                        <td style="background:#faf8fc;border-right:1px solid var(--border);padding:20px;font-size:12px;font-weight:700;color:var(--text-muted)"> Vezi pagina completă</td>';
        }
        }


        for ($i = 0; $i < $rows; $i++) { ?>
        <td><a target="_blank" href="liceu_page.php?id=<?= $row[$i][0] ?>" class="view-btn">Vezi liceu</a></td>
        <!--<<td class="data-cell">â€”</td>-->
        <?php if ($i+1 == $rows ) {
            echo '</tr>';
        }
        } ?>




    </tbody>
    </table>
</div>
</div>


</div>



<?php


include 'template/footer.php'; ?>