<?php

ob_start();
session_start();

global $con;

include 'plugin/init.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('passwordAlgo')) {
    function passwordAlgo()
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }
}

if (!function_exists('passwordOptions')) {
    function passwordOptions(): array
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return [
                'memory_cost' => 1 << 16,
                'time_cost'   => 4,
                'threads'     => 2,
            ];
        }

        return ['cost' => 12];
    }
}

if (!function_exists('hashPassword')) {
    function hashPassword(string $password): string
    {
        return password_hash($password, passwordAlgo(), passwordOptions());
    }
}


$token = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['token'] ?? '')
    : ($_GET['token'] ?? '');

$errors     = [];
$tokenValid = false;
$user       = null;

if ($token !== '') {
    $tokenHash = hash('sha256', $token);

    $stmt = $con->prepare(
        "SELECT id, username, reset_token_expires_at
           FROM " . DB_PREFIX . "user_details
          WHERE reset_token_hash = ?
          LIMIT 1"
    );
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch();

    if ($user && strtotime($user['reset_token_expires_at']) > time()) {
        $tokenValid = true;
    }
}

if (!$tokenValid) {
    $errors[] = 'Linkul de resetare este <strong>invalid sau a expirat</strong>. Cere unul nou.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {

    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $errors[] = 'Sesiune expirată. Reîncarcă pagina și încearcă din nou.';
    } else {

        $pass1 = $_POST['pass1'] ?? '';
        $pass2 = $_POST['pass2'] ?? '';

        if ($pass1 === '' || $pass2 === '') {
            $errors[] = 'Parola nu poate fi <strong>goală</strong>.';
        } elseif ($pass1 !== $pass2) {
            $errors[] = 'Cele două parole <strong>nu coincid</strong>.';
        } elseif (!preg_match('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}$/', $pass1)) {
            $errors[] = 'Parola trebuie să conțină cel puțin o cifră, o literă mică, o literă mare și minimum <strong>8 caractere</strong>.';
        }

        if (empty($errors)) {
            $upd = $con->prepare(
                "UPDATE " . DB_PREFIX . "user_details
                    SET password = ?, reset_token_hash = NULL, reset_token_expires_at = NULL
                  WHERE id = ?"
            );
            $upd->execute([hashPassword($pass1), $user['id']]);

            session_regenerate_id(true);

            header('Location: login.php?reset=1');
            exit;
        }
    }
}

unset($_SESSION['pagename'], $_SESSION['stylecss'], $_SESSION['stylecss1']);
$_SESSION['stylecss']  = 'login.css';
$_SESSION['stylecss1'] = 'licee_general_mobile.css';
$_SESSION['pagename']  = 'login-page';
$pageTitle             = 'Parolă nouă';

include 'template/header.php';
?>

<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

<div class="wrapper">
    <div class="form-box" style="height: 480px;">

        <div class="login-container" id="login" style="left: 4px; opacity: 1;">

            <form class="login" action="reset-password.php" autocomplete="off" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="token" value="<?php echo e($token); ?>">

                <div class="top">
                    <span>Ți-ai amintit parola? <a href="login.php">Autentificare</a></span>
                    <header>Parolă nouă</header>
                </div>

                <?php foreach ($errors as $error): ?>
                    <div class="message warning"><?php echo $error; ?><span class="close">&times;</span></div>
                <?php endforeach; ?>

                <?php if ($tokenValid): ?>

                    <div class="input-box">
                        <input type="password" name="pass1"
                            pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                            title="Minimum 8 caractere, cu cel puțin o cifră, o literă mică și o literă mare"
                            class="input-field" placeholder="Parolă nouă" required>
                        <i class="bx bx-lock-alt"></i>
                    </div>

                    <div class="input-box">
                        <input type="password" name="pass2"
                            pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                            title="Minimum 8 caractere, cu cel puțin o cifră, o literă mică și o literă mare"
                            class="input-field" placeholder="Confirmă parola" required>
                        <i class="bx bx-lock-alt"></i>
                    </div>

                    <div class="input-box">
                        <input type="submit" class="submit" value="Salvează parola">
                    </div>

                <?php else: ?>

                    <div class="input-box">
                        <a class="submit" href="forgot-password.php"
                           style="display:block; text-align:center; line-height:normal;">
                            Cere un link nou
                        </a>
                    </div>

                <?php endif; ?>
            </form>
        </div>

    </div>
</div>

<script>
document.querySelectorAll('.close').forEach(btn => {
    btn.addEventListener('click', function () {
        const box = this.parentElement;
        box.classList.add('hide');
        setTimeout(() => box.remove(), 600);
    });
});
</script>

<?php
include 'template/footer.php';
?>
