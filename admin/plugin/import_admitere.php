<?php
header('Content-Type: application/json; charset=utf-8');

global $con;
include 'plugin/init.php';   

function respond($d, $c = 200) { http_response_code($c); echo json_encode($d); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['rows']) || !is_array($input['rows'])) {
    respond(['ok' => false, 'error' => 'Date invalide.'], 422);
}
$rows  = $input['rows'];
$table = DB_PREFIX . 'admitere_2026';

$num = function ($v) {
    if ($v === '' || $v === null) return null;
    $v = str_replace(',', '.', (string) $v);
    return is_numeric($v) ? $v + 0 : null;
};
$intOrNull = function ($v) {
    if ($v === '' || $v === null) return null;
    return is_numeric($v) ? (int) $v : null;
};
$str = function ($v, $max) { return mb_substr(trim((string) ($v ?? '')), 0, $max); };

$sql = "INSERT INTO `$table`
 (nr,tip_scoala,nume_scoala,filiera,profil,specializare,mentiune,clase,
  total_locuri,locuri_romi,locuri_ces,media_ultimului_admis,codificare,observatii,specializare_complet)
 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
 ON DUPLICATE KEY UPDATE
   nr=VALUES(nr), tip_scoala=VALUES(tip_scoala), nume_scoala=VALUES(nume_scoala),
   filiera=VALUES(filiera), profil=VALUES(profil), specializare=VALUES(specializare),
   mentiune=VALUES(mentiune), clase=VALUES(clase), total_locuri=VALUES(total_locuri),
   locuri_romi=VALUES(locuri_romi), locuri_ces=VALUES(locuri_ces),
   media_ultimului_admis=VALUES(media_ultimului_admis), observatii=VALUES(observatii),
   specializare_complet=VALUES(specializare_complet)";

try {
    $con->beginTransaction();
    $st = $con->prepare($sql);
    $processed = 0; $skipped = 0;

    foreach ($rows as $r) {
        $cod = $str($r['codificare'] ?? '', 10);
        if ($cod === '') { $skipped++; continue; }  
        $st->execute([
            $intOrNull($r['nr'] ?? null),
            $str($r['tip_scoala'] ?? '', 120),
            $str($r['nume_scoala'] ?? '', 160),
            $str($r['filiera'] ?? '', 80),
            $str($r['profil'] ?? '', 80),
            $str($r['specializare'] ?? '', 160),
            $str($r['mentiune'] ?? '', 160),
            $num($r['clase'] ?? null),
            $intOrNull($r['total_locuri'] ?? null),
            $intOrNull($r['locuri_romi'] ?? null),
            $intOrNull($r['locuri_ces'] ?? null),
            $num($r['media_ultimului_admis'] ?? null),
            $cod,
            $str($r['observatii'] ?? '', 255),
            $str($r['specializare_complet'] ?? '', 255),
        ]);
        $processed++;
    }
    $con->commit();
    respond(['ok' => true, 'processed' => $processed, 'skipped' => $skipped]);
} catch (Exception $e) {
    if ($con->inTransaction()) $con->rollBack();
    respond(['ok' => false, 'error' => $e->getMessage()], 500);
}
