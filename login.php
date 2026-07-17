<?php

ob_start();
session_start();

$noNavbar = '';
/** @var PDO $con */
global $con;

$pageTitle1 = 'LOGIN';

// id from query string, forced to an integer (used to exclude self on edit)
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Already logged in -> go to dashboard
if (isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

include 'plugin/init.php';
include 'plugin/otp.php';   // generateOtp(), hashOtp(), issueOtp(), clearOtp()

/*
 * Legacy password scheme that older accounts were stored with:
 *   md5($password . md5(313))
 * Kept ONLY so existing users can still log in; on success their hash is
 * silently upgraded to a modern password_hash() value.
 */
function legacyHash(string $password): string
{
    return md5($password . md5(313));
}

/*
 * Strongest hashing algorithm available on this server.
 * Argon2id (memory-hard, current OWASP first choice) when PHP was built with
 * libargon2; bcrypt otherwise. Verification via password_verify() works for
 * any of these automatically, so accounts created under either algorithm are
 * always accepted.
 */
function passwordAlgo()
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
}

/*
 * Cost parameters. Tune to your hardware: aim for ~0.25–0.5s per hash.
 *   - Argon2id: memory_cost in KiB, time_cost = iterations, threads.
 *   - bcrypt:   cost = work factor (each +1 doubles the work).
 */
function passwordOptions(): array
{
    if (defined('PASSWORD_ARGON2ID')) {
        return [
            'memory_cost' => 1 << 16, // 65536 KiB = 64 MiB
            'time_cost'   => 4,
            'threads'     => 2,
        ];
    }

    return ['cost' => 12];
}

function hashPassword(string $password): string
{
    return password_hash($password, passwordAlgo(), passwordOptions());
}

/*
 * Safe existence check. Column is whitelisted; value and id are bound,
 * so nothing from the user is ever concatenated into the SQL.
 */
function userExists($con, string $column, string $value, int $excludeId = 0): bool
{
    $allowed = ['username', 'email'];
    if (!in_array($column, $allowed, true)) {
        return false;
    }

    $stmt = $con->prepare(
        "SELECT id
           FROM " . DB_PREFIX . "user_details
          WHERE $column = ?
            AND id != ?
          LIMIT 1"
    );
    $stmt->execute([$value, $excludeId]);

    return $stmt->rowCount() > 0;
}

// CSRF token (one per session)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$loginError      = '';   // shown inside the login panel
$registerErrors  = [];   // shown inside the register panel
$registerSuccess = '';
$showRegister    = false; // open the register panel on reload when relevant

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check for any POST
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $loginError = '<div class="message warning">Sesiune expirată. Reîncarcă pagina și încearcă din nou.<span class="close">&times;</span></div>';
    } elseif (!isset($_POST['reg'])) {

        /* ---------------------- LOGIN ---------------------- */

        $username = trim($_POST['user'] ?? '');
        $password = $_POST['pass'] ?? '';

        if ($username === '' || $password === '') {
            $loginError = '<div class="message warning">Completează numele de utilizator și parola.<span class="close">&times;</span></div>';
        } else {
            // email + first_name sunt necesare pentru emailul cu codul OTP
            $stmt = $con->prepare(
                "SELECT id, username, email, first_name, password, is_active
                   FROM " . DB_PREFIX . "user_details
                  WHERE (username = ? OR email = ?)
                    AND is_active = 1
                  LIMIT 1"
            );
            $stmt->execute([$username, $username]);
            $row = $stmt->fetch();

            $authenticated = false;

            if ($row) {
                $stored = (string) $row['password'];

                if (password_verify($password, $stored)) {
                    // Already a modern hash
                    $authenticated = true;

                    // Re-hash if the algorithm or cost parameters changed
                    if (password_needs_rehash($stored, passwordAlgo(), passwordOptions())) {
                        $upd = $con->prepare(
                            "UPDATE " . DB_PREFIX . "user_details SET password = ? WHERE id = ?"
                        );
                        $upd->execute([hashPassword($password), $row['id']]);
                    }
                } elseif (hash_equals($stored, legacyHash($password))) {
                    // Old MD5 account -> verify, then upgrade transparently
                    $authenticated = true;

                    $upd = $con->prepare(
                        "UPDATE " . DB_PREFIX . "user_details SET password = ? WHERE id = ?"
                    );
                    $upd->execute([hashPassword($password), $row['id']]);
                }
            }

            if ($authenticated) {
                /*
                 * Parola e corectă, dar sesiunea de login NU se creează încă.
                 * Marcăm userul ca "în așteptare" și îi trimitem codul pe email.
                 * Sesiunea reală se face în verify-otp.php, după codul corect.
                 */
                $_SESSION['pending_user_id'] = (int) $row['id'];
                $_SESSION['otp_last_sent']   = time();

                issueOtp(
                    $con,
                    (int) $row['id'],
                    (string) $row['email'],
                    (string) $row['first_name']
                );

                header('Location: verify-otp.php');
                exit;
            }

            $loginError = '<div class="message warning">Username sau parolă incorectă.<span class="close">&times;</span></div>';
        }

    } else {

        /* -------------------- REGISTER --------------------- */

        $showRegister = true;

        $fname    = trim($_POST['fname'] ?? '');
        $lname    = trim($_POST['lname'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $username = trim($_POST['uname'] ?? '');
        $pass1    = $_POST['pass1'] ?? '';
        $pass2    = $_POST['pass2'] ?? '';

        if (mb_strlen($username) < 4) {
            $registerErrors[] = 'Numele de utilizator trebuie să aibă cel puțin <strong>4 caractere</strong>.';
        }
        if (mb_strlen($username) > 20) {
            $registerErrors[] = 'Numele de utilizator nu poate depăși <strong>20 de caractere</strong>.';
        }
        if ($username === '') {
            $registerErrors[] = 'Numele de utilizator nu poate fi <strong>gol</strong>.';
        }
        if ($fname === '') {
            $registerErrors[] = 'Prenumele nu poate fi <strong>gol</strong>.';
        }
        if ($lname === '') {
            $registerErrors[] = 'Numele nu poate fi <strong>gol</strong>.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $registerErrors[] = 'Adresa de email <strong>nu este validă</strong>.';
        }
        if ($pass1 === '' || $pass2 === '') {
            $registerErrors[] = 'Parola nu poate fi <strong>goală</strong>.';
        } elseif ($pass1 !== $pass2) {
            $registerErrors[] = 'Cele două parole <strong>nu coincid</strong>.';
        } elseif (!preg_match('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}$/', $pass1)) {
            $registerErrors[] = 'Parola trebuie să conțină cel puțin o cifră, o literă mică, o literă mare și minimum <strong>8 caractere</strong>.';
        }

        if (empty($registerErrors) && userExists($con, 'username', $username, $id)) {
            $registerErrors[] = 'Acest nume de utilizator este deja folosit.';
        }
        if (empty($registerErrors) && userExists($con, 'email', $email, $id)) {
            $registerErrors[] = 'Această adresă de email este deja folosită.';
        }

        if (empty($registerErrors)) {
            $activation_token      = bin2hex(random_bytes(16));
            $activation_token_hash = hash("sha256", $activation_token);

            $stmt = $con->prepare(
                "INSERT INTO " . DB_PREFIX . "user_details
                        (first_name, last_name, email, password, username ,account_activation_hash )
                 VALUES (:zfname, :zlname, :zemail, :zpass, :zuser , :ztoken)"
            );
            $stmt->execute([
                'zfname' => $fname,
                'zlname' => $lname,
                'zemail' => $email,
                'zpass'  => hashPassword($pass1),
                'zuser'  => $username,
                'ztoken' => $activation_token_hash,
            ]);

            if (isset($_SERVER['HTTPS'])) {
                $protocol = ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != "off") ? "https" : "http";
            } else {
                $protocol = 'http';
            }

            $subjectText = 'Activarea contului';
            $subject = '=?UTF-8?B?' . base64_encode($subjectText) . '?=';
            $fromEmail = 'info@eliceu.ro';
            $fromName  = '=?UTF-8?B?' . base64_encode('Ǝliceu') . '?=';
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: $fromName <$fromEmail>\r\n";
            $headers .= "Reply-To: $fromEmail\r\n";
            $emailaddres= $email;
            $message = 'Faceți clic <a href="' . $protocol . '://' . $_SERVER['SERVER_NAME'] . '/activate-account.php?token=' . $activation_token . '"> aici </a>&nbsp;pentru a vă activa contul';
          
            mail($emailaddres, $subject, $message, $headers, '-f' . $fromEmail);

            $registerSuccess = '<div class="message success">Verifică-ți emailul pentru a-ți activa contul.<span class="close">&times;</span></div>';
            $showRegister    = false; // send them to the login panel
        }
    }
}

// Mesaj după resetarea parolei (redirect din reset-password.php)
if (isset($_GET['reset'])) {
    $registerSuccess = '<div class="message success">Parola a fost schimbată. Te poți autentifica.<span class="close">&times;</span></div>';
}

// Helper for safely echoing into HTML attributes/text
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

unset($_SESSION['pagename']);
unset($_SESSION['stylecss']);
unset($_SESSION['stylecss1']);
$_SESSION['stylecss']  = 'login.css';
$_SESSION['stylecss1'] = 'licee_general_mobile.css';
$_SESSION['pagename']  = 'login-page';
$pageTitle             = 'Autentificare';

// Shared site header (top bar + slide-in sidebar). Opens <html>, <head>, <body>.
include 'template/header.php';
?>

<!-- Boxicons for the input-field icons used below -->
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <div class="wrapper">

        <div class="form-box" style="height: 700px;">

            <!-- AUTENTIFICARE -->
            <div class="login-container" id="login">

                <form class="login" action="login.php" autocomplete="off" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">

                    <div class="top">
                        <span>Nu ai cont?<a href="#" onclick="register()">Înregistrare</a></span>
                        <header>Autentificare</header>
                    </div>

                    <?php echo $loginError; ?>
                    <?php echo $registerSuccess; ?>

                    <!-- Username / Email -->
                    <div class="input-box">
                        <input type="text" class="input-field" name="user"
                            placeholder="Numele utilizatorului sau email-ul">
                        <i class="bx bx-user"></i>
                    </div>

                    <!-- Parola -->
                    <div class="input-box">
                        <input type="password" class="input-field" name="pass" placeholder="Parolă">
                        <i class="bx bx-lock-alt"></i>
                    </div>

                    <!-- Buton -->
                    <div class="input-box">
                        <input type="submit" class="submit" value="Autentificare">
                    </div>

                    <!-- Checkbox + Termeni -->
                    <div class="two-col">
                        <div class="one">
                            <input type="checkbox" id="login-check">
                            <label for="login-check">Ține-mă minte</label>
                        </div>
                        <div class="two">
                            <label><a href="forgot-password.php">Ai uitat parola?</a></label>
                        </div>
                    </div>
                </form>
            </div>

            <!-- INREGISTRARE -->
            <div class="register-container" id="register">
                <form class="login" action="login.php" autocomplete="off" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="reg" value="1">

                    <div class="top">
                        <span>Ai un cont? <a href="#" onclick="login()">Autentificare</a></span>
                        <header>Înregistrare</header>
                    </div>

                    <?php foreach ($registerErrors as $error): ?>
                        <div class="message warning"><?php echo $error; ?><span class="close">&times;</span></div>
                    <?php endforeach; ?>

                    <!-- Nume + Prenume -->
                    <div class="two-forms">
                        <div class="input-box">
                            <input type="text" name="lname" class="input-field" placeholder="Nume" required>
                            <i class="bx bx-user"></i>
                        </div>
                        <div class="input-box">
                            <input type="text" name="fname" class="input-field" placeholder="Prenume" required>
                            <i class="bx bx-user"></i>
                        </div>
                    </div>

                    <!-- Username -->
                    <div class="input-box">
                        <input type="text" name="uname" class="input-field" placeholder="Username" required>
                        <i class="bx bx-user"></i>
                    </div>

                    <!-- Email -->
                    <div class="input-box">
                        <input type="email" name="email" class="input-field" placeholder="Email" required>
                        <i class="bx bx-envelope"></i>
                    </div>

                    <!-- Parola -->
                    <div class="input-box">
                        <input type="password" name="pass1"
                            pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                            title="Minimum 8 caractere, cu cel puțin o cifră, o literă mică și o literă mare"
                            class="input-field" placeholder="Parolă" required>
                        <i class="bx bx-lock-alt"></i>
                    </div>

                    <div class="input-box">
                        <input type="password" name="pass2"
                            pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                            title="Minimum 8 caractere, cu cel puțin o cifră, o literă mică și o literă mare"
                            class="input-field" placeholder="Confirmă parola" required>
                        <i class="bx bx-lock-alt"></i>
                    </div>

                    <!-- Buton -->
                    <div class="input-box">
                        <input type="submit" class="submit" value="Înregistrare">
                    </div>

                    <!-- Checkbox + Termeni -->
                    <div class="two-col">
                        <div class="one">
                            <input type="checkbox" id="register-check">
                            <label for="register-check">Ține-mă minte</label>
                        </div>
                        <div class="two">
                            <label><a href="#">Termenii și condițiile</a></label>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script>
// Close button
document.querySelectorAll('.close').forEach(btn => {

    btn.addEventListener('click', function(){

        const box = this.parentElement;

        box.classList.add('hide');

        setTimeout(() => box.remove(), 600);

    });

});

// Auto close after 7 seconds
document.querySelectorAll('.message').forEach(box => {

    setTimeout(() => {

        box.classList.add('hide');

        setTimeout(() => box.remove(), 600);

    }, 7000);

});


// ===== Slide between the login and register panels =====
        var a = document.getElementById("loginBtn");    // may be null (nav removed)
        var b = document.getElementById("registerBtn"); // may be null
        var x = document.getElementById("login");
        var y = document.getElementById("register");

        function login() {
            x.style.left = "4px";
            y.style.right = "-520px";
            if (a) a.className = "btn white-btn";
            if (b) b.className = "btn";
            x.style.opacity = 1;
            y.style.opacity = 0;
        }

        function register() {
            x.style.left = "-510px";
            y.style.right = "5px";
            if (a) a.className = "btn";
            if (b) b.className = "btn white-btn";
            x.style.opacity = 0;
            y.style.opacity = 1;
        }


        // Open the register panel if registration failed, or via #register hash
        <?php if ($showRegister): ?>
        register();
        <?php else: ?>
        if (window.location.hash === "#register") {
            register();
        }
        <?php endif; ?>
    </script>
<?php
include 'template/footer.php';
?>