<?php
   //session_start();
    require_once('config.php');  
    ini_set('default_charset', 'UTF-8');
    include 'connect.php';  
    
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) { // last request was more than 30 minutes ago
    session_unset();     // unset $_SESSION variable for the run-time 
    session_destroy();   // destroy session data in storage
    header('Location: /index.php');
}else{
    $_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp
    if (isset($_SESSION['Language'])) {
        $Language = $_SESSION['Language'] . '.php';
        $langFile =  'languages' . $Language;
    } else {
        $langFile = __DIR__ . '/languages/en.php';
    }

    if (file_exists($langFile)) {
        include $langFile;
    } else {
        // fallback so $pageTitle=lang(...) below doesn't fatal on undefined function
        include __DIR__ . '/languages/en.php';
    }
   // $pageTitle=lang($pageTitle1);
    include 'function.php';
}
?>