<?php
// admin/plugin/profil_api.php — CRUD pentru home_profil (cheie = id)
ob_start();
require_once __DIR__ . '/admin_init.php';
if (ob_get_length()) { ob_clean(); }

/** @var PDO $con */
$T = DB_PREFIX . 'profil';

// tabele care stochează profilul ca text (nu prin FK)
$REFS = [
    DB_PREFIX . 'liceu'          => 'profil',
    DB_PREFIX . 'admitere'       => 'profil',
    DB_PREFIX . 'admitere_2026'  => 'profil',
];

$action = $_REQUEST['action'] ?? '';

if (in_array($action, ['create', 'update', 'delete', 'bulk_delete'], true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
        json_out(['ok' => false, 'msg' => 'Token CSRF invalid. Reîncarcă pagina.'], 403);
    }
}

function p($k, $d = '') { return trim((string)($_POST[$k] ?? $d)); }

/** Verifică dacă un tabel există (unele pot lipsi în anumite instalări). */
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

/** Numără câte rânduri folosesc un profil, pe toate tabelele de referință. */
function countUsage(PDO $con, array $refs, string $desc): int {
    $total = 0;
    foreach ($refs as $tbl => $col) {
        if (!tableExists($con, $tbl)) continue;
        $st = $con->prepare("SELECT COUNT(*) FROM `$tbl` WHERE `$col` = ?");
        $st->execute([$desc]);
        $total += (int)$st->fetchColumn();
    }
    return $total;
}

try {
    switch ($action) {

        /* ---------------- LISTĂ ---------------- */
        case 'list':
            $rows = $con->query("SELECT id_profil, description FROM `$T` ORDER BY description ASC")
                        ->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['nr_uz'] = countUsage($con, $REFS, $r['description']);
            }
            unset($r);
            json_out(['data' => $rows]);

        /* ---------------- CITEȘTE ---------------- */
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $st = $con->prepare("SELECT * FROM `$T` WHERE id_profil = ? LIMIT 1");
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) json_out(['ok' => false, 'msg' => 'Profilul nu a fost găsit.'], 404);
            json_out(['ok' => true, 'row' => $row]);

        /* ---------------- ADAUGĂ ---------------- */
        case 'create':
            $desc = p('description');
            if ($desc === '')           json_out(['ok' => false, 'msg' => 'Denumirea este obligatorie.'], 422);
            if (mb_strlen($desc) > 250) json_out(['ok' => false, 'msg' => 'Denumirea depășește 250 de caractere.'], 422);

            $st = $con->prepare("SELECT 1 FROM `$T` WHERE description = ? LIMIT 1");
            $st->execute([$desc]);
            if ($st->fetch()) json_out(['ok' => false, 'msg' => 'Acest profil există deja.'], 409);

            $st = $con->prepare("INSERT INTO `$T` (description) VALUES (?)");
            $st->execute([$desc]);
            json_out(['ok' => true, 'msg' => 'Profilul „' . $desc . '” a fost adăugat.']);

        /* ---------------- MODIFICĂ ---------------- */
/* ---------------- MODIFICĂ ---------------- */
        case 'update':
            $id   = (int)p('id_profil');
            $desc = p('description');

            if (!$id)                   json_out(['ok' => false, 'msg' => 'ID lipsă.'], 400);
            if ($desc === '')           json_out(['ok' => false, 'msg' => 'Denumirea este obligatorie.'], 422);
            if (mb_strlen($desc) > 250) json_out(['ok' => false, 'msg' => 'Denumirea depășește 250 de caractere.'], 422);

            // valoarea veche, necesară pentru propagare
            $st = $con->prepare("SELECT description FROM `$T` WHERE id_profil = ? LIMIT 1");
            $st->execute([$id]);
            $old = $st->fetchColumn();
            if ($old === false) json_out(['ok' => false, 'msg' => 'Profilul nu mai există.'], 404);

            // denumire duplicată la alt id?
            $st = $con->prepare("SELECT 1 FROM `$T` WHERE description = ? AND id_profil <> ? LIMIT 1");
            $st->execute([$desc, $id]);
            if ($st->fetch()) json_out(['ok' => false, 'msg' => 'Această denumire este deja folosită.'], 409);

            $con->beginTransaction();
            try {
                // 1. actualizăm tabelul de referință
                $con->prepare("UPDATE `$T` SET description = ? WHERE id_profil = ?")
                    ->execute([$desc, $id]);

                // 2. propagăm în tabelele care stochează profilul ca text
                $afectate = 0;
                $detaliu  = [];

                if ($old !== $desc) {
                    foreach ($REFS as $tbl => $col) {
                        if (!tableExists($con, $tbl)) continue;

                        $u = $con->prepare("UPDATE `$tbl` SET `$col` = ? WHERE `$col` = ?");
                        $u->execute([$desc, $old]);

                        if ($u->rowCount()) {
                            $afectate += $u->rowCount();
                            $detaliu[] = str_replace(DB_PREFIX, '', $tbl) . ' (' . $u->rowCount() . ')';
                        }
                    }
                }

                $con->commit();
            } catch (Throwable $ex) {
                $con->rollBack();
                throw $ex;
            }

            $msg = 'Modificările au fost salvate.';
            if ($afectate) {
                $msg .= ' Actualizat în: ' . implode(', ', $detaliu) . '.';
            }
            json_out(['ok' => true, 'msg' => $msg]);
            

        /* ---------------- ȘTERGE ---------------- */
        case 'delete':
            $id = (int)p('id_profil');
            if (!$id) json_out(['ok' => false, 'msg' => 'ID lipsă.'], 400);

            $st = $con->prepare("SELECT description FROM `$T` WHERE id_profil = ? LIMIT 1");
            $st->execute([$id]);
            $desc = $st->fetchColumn();
            if ($desc === false) json_out(['ok' => false, 'msg' => 'Profilul nu mai există.'], 404);

            $nr = countUsage($con, $REFS, $desc);
            if ($nr > 0) {
                json_out(['ok' => false,
                          'msg' => 'Nu se poate șterge: ' . $nr . ' înregistrare(i) folosesc acest profil.'], 409);
            }

            $st = $con->prepare("DELETE FROM `$T` WHERE id_profil = ?");
            $st->execute([$id]);
            json_out(['ok' => true, 'msg' => 'Profilul a fost șters.']);

        /* ---------------- ȘTERGERE MULTIPLĂ ---------------- */
        case 'bulk_delete':
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids) || !$ids) json_out(['ok' => false, 'msg' => 'Nicio selecție.'], 400);
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if (!$ids) json_out(['ok' => false, 'msg' => 'Nicio selecție validă.'], 400);

            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $con->prepare("SELECT id_profil, description FROM `$T` WHERE id_profil IN ($in)");
            $st->execute($ids);

            $sterse = 0; $blocate = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (countUsage($con, $REFS, $r['description']) > 0) {
                    $blocate[] = $r['description'];
                    continue;
                }
                $d = $con->prepare("DELETE FROM `$T` WHERE id_profil = ?");
                $d->execute([$r['id_profil']]);
                $sterse += $d->rowCount();
            }

            $msg = $sterse . ' profil(e) șterse.';
            if ($blocate) $msg .= ' Nu s-au putut șterge (în uz): ' . implode(', ', $blocate) . '.';
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