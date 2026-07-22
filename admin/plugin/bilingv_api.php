<?php
// admin/plugin/bilingv_api.php — CRUD pentru home_bilingv_a (cheie = id)
ob_start();
require_once __DIR__ . '/admin_init.php';
if (ob_get_length()) { ob_clean(); }

/** @var PDO $con */
$T = DB_PREFIX . 'bilingv_a';

// tabele care stochează valoarea bilingv ca text
$REFS = [
    DB_PREFIX . 'liceu'   => 'bilingv',
    DB_PREFIX . 'medie'   => 'bilingv',
    DB_PREFIX . 'poztion' => 'bilingv',
];

$action = $_REQUEST['action'] ?? '';

if (in_array($action, ['create', 'update', 'delete', 'bulk_delete'], true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
        json_out(['ok' => false, 'msg' => 'Token CSRF invalid. Reîncarcă pagina.'], 403);
    }
}

function p($k, $d = '') { return trim((string)($_POST[$k] ?? $d)); }

function tableExists(PDO $con, string $t): bool {
    static $cache = [];
    if (isset($cache[$t])) return $cache[$t];
    try {
        $con->query("SELECT 1 FROM `$t` LIMIT 1");
        return $cache[$t] = true;
    } catch (Throwable $ex) {
        return $cache[$t] = false;
    }
}

/**
 * Numără utilizările. Rândurile goale nu se contorizează —
 * altfel „” ar părea folosit de mii de licee non-bilingve.
 */
function countUsage(PDO $con, array $refs, ?string $desc): int {
    $desc = trim((string)$desc);
    if ($desc === '') return 0;

    $total = 0;
    foreach ($refs as $tbl => $col) {
        if (!tableExists($con, $tbl)) continue;
        $st = $con->prepare("SELECT COUNT(*) FROM `$tbl` WHERE `$col` = ?");
        $st->execute([$desc]);
        $total += (int)$st->fetchColumn();
    }
    return $total;
}

function usageBreakdown(PDO $con, array $refs, ?string $desc): array {
    $desc = trim((string)$desc);
    if ($desc === '') return [];

    $out = [];
    foreach ($refs as $tbl => $col) {
        if (!tableExists($con, $tbl)) continue;
        $st = $con->prepare("SELECT COUNT(*) FROM `$tbl` WHERE `$col` = ?");
        $st->execute([$desc]);
        $n = (int)$st->fetchColumn();
        if ($n > 0) $out[$tbl] = $n;
    }
    return $out;
}

try {
    switch ($action) {

        /* ---------------- LISTĂ ---------------- */
        case 'list':
            $rows = $con->query("
                SELECT id_bilingv, COALESCE(description, '') AS description
                FROM `$T`
                ORDER BY description ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$r) {
                $r['nr_uz'] = countUsage($con, $REFS, $r['description']);
                $r['gol']   = (trim($r['description']) === '') ? 1 : 0;
            }
            unset($r);
            json_out(['data' => $rows]);

        /* ---------------- CITEȘTE ---------------- */
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $st = $con->prepare("
                SELECT id_bilingv, COALESCE(description, '') AS description
                FROM `$T` WHERE id_bilingv = ? LIMIT 1
            ");
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) json_out(['ok' => false, 'msg' => 'Înregistrarea nu a fost găsită.'], 404);

            $row['usage'] = usageBreakdown($con, $REFS, $row['description']);
            json_out(['ok' => true, 'row' => $row]);

        /* ---------------- ADAUGĂ ---------------- */
        case 'create':
            $desc = p('description');
            if ($desc === '')           json_out(['ok' => false, 'msg' => 'Denumirea este obligatorie.'], 422);
            if (mb_strlen($desc) > 100) json_out(['ok' => false, 'msg' => 'Denumirea depășește 100 de caractere.'], 422);

            // tabelul nu are UNIQUE pe description → verificăm manual
            $st = $con->prepare("SELECT 1 FROM `$T` WHERE description = ? LIMIT 1");
            $st->execute([$desc]);
            if ($st->fetch()) json_out(['ok' => false, 'msg' => 'Această valoare există deja.'], 409);

            $st = $con->prepare("INSERT INTO `$T` (description) VALUES (?)");
            $st->execute([$desc]);
            json_out(['ok' => true, 'msg' => 'Valoarea „' . $desc . '” a fost adăugată.']);

        /* ---------------- MODIFICĂ ---------------- */
        case 'update':
            $id   = (int)p('id_bilingv');
            $desc = p('description');
            if (!$id)                   json_out(['ok' => false, 'msg' => 'ID lipsă.'], 400);
            if ($desc === '')           json_out(['ok' => false, 'msg' => 'Denumirea este obligatorie.'], 422);
            if (mb_strlen($desc) > 100) json_out(['ok' => false, 'msg' => 'Denumirea depășește 100 de caractere.'], 422);

            $st = $con->prepare("SELECT COALESCE(description, '') FROM `$T` WHERE id_bilingv = ? LIMIT 1");
            $st->execute([$id]);
            $old = $st->fetchColumn();
            if ($old === false) json_out(['ok' => false, 'msg' => 'Înregistrarea nu mai există.'], 404);

            $st = $con->prepare("SELECT 1 FROM `$T` WHERE description = ? AND id_bilingv <> ? LIMIT 1");
            $st->execute([$desc, $id]);
            if ($st->fetch()) json_out(['ok' => false, 'msg' => 'Această valoare este deja folosită.'], 409);

            $st = $con->prepare("UPDATE `$T` SET description = ? WHERE id_bilingv = ?");
            $st->execute([$desc, $id]);

            // propagăm doar dacă vechea valoare nu era goală
            $afectate = 0;
            $detaliu  = [];
            if (trim($old) !== '' && $old !== $desc) {
                foreach ($REFS as $tbl => $col) {
                    if (!tableExists($con, $tbl)) continue;
                    $u = $con->prepare("UPDATE `$tbl` SET `$col` = ? WHERE `$col` = ?");
                    $u->execute([$desc, $old]);
                    if ($u->rowCount()) {
                        $afectate += $u->rowCount();
                        $detaliu[] = $tbl . ' (' . $u->rowCount() . ')';
                    }
                }
            }

            $msg = 'Modificările au fost salvate.';
            if ($afectate) $msg .= ' Actualizate: ' . implode(', ', $detaliu) . '.';
            json_out(['ok' => true, 'msg' => $msg]);

        /* ---------------- ȘTERGE ---------------- */
        case 'delete':
            $id = (int)p('id_bilingv');
            if (!$id) json_out(['ok' => false, 'msg' => 'ID lipsă.'], 400);

            $st = $con->prepare("SELECT COALESCE(description, '') FROM `$T` WHERE id_bilingv = ? LIMIT 1");
            $st->execute([$id]);
            $desc = $st->fetchColumn();
            if ($desc === false) json_out(['ok' => false, 'msg' => 'Înregistrarea nu mai există.'], 404);

            $nr = countUsage($con, $REFS, $desc);
            if ($nr > 0) {
                json_out(['ok' => false,
                          'msg' => 'Nu se poate șterge: ' . $nr . ' înregistrare(i) folosesc această valoare.'], 409);
            }

            $st = $con->prepare("DELETE FROM `$T` WHERE id_bilingv = ?");
            $st->execute([$id]);
            json_out(['ok' => true, 'msg' => 'Înregistrarea a fost ștearsă.']);

        /* ---------------- ȘTERGERE MULTIPLĂ ---------------- */
        case 'bulk_delete':
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids) || !$ids) json_out(['ok' => false, 'msg' => 'Nicio selecție.'], 400);
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if (!$ids) json_out(['ok' => false, 'msg' => 'Nicio selecție validă.'], 400);

            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $con->prepare("
                SELECT id_bilingv, COALESCE(description, '') AS description
                FROM `$T` WHERE id_bilingv IN ($in)
            ");
            $st->execute($ids);

            $sterse = 0; $blocate = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (countUsage($con, $REFS, $r['description']) > 0) {
                    $blocate[] = $r['description'];
                    continue;
                }
                $d = $con->prepare("DELETE FROM `$T` WHERE id_bilingv = ?");
                $d->execute([$r['id_bilingv']]);
                $sterse += $d->rowCount();
            }

            $msg = $sterse . ' înregistrare(i) șterse.';
            if ($blocate) $msg .= ' Nu s-au putut șterge (în uz): ' . implode(', ', $blocate) . '.';
            json_out(['ok' => true, 'msg' => $msg]);

        /* ---------------- CURĂȚĂ RÂNDURILE GOALE ---------------- */
        case 'clean_empty':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
                json_out(['ok' => false, 'msg' => 'Token CSRF invalid.'], 403);
            }
            $st = $con->query("DELETE FROM `$T` WHERE description IS NULL OR TRIM(description) = ''");
            json_out(['ok' => true, 'msg' => $st->rowCount() . ' rând(uri) goale șterse.']);

        default:
            json_out(['ok' => false, 'msg' => 'Acțiune necunoscută.'], 400);
    }
} catch (Throwable $ex) {
    json_out([
        'ok'   => false,
        'msg'  => 'Eroare server: ' . $ex->getMessage(),
        'file' => basename($ex->getFile()) . ':' . $ex->getLine(),
    ], 500);
}