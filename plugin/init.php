<?php
    require_once('config.php');  
    ini_set('default_charset', 'UTF-8');
    include 'connect.php';  
    
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) { 
    session_unset();     
    session_destroy();   
    header('Location: /index.php');
}else{
    $_SESSION['LAST_ACTIVITY'] = time(); 
    if (isset($_SESSION['Language'])) {
        $Language = $_SESSION['Language'] . '.php';
        $langFile =  'languages' . $Language;
    } else {
       // $langFile = __DIR__ . '/languages/en.php';
    }

    if (file_exists($langFile)) {
        include $langFile;
    } else {
        include __DIR__ . '/languages/en.php';
    }
    include 'function.php';
}
?>
