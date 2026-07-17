<?php

ob_start();
session_start();

global $con;

if (isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

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

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $message = '<div class="message warning">Sesiune expirată. Reîncarcă pagina și încearcă din nou.<span class="close">&times;</span></div>';
    } else {

        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="message warning">Adresa de email <strong>nu este validă</strong>.<span class="close">&times;</span></div>';
        } else {

            $stmt = $con->prepare(
                "SELECT id, first_name
                   FROM " . DB_PREFIX . "user_details
                  WHERE email = ?
                    AND is_active = 1
                  LIMIT 1"
            );
            $stmt->execute([$email]);
            $row = $stmt->fetch();

            if ($row) {
                $resetToken = bin2hex(random_bytes(32));        
                $resetHash  = hash('sha256', $resetToken);          
                $expiresAt  = date('Y-m-d H:i:s', time() + 3600);   

                $upd = $con->prepare(
                    "UPDATE " . DB_PREFIX . "user_details
                        SET reset_token_hash = ?, reset_token_expires_at = ?
                      WHERE id = ?"
                );
                $upd->execute([$resetHash, $expiresAt, $row['id']]);

                if (isset($_SERVER['HTTPS'])) {
                    $protocol = ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'off') ? 'https' : 'http';
                } else {
                    $protocol = 'http';
                }

                $link = $protocol . '://' . $_SERVER['SERVER_NAME'] . '/reset-password.php?token=' . $resetToken;

                $subject = 'Resetarea parolei';
                $body    = 'Ai cerut resetarea parolei pentru contul tău Ǝliceu.<br><br>'
                         . 'Fă clic <a href="' . $link . '">aici</a> pentru a-ți seta o parolă nouă.<br>'
                         . 'Linkul este valabil <strong>1 oră</strong>.<br><br>'
                         . 'Dacă nu tu ai cerut acest lucru, ignoră acest email — parola rămâne neschimbată.';

                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8\r\n";

                mail($email, $subject, $body, $headers);
            }

            $message = '<div class="message success">Dacă adresa există în baza noastră de date, ți-am trimis un link de resetare. Verifică-ți emailul (și folderul Spam).<span class="close">&times;</span></div>';
        }
    }
}

unset($_SESSION['pagename'], $_SESSION['stylecss'], $_SESSION['stylecss1']);
$_SESSION['stylecss']  = 'login.css';
$_SESSION['stylecss1'] = 'licee_general_mobile.css';
$_SESSION['pagename']  = 'login-page';
$pageTitle             = 'Recuperare parolă';

include 'template/header.php';
?>

<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

<div class="wrapper">
    <div class="form-box" style="height: 420px;">

        <div class="login-container" id="login" style="left: 4px; opacity: 1;">

            <form class="login" action="forgot-password.php" autocomplete="off" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">

                <div class="top">
                    <span>Ți-ai amintit parola? <a href="login.php">Autentificare</a></span>
                    <header>Recuperare parolă</header>
                </div>

                <?php echo $message; ?>

                <div class="input-box">
                    <input type="email" class="input-field" name="email" placeholder="Adresa ta de email" required>
                    <i class="bx bx-envelope"></i>
                </div>

                <div class="input-box">
                    <input type="submit" class="submit" value="Trimite linkul de resetare">
                </div>
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

document.querySelectorAll('.message').forEach(box => {
    setTimeout(() => {
        box.classList.add('hide');
        setTimeout(() => box.remove(), 600);
    }, 7000);
});
</script>

<?php
include 'template/footer.php';
?>
