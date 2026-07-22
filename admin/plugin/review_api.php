<?php
// admin/plugin/review_api.php — moderare recenzii (home_review) + suspendare conturi
ob_start();
require_once __DIR__ . '/admin_init.php';
if (ob_get_length()) { ob_clean(); }

/** @var PDO $con */
$T  = DB_PREFIX . 'review';
$TU = DB_PREFIX . 'user_details';   // conturile vizitatorilor care scriu recenzii
$TL = DB_PREFIX . 'numa_liceu';

$action = $_REQUEST['action'] ?? '';

$writeActions = [
    'publish', 'unpublish', 'bulk_publish', 'bulk_unpublish',
    'delete', 'bulk_delete', 'suspend_user', 'reactivate_user',
];
if (in_array($action, $writeActions, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
        json_out(['ok' => false, 'msg' => 'Token CSRF invalid. Reîncarcă pagina.'], 403);
    }
}

function p($k, $d = '')   { return trim((string)($_POST[$k] ?? $d)); }
function pInt($k, $d = 0) { return (int)($_POST[$k] ?? $d); }

/** ID-uri valide dintr-un array POST. */
function idList(string $key): array
{
    $v = $_POST[$key] ?? [];
    if (!is_array($v)) return [];
    return array_values(array_filter(array_map('intval', $v)));
}

function tableExists(PDO $con, string $t): bool
{
    static $cache = [];
    if (isset($cache[$t])) return $cache[$t];
    try { $con->query("SELECT 1 FROM `$t` LIMIT 1"); return $cache[$t] = true; }
    catch (Throwable $ex) { return $cache[$t] = false; }
}

$hasUsers = tableExists($con, $TU);

try {
    switch ($action) {

        /* ---------------- LISTĂ ---------------- */
        case 'list':
            // JOIN cu liceul (id) și cu autorul (dacă tabelul există)
            if ($hasUsers) {
                $sql = "SELECT r.id, r.id_numa_liceu, r.user_id, r.rating, r.comment,
                               r.created_at, r.is_active,
                               l.name       AS liceu,
                               u.username   AS autor,
                               u.email      AS autor_email,
                               u.is_active  AS autor_activ,
                               (SELECT COUNT(*) FROM `$T` r2 WHERE r2.user_id = r.user_id) AS autor_recenzii
                        FROM `$T` r
                        LEFT JOIN `$TL` l ON l.id_numa_liceu = r.id_numa_liceu
                        LEFT JOIN `$TU` u ON u.id = r.user_id
                        ORDER BY r.is_active ASC, r.created_at DESC";
            } else {
                $sql = "SELECT r.id, r.id_numa_liceu, r.user_id, r.rating, r.comment,
                               r.created_at, r.is_active,
                               l.name AS liceu,
                               NULL AS autor, NULL AS autor_email,
                               1 AS autor_activ, 0 AS autor_recenzii
                        FROM `$T` r
                        LEFT JOIN `$TL` l ON l.id_numa_liceu = r.id_numa_liceu
                        ORDER BY r.is_active ASC, r.created_at DESC";
            }
            json_out(['data' => $con->query($sql)->fetchAll(PDO::FETCH_ASSOC),
                      'has_users' => $hasUsers]);

        /* ---------------- CITEȘTE O RECENZIE ---------------- */
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $join = $hasUsers
                ? "LEFT JOIN `$TU` u ON u.id = r.user_id"
                : '';
            $cols = $hasUsers
                ? "u.username AS autor, u.email AS autor_email, u.is_active AS autor_activ"
                : "NULL AS autor, NULL AS autor_email, 1 AS autor_activ";

            $st = $con->prepare("
                SELECT r.*, l.name AS liceu, $cols
                FROM `$T` r
                LEFT JOIN `$TL` l ON l.id_numa_liceu = r.id_numa_liceu
                $join
                WHERE r.id = ? LIMIT 1
            ");
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) json_out(['ok' => false, 'msg' => 'Recenzia nu a fost găsită.'], 404);

            // celelalte recenzii ale aceluiași autor — context pentru moderare
            $st = $con->prepare("
                SELECT r.id, r.rating, r.comment, r.is_active, r.created_at, l.name AS liceu
                FROM `$T` r
                LEFT JOIN `$TL` l ON l.id_numa_liceu = r.id_numa_liceu
                WHERE r.user_id = ? AND r.id <> ?
                ORDER BY r.created_at DESC LIMIT 10
            ");
            $st->execute([$row['user_id'], $id]);
            $row['altele'] = $st->fetchAll(PDO::FETCH_ASSOC);

            json_out(['ok' => true, 'row' => $row]);

        /* ---------------- PUBLICĂ / RETRAGE ---------------- */
        case 'publish':
        case 'unpublish':
            $id = pInt('id');
            if (!$id) json_out(['ok' => false, 'msg' => 'ID lipsă.'], 400);

            $val = ($action === 'publish') ? 1 : 0;
            $st  = $con->prepare("UPDATE `$T` SET is_active = ? WHERE id = ?");
            $st->execute([$val, $id]);

            json_out([
                'ok'  => (bool)$st->rowCount(),
                'msg' => $val ? 'Recenzia a fost publicată.' : 'Recenzia a fost retrasă de pe site.',
            ]);

        case 'bulk_publish':
        case 'bulk_unpublish':
            $ids = idList('ids');
            if (!$ids) json_out(['ok' => false, 'msg' => 'Nicio selecție.'], 400);

            $val = ($action === 'bulk_publish') ? 1 : 0;
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $st  = $con->prepare("UPDATE `$T` SET is_active = ? WHERE id IN ($in)");
            $st->execute(array_merge([$val], $ids));

            json_out(['ok' => true,
                      'msg' => $st->rowCount() . ($val ? ' recenzii publicate.' : ' recenzii retrase.')]);

        /* ---------------- ȘTERGE ---------------- */
        case 'delete':
            $id = pInt('id');
            if (!$id) json_out(['ok' => false, 'msg' => 'ID lipsă.'], 400);
            $st = $con->prepare("DELETE FROM `$T` WHERE id = ?");
            $st->execute([$id]);
            json_out([
                'ok'  => (bool)$st->rowCount(),
                'msg' => $st->rowCount() ? 'Recenzia a fost ștearsă.' : 'Nu s-a șters nimic.',
            ]);

        case 'bulk_delete':
            $ids = idList('ids');
            if (!$ids) json_out(['ok' => false, 'msg' => 'Nicio selecție.'], 400);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $con->prepare("DELETE FROM `$T` WHERE id IN ($in)");
            $st->execute($ids);
            json_out(['ok' => true, 'msg' => $st->rowCount() . ' recenzii șterse.']);

        /* ---------------- SUSPENDĂ AUTORUL ---------------- */
        case 'suspend_user':
            if (!$hasUsers) json_out(['ok' => false, 'msg' => 'Tabelul de utilizatori nu este disponibil.'], 400);

            $uid       = pInt('user_id');
            $ascunde   = pInt('hide_reviews') ? true : false;
            if (!$uid) json_out(['ok' => false, 'msg' => 'ID utilizator lipsă.'], 400);

            $st = $con->prepare("SELECT username FROM `$TU` WHERE id = ? LIMIT 1");
            $st->execute([$uid]);
            $uname = $st->fetchColumn();
            if ($uname === false) json_out(['ok' => false, 'msg' => 'Utilizatorul nu a fost găsit.'], 404);

            $st = $con->prepare("UPDATE `$TU` SET is_active = 0 WHERE id = ?");
            $st->execute([$uid]);

            $ascunse = 0;
            if ($ascunde) {
                $st = $con->prepare("UPDATE `$T` SET is_active = 0 WHERE user_id = ?");
                $st->execute([$uid]);
                $ascunse = $st->rowCount();
            }

            $msg = 'Contul „' . $uname . '” a fost suspendat.';
            if ($ascunse ?? false) {} // no-op
            if ($ascunse) $msg .= ' ' . $ascunse . ' recenzie(i) retrase de pe site.';
            json_out(['ok' => true, 'msg' => $msg]);

        /* ---------------- REACTIVEAZĂ AUTORUL ---------------- */
        case 'reactivate_user':
            if (!$hasUsers) json_out(['ok' => false, 'msg' => 'Tabelul de utilizatori nu este disponibil.'], 400);

            $uid = pInt('user_id');
            if (!$uid) json_out(['ok' => false, 'msg' => 'ID utilizator lipsă.'], 400);

            $st = $con->prepare("SELECT username FROM `$TU` WHERE id = ? LIMIT 1");
            $st->execute([$uid]);
            $uname = $st->fetchColumn();
            if ($uname === false) json_out(['ok' => false, 'msg' => 'Utilizatorul nu a fost găsit.'], 404);

            $st = $con->prepare("UPDATE `$TU` SET is_active = 1 WHERE id = ?");
            $st->execute([$uid]);

            json_out(['ok' => true,
                      'msg' => 'Contul „' . $uname . '” a fost reactivat. Recenziile rămân retrase până le publici.']);

        /* ---------------- listă licee pentru filtru ---------------- */
        case 'lookups':
            $licee = $con->query("
                SELECT DISTINCT l.name
                FROM `$T` r JOIN `$TL` l ON l.id_numa_liceu = r.id_numa_liceu
                ORDER BY l.name
            ")->fetchAll(PDO::FETCH_COLUMN);
            json_out(['ok' => true, 'licee' => $licee]);

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