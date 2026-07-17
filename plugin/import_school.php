<?php
/*
 * import_school.php
 * Receives the rows parsed by the school-list PDF extractor (JSON: { rows: [ {…6 fields…} ] })
 * and upserts them into home_licee keyed on `nume_scoala`.
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
global $con;
include 'init.php';   // provides $con (PDO) and DB_PREFIX

function respond($d, $c = 200) { http_response_code($c); echo json_encode($d); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['rows']) || !is_array($input['rows'])) {
    respond(['ok' => false, 'error' => 'Date invalide.'], 422);
}
$rows  = $input['rows'];
$table = DB_PREFIX . 'licee';

// value cleaners
$intOrNull = function ($v) {
    if ($v === '' || $v === null) return null;
    return is_numeric($v) ? (int) $v : null;
};
$str = function ($v, $max) { return mb_substr(trim((string) ($v ?? '')), 0, $max); };

$sql = "INSERT INTO `$table`
 (nr, nume_scoala, adresa, telefon, puncte_reper, sector)
 VALUES (?,?,?,?,?,?)
 ON DUPLICATE KEY UPDATE
   nr=VALUES(nr), adresa=VALUES(adresa), telefon=VALUES(telefon),
   puncte_reper=VALUES(puncte_reper), sector=VALUES(sector)";

try {
    $con->beginTransaction();
    $st = $con->prepare($sql);
    $processed = 0; $skipped = 0;

    foreach ($rows as $r) {
        $nume = $str($r['nume_scoala'] ?? '', 200);
        if ($nume === '') { $skipped++; continue; }   // nume_scoala is the unique key
        $st->execute([
            $intOrNull($r['nr'] ?? null),
            $nume,
            $str($r['adresa'] ?? '', 255),
            $str($r['telefon'] ?? '', 100),
            $str($r['puncte_reper'] ?? '', 500),
            $intOrNull($r['sector'] ?? null),
        ]);
        $processed++;
    }
    $con->commit();
    respond(['ok' => true, 'processed' => $processed, 'skipped' => $skipped]);
} catch (Exception $e) {
    if ($con->inTransaction()) $con->rollBack();
    respond(['ok' => false, 'error' => $e->getMessage()], 500);
}