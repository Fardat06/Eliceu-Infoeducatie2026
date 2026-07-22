<?php
// admin/plugin/users_api.php — CRUD pentru home_users (conturi de administrare)
ob_start();
require_once __DIR__ . '/admin_init.php';
if (ob_get_length()) { ob_clean(); }

/** @var PDO $con */
$T = DB_PREFIX . 'users';

$action = $_REQUEST['action'] ?? '';

$writeActions = ['create', 'update', 'delete', 'bulk_delete', 'toggle_stop', 'reset_password'];
if (in_array($action, $writeActions, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
        json_out(['ok' => false, 'msg' => 'Token CSRF invalid. Reîncarcă pagina.'], 403);
    }
}

/* ID-ul contului curent — nu îl lăsăm să se auto-distrugă */
function currentUserId(PDO $con): int
{
    if (!empty($_SESSION['UserID'])) return (int)$_SESSION['UserID'];
    if (!empty($_SESSION['username-x'])) {
        $st = $con->prepare("SELECT UserID FROM `" . DB_PREFIX . "users` WHERE UserName = ? LIMIT 1");
        $st->execute([$_SESSION['username-x']]);
        return (int)$st->fetchColumn();
    }
    return 0;
}

function p($k, $d = '')   { return trim((string)($_POST[$k] ?? $d)); }
function pInt($k, $d = 0) { return (int)($_POST[$k] ?? $d); }

/** Grupuri de utilizatori. Ajustează dacă folosești altă convenție. */
function groupName(int $g): string
{
    $map = [0 => 'Utilizator', 1 => 'Editor', 2 => 'Administrator', 9 => 'Super-admin'];
    return $map[$g] ?? ('Grup ' . $g);
}

function validate(array $f, bool $isNew, ?string $pass): array
{
    $err = [];
    if ($f['UserName'] === '')             { $err[] = 'Numele de utilizator este obligatoriu.'; }
    if (mb_strlen($f['UserName']) > 255)   { $err[] = 'Numele de utilizator este prea lung.'; }
    if (preg_match('/\s/', $f['UserName'])) { $err[] = 'Numele de utilizator nu poate conține spații.'; }

    if ($f['Email'] === '') {
        $err[] = 'Adresa de email este obligatorie.';
    } elseif (!filter_var($f['Email'], FILTER_VALIDATE_EMAIL)) {
        $err[] = 'Adresa de email nu este validă.';
    }

    if ($f['FullName'] === '') { $err[] = 'Numele complet este obligatoriu.'; }

    if ($isNew || ($pass !== null && $pass !== '')) {
        if ($pass === null || mb_strlen($pass) < 8) {
            $err[] = 'Parola trebuie să aibă cel puțin 8 caractere.';
        }
    }
    return $err;
}

try {
    switch ($action) {

        /* ---------------- LISTĂ ---------------- */
        case 'list':
            $rows = $con->query("
                SELECT UserID, UserName, FullName, Email, Language,
                       GroupID, RegStatus, TrustStatus, stopx,
                       first_name, last_name, image, created_at, updated_at
                FROM `$T`
                ORDER BY UserName ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$r) {
                $r['GroupName'] = groupName((int)$r['GroupID']);
            }
            unset($r);

            json_out(['data' => $rows, 'me' => currentUserId($con)]);

        /* ---------------- CITEȘTE ---------------- */
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $st = $con->prepare("
                SELECT UserID, UserName, FullName, Email, Language, GroupID,
                       RegStatus, TrustStatus, stopx, first_name, last_name,
                       image, FontName, Date
                FROM `$T` WHERE UserID = ? LIMIT 1
            ");
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) json_out(['ok' => false, 'msg' => 'Utilizatorul nu a fost găsit.'], 404);
            json_out(['ok' => true, 'row' => $row]);

        /* ---------------- ADAUGĂ ---------------- */
        case 'create':
            $f = [
                'UserName'    => p('UserName'),
                'Email'       => p('Email'),
                'FullName'    => p('FullName'),
                'Language'    => p('Language', 'ro') ?: 'ro',
                'GroupID'     => pInt('GroupID'),
                'RegStatus'   => pInt('RegStatus', 1),
                'TrustStatus' => pInt('TrustStatus'),
                'first_name'  => p('first_name'),
                'last_name'   => p('last_name'),
                'stopx'       => pInt('stopx') ? 1 : 0,
            ];
            $pass = p('Password');

            $err = validate($f, true, $pass);
            if ($err) json_out(['ok' => false, 'msg' => implode(' ', $err)], 422);

            $st = $con->prepare("SELECT UserName, Email FROM `$T` WHERE UserName = ? OR Email = ? LIMIT 1");
            $st->execute([$f['UserName'], $f['Email']]);
            if ($dup = $st->fetch(PDO::FETCH_ASSOC)) {
                $msg = ($dup['UserName'] === $f['UserName'])
                     ? 'Acest nume de utilizator este deja folosit.'
                     : 'Această adresă de email este deja folosită.';
                json_out(['ok' => false, 'msg' => $msg], 409);
            }

            $f['Password'] = password_hash($pass, PASSWORD_DEFAULT);
            $f['Date']     = date('Y-m-d');

            $cols = array_keys($f);
            $sql  = "INSERT INTO `$T` (`" . implode('`,`', $cols) . "`, `created_at`, `updated_at`, `created_by`)
                     VALUES (:" . implode(', :', $cols) . ", NOW(), NOW(), :me)";
            $params = $f;
            $params['me'] = currentUserId($con);
            $con->prepare($sql)->execute($params);

            json_out(['ok' => true, 'msg' => 'Utilizatorul „' . $f['UserName'] . '” a fost creat.']);

        /* ---------------- MODIFICĂ ---------------- */
        case 'update':
            $id = pInt('UserID');
            if (!$id) json_out(['ok' => false, 'msg' => 'ID lipsă.'], 400);

            $f = [
                'UserName'    => p('UserName'),
                'Email'       => p('Email'),
                'FullName'    => p('FullName'),
                'Language'    => p('Language', 'ro') ?: 'ro',
                'GroupID'     => pInt('GroupID'),
                'RegStatus'   => pInt('RegStatus'),
                'TrustStatus' => pInt('TrustStatus'),
                'first_name'  => p('first_name'),
                'last_name'   => p('last_name'),
                'stopx'       => pInt('stopx') ? 1 : 0,
            ];
            $pass = p('Password');   // gol = nu se schimbă

            $err = validate($f, false, $pass === '' ? null : $pass);
            if ($err) json_out(['ok' => false, 'msg' => implode(' ', $err)], 422);

            $st = $con->prepare("SELECT UserID FROM `$T` WHERE UserID = ? LIMIT 1");
            $st->execute([$id]);
            if (!$st->fetch()) json_out(['ok' => false, 'msg' => 'Utilizatorul nu mai există.'], 404);

            $st = $con->prepare("SELECT UserName, Email FROM `$T`
                                 WHERE (UserName = ? OR Email = ?) AND UserID <> ? LIMIT 1");
            $st->execute([$f['UserName'], $f['Email'], $id]);
            if ($dup = $st->fetch(PDO::FETCH_ASSOC)) {
                $msg = ($dup['UserName'] === $f['UserName'])
                     ? 'Acest nume de utilizator este deja folosit.'
                     : 'Această adresă de email este deja folosită.';
                json_out(['ok' => false, 'msg' => $msg], 409);
            }

            $me = currentUserId($con);
            // nu îți poți retrage singur drepturile sau dezactiva propriul cont
            if ($id === $me) {
                $f['stopx']     = 0;
                $f['GroupID']   = (int)$con->query("SELECT GroupID FROM `$T` WHERE UserID = $me")->fetchColumn();
                $f['RegStatus'] = 1;
            }

            if ($pass !== '') {
                $f['Password'] = password_hash($pass, PASSWORD_DEFAULT);
            }

            $set = [];
            foreach (array_keys($f) as $c) { $set[] = "`$c` = :$c"; }
            $params = $f;
            $params['id'] = $id;
            $params['me'] = $me;

            $con->prepare("UPDATE `$T` SET " . implode(', ', $set) .
                          ", `updated_at` = NOW(), `updated_by` = :me WHERE UserID = :id")
                ->execute($params);

            $msg = 'Modificările au fost salvate.';
            if ($pass !== '') $msg .= ' Parola a fost schimbată.';
            if ($id === $me)  $msg .= ' Grupul și starea propriului cont nu pot fi modificate.';
            json_out(['ok' => true, 'msg' => $msg]);

        /* ---------------- RESETARE PAROLĂ ---------------- */
        case 'reset_password':
            $id   = pInt('UserID');
            $pass = p('Password');
            if (!$id)                  json_out(['ok' => false, 'msg' => 'ID lipsă.'], 400);
            if (mb_strlen($pass) < 8)  json_out(['ok' => false, 'msg' => 'Parola trebuie să aibă cel puțin 8 caractere.'], 422);

            $st = $con->prepare("UPDATE `$T` SET Password = ?, updated_at = NOW(), updated_by = ?
                                 WHERE UserID = ?");
            $st->execute([password_hash($pass, PASSWORD_DEFAULT), currentUserId($con), $id]);
            json_out(['ok' => (bool)$st->rowCount(), 'msg' => 'Parola a fost resetată.']);

        /* ---------------- ACTIVEAZĂ / DEZACTIVEAZĂ ---------------- */
        case 'toggle_stop':
            $id = pInt('UserID');
            if (!$id) json_out(['ok' => false, 'msg' => 'ID lipsă.'], 400);
            if ($id === currentUserId($con)) {
                json_out(['ok' => false, 'msg' => 'Nu îți poți dezactiva propriul cont.'], 403);
            }
            $st = $con->prepare("UPDATE `$T` SET stopx = 1 - stopx, updated_at = NOW() WHERE UserID = ?");
            $st->execute([$id]);
            json_out(['ok' => true, 'msg' => 'Starea contului a fost actualizată.']);

        /* ---------------- ȘTERGE ---------------- */
        case 'delete':
            $id = pInt('UserID');
            if (!$id) json_out(['ok' => false, 'msg' => 'ID lipsă.'], 400);
            if ($id === currentUserId($con)) {
                json_out(['ok' => false, 'msg' => 'Nu îți poți șterge propriul cont.'], 403);
            }

            // nu rămânem fără niciun administrator activ
            $admini = (int)$con->query("SELECT COUNT(*) FROM `$T` WHERE GroupID >= 2 AND stopx = 0")->fetchColumn();
            $st = $con->prepare("SELECT GroupID, stopx FROM `$T` WHERE UserID = ? LIMIT 1");
            $st->execute([$id]);
            $u = $st->fetch(PDO::FETCH_ASSOC);
            if ($u && (int)$u['GroupID'] >= 2 && (int)$u['stopx'] === 0 && $admini <= 1) {
                json_out(['ok' => false, 'msg' => 'Nu se poate șterge ultimul administrator activ.'], 409);
            }

            $st = $con->prepare("DELETE FROM `$T` WHERE UserID = ?");
            $st->execute([$id]);
            json_out([
                'ok'  => (bool)$st->rowCount(),
                'msg' => $st->rowCount() ? 'Utilizatorul a fost șters.' : 'Nu s-a șters nimic.',
            ]);

        /* ---------------- ȘTERGERE MULTIPLĂ ---------------- */
        case 'bulk_delete':
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids) || !$ids) json_out(['ok' => false, 'msg' => 'Nicio selecție.'], 400);
            $ids = array_values(array_filter(array_map('intval', $ids)));

            $me  = currentUserId($con);
            $ids = array_values(array_diff($ids, [$me]));
            if (!$ids) json_out(['ok' => false, 'msg' => 'Nicio selecție validă (propriul cont este exclus).'], 400);

            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $con->prepare("DELETE FROM `$T` WHERE UserID IN ($in)");
            $st->execute($ids);

            $msg = $st->rowCount() . ' utilizator(i) șterși.';
            json_out(['ok' => true, 'msg' => $msg]);

        /* ---------------- grupuri pentru selector ---------------- */
        case 'lookups':
            $groups = [];
            foreach ([0, 1, 2, 9] as $g) $groups[] = ['id' => $g, 'name' => groupName($g)];

            $langs = $con->query("SELECT DISTINCT Language FROM `$T` WHERE Language <> '' ORDER BY Language")
                         ->fetchAll(PDO::FETCH_COLUMN);
            if (!$langs) $langs = ['ro', 'en'];

            json_out(['ok' => true, 'groups' => $groups, 'langs' => $langs]);

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