<?php
// admin/plugin/limba_api.php — CRUD pentru home_limba (limbi pentru secții bilingve)
ob_start();
require_once __DIR__ . '/admin_init.php';
if (ob_get_length()) { ob_clean(); }

/** @var PDO $con */
$T = DB_PREFIX . 'limba';

/* home_limba alimentează DOUĂ câmpuri diferite:
   - `bilingv` = secția bilingvă (ex: Limba engleză(cu examen))
   - `limba`   = limba de predare (ex: Limba română)
   Propagăm în ambele. */
$REFS = [
    DB_PREFIX . 'liceu'   => ['bilingv', 'limba'],
    DB_PREFIX . 'medie'   => ['bilingv'],
    DB_PREFIX . 'poztion' => ['bilingv'],
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
    try { $con->query("SELECT 1 FROM `$t` LIMIT 1"); return $cache[$t] = true; }
    catch (Throwable $ex) { return $cache[$t] = false; }
}

/** Numără utilizările. „-” înseamnă „fără secție bilingvă” și nu se contorizează. */
function countUsage(PDO $con, array $refs, ?string $desc): int {
    $desc = trim((string)$desc);
    if ($desc === '' || $desc === '-') return 0;

    $total = 0;
    foreach ($refs as $tbl => $cols) {
        if (!tableExists($con, $tbl)) continue;
        foreach ($cols as $col) {
            $st = $con->prepare("SELECT COUNT(*) FROM `$tbl` WHERE `$col` = ?");
            $st->execute([$desc]);
            $total += (int)$st->fetchColumn();
        }
    }
    return $total;
}

function usageBreakdown(PDO $con, array $refs, ?string $desc): array {
    $desc = trim((string)$desc);
    if ($desc === '' || $desc === '-') return [];

    $out = [];
    foreach ($refs as $tbl => $cols) {
        if (!tableExists($con, $tbl)) continue;
        foreach ($cols as $col) {
            $st = $con->prepare("SELECT COUNT(*) FROM `$tbl` WHERE `$col` = ?");
            $st->execute([$desc]);
            $n = (int)$st->fetchColumn();
            if ($n > 0) $out[str_replace(DB_PREFIX, '', $tbl) . '.' . $col] = $n;
        }
    }
    return $out;
}

try {
    switch ($action) {

        /* ---------------- LISTĂ ---------------- */
        case 'list':
            $rows = $con->query("SELECT id_limba, description FROM `$T` ORDER BY description ASC")
                        ->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['nr_uz']    = countUsage($con, $REFS, $r['description']);
                $r['rezervat'] = ($r['description'] === '-') ? 1 : 0;
            }
            unset($r);
            json_out(['data' => $rows]);

        /* ---------------- CITEȘTE ---------------- */
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $st = $con->prepare("SELECT * FROM `$T` WHERE id_limba = ? LIMIT 1");
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) json_out(['ok' => false, 'msg' => 'Limba nu a fost găsită.'], 404);

            $row['usage'] = usageBreakdown($con, $REFS, $row['description']);
            json_out(['ok' => true, 'row' => $row]);

        /* ---------------- ADAUGĂ ---------------- */
        case 'create':
            $desc = p('description');
            if ($desc === '')           json_out(['ok' => false, 'msg' => 'Denumirea este obligatorie.'], 422);
            if (mb_strlen($desc) > 250) json_out(['ok' => false, 'msg' => 'Denumirea depășește 250 de caractere.'], 422);

            $st = $con->prepare("SELECT 1 FROM `$T` WHERE description = ? LIMIT 1");
            $st->execute([$desc]);
            if ($st->fetch()) json_out(['ok' => false, 'msg' => 'Această limbă există deja.'], 409);

            $con->prepare("INSERT INTO `$T` (description) VALUES (?)")->execute([$desc]);
            json_out(['ok' => true, 'msg' => 'Limba „' . $desc . '” a fost adăugată.']);

        /* ---------------- MODIFICĂ ---------------- */
        case 'update':
            $id   = (int)p('id_limba');
            $desc = p('description');

            if (!$id)                   json_out(['ok' => false, 'msg' => 'ID lipsă.'], 400);
            if ($desc === '')           json_out(['ok' => false, 'msg' => 'Denumirea este obligatorie.'], 422);
            if (mb_strlen($desc) > 250) json_out(['ok' => false, 'msg' => 'Denumirea depășește 250 de caractere.'], 422);

            $st = $con->prepare("SELECT description FROM `$T` WHERE id_limba = ? LIMIT 1");
            $st->execute([$id]);
            $old = $st->fetchColumn();
            if ($old === false) json_out(['ok' => false, 'msg' => 'Limba nu mai există.'], 404);

            if ($old === '-' && $desc !== '-') {
                json_out(['ok' => false,
                          'msg' => 'Valoarea „-” este rezervată („fără secție bilingvă”) și nu poate fi redenumită.'], 403);
            }

            $st = $con->prepare("SELECT 1 FROM `$T` WHERE description = ? AND id_limba <> ? LIMIT 1");
            $st->execute([$desc, $id]);
            if ($st->fetch()) json_out(['ok' => false, 'msg' => 'Această denumire este deja folosită.'], 409);

            $con->beginTransaction();
            try {
                $con->prepare("UPDATE `$T` SET description = ? WHERE id_limba = ?")
                    ->execute([$desc, $id]);

                $afectate = 0;
                $detaliu  = [];

                if ($old !== $desc && $old !== '-') {
                    foreach ($REFS as $tbl => $cols) {
                        if (!tableExists($con, $tbl)) continue;
                        foreach ($cols as $col) {
                            $u = $con->prepare("UPDATE `$tbl` SET `$col` = ? WHERE `$col` = ?");
                            $u->execute([$desc, $old]);
                            if ($u->rowCount()) {
                                $afectate += $u->rowCount();
                                $detaliu[] = str_replace(DB_PREFIX, '', $tbl) . '.' . $col . ' (' . $u->rowCount() . ')';
                            }
                        }
                    }
                }

                $con->commit();
            } catch (Throwable $ex) {
                $con->rollBack();
                throw $ex;
            }

            $msg = 'Modificările au fost salvate.';
            if ($afectate) $msg .= ' Actualizat în: ' . implode(', ', $detaliu) . '.';
            json_out(['ok' => true, 'msg' => $msg]);

        /* ---------------- ȘTERGE ---------------- */
        case 'delete':
            $id = (int)p('id_limba');
            if (!$id) json_out(['ok' => false, 'msg' => 'ID lipsă.'], 400);

            $st = $con->prepare("SELECT description FROM `$T` WHERE id_limba = ? LIMIT 1");
            $st->execute([$id]);
            $desc = $st->fetchColumn();
            if ($desc === false) json_out(['ok' => false, 'msg' => 'Limba nu mai există.'], 404);

            if ($desc === '-') {
                json_out(['ok' => false,
                          'msg' => 'Valoarea „-” este rezervată și nu poate fi ștearsă.'], 403);
            }

            $nr = countUsage($con, $REFS, $desc);
            if ($nr > 0) {
                json_out(['ok' => false,
                          'msg' => 'Nu se poate șterge: ' . $nr . ' înregistrare(i) folosesc această limbă.'], 409);
            }

            $con->prepare("DELETE FROM `$T` WHERE id_limba = ?")->execute([$id]);
            json_out(['ok' => true, 'msg' => 'Limba a fost ștearsă.']);

        /* ---------------- ȘTERGERE MULTIPLĂ ---------------- */
        case 'bulk_delete':
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids) || !$ids) json_out(['ok' => false, 'msg' => 'Nicio selecție.'], 400);
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if (!$ids) json_out(['ok' => false, 'msg' => 'Nicio selecție validă.'], 400);

            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $con->prepare("SELECT id_limba, description FROM `$T` WHERE id_limba IN ($in)");
            $st->execute($ids);

            $sterse = 0; $blocate = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if ($r['description'] === '-' || countUsage($con, $REFS, $r['description']) > 0) {
                    $blocate[] = $r['description'];
                    continue;
                }
                $d = $con->prepare("DELETE FROM `$T` WHERE id_limba = ?");
                $d->execute([$r['id_limba']]);
                $sterse += $d->rowCount();
            }

            $msg = $sterse . ' limbă(i) șterse.';
            if ($blocate) $msg .= ' Nu s-au putut șterge: ' . implode(', ', $blocate) . '.';
            json_out(['ok' => true, 'msg' => $msg]);

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