<?php

if (!defined('FUNCTION_PHP_LOADED')) {
define('FUNCTION_PHP_LOADED', true);


function sanitize_output(string $buffer): string {

    // Searching textarea and pre
    preg_match_all('#\<textarea.*\>.*\<\/textarea\>#Uis', $buffer, $foundTxt);
    preg_match_all('#\<pre.*\>.*\<\/pre\>#Uis', $buffer, $foundPre);

    // replacing both with <textarea>$index</textarea> / <pre>$index</pre>
    $buffer = str_replace($foundTxt[0], array_map(function($el){ return '<textarea>'.$el.'</textarea>'; }, array_keys($foundTxt[0])), $buffer);
    $buffer = str_replace($foundPre[0], array_map(function($el){ return '<pre>'.$el.'</pre>'; }, array_keys($foundPre[0])), $buffer);

    // your stuff
    $search = array(
        '/\>[^\S ]+/s',  // strip whitespaces after tags, except space
        '/[^\S ]+\</s',  // strip whitespaces before tags, except space
        '/(\s)+/s' ,      // shorten multiple whitespace sequences
        '/<!--(.|\s)*?-->/' // Remove HTML comments
    );

    $replace = array(
        '>',
        '<',
        '\\1'
    );

    $buffer = preg_replace($search, $replace, $buffer);

    // Replacing back with content
    $buffer = str_replace(array_map(function($el){ return '<textarea>'.$el.'</textarea>'; }, array_keys($foundTxt[0])), $foundTxt[0], $buffer);
    $buffer = str_replace(array_map(function($el){ return '<pre>'.$el.'</pre>'; }, array_keys($foundPre[0])), $foundPre[0], $buffer);

    return $buffer;
}
function media($name){

    include('connect.php');
    $stat4 = $con->prepare("SELECT  MIN(u_medie_2025) AS average FROM " . DB_PREFIX . "medie  WHERE Name = '$name' AND stopx = '0'");
    $stat4->execute();
    $curr  = $stat4->fetch();
    return number_format((float)$curr['average'], 2, '.', '');

    
}

function place($name){

    include('connect.php');
    $stat4 = $con->prepare("SELECT  SUM(nr_place_2025)  AS t_place FROM " . DB_PREFIX . "poztion  WHERE Name = '$name' AND stopx = '0'");
    $stat4->execute();
    $curr  = $stat4->fetch();
    return number_format((float)$curr['t_place'], 0, '.', '');

    
}

function sector($name){

    include('connect.php');
    $stat4 = $con->prepare("SELECT   COUNT(zone) AS average FROM " . DB_PREFIX . "numa_liceu  WHERE zone = '$name' AND stopx = '0'");
    $stat4->execute();
    $curr  = $stat4->fetch();
    return $curr['average'];

    
}
function profil($name){

    include('connect.php');
    $stat4 = $con->prepare("SELECT   COUNT(profil) AS average FROM " . DB_PREFIX . "liceu  WHERE profil = '$name' AND stopx = '0'");
    $stat4->execute();
    $curr  = $stat4->fetch();
    return $curr['average'];

    
}


function profilx($name){

    include('connect.php');
    $stat4 = $con->prepare("SELECT   COUNT(DISTINCT profil) AS average FROM " . DB_PREFIX . "liceu  WHERE name = '$name' AND stopx = '0'");
    $stat4->execute();
    $curr  = $stat4->fetch();
    return $curr['average'];

    
}



function program_clasa_9($name) {
    global $con;

    $stat4 = $con->prepare(
        "SELECT 
            COUNT(IF(`program_9` = 'dimi', 1, NULL))  AS pro_d,
            COUNT(IF(`program_9` = 'prânz', 1, NULL)) AS pro_p
         FROM " . DB_PREFIX . "liceu
         WHERE `name` = '$name' AND `stopx` = 0"
    );
    $stat4->execute();
    $curr = $stat4->fetch();

    $hasD = (int)$curr['pro_d'] > 0;
    $hasP = (int)$curr['pro_p'] > 0;

    if ($hasD && $hasP) return 'Mix';
    if (!$hasD && !$hasP) return 'N/A';
    if ($hasD) return 'Dimineata';
    return 'Prânz';
}
function allprofil($name){

    include('connect.php');
    $stat4 = $con->prepare("SELECT DISTINCT  profil FROM " . DB_PREFIX . "liceu  WHERE name= '$name' AND stopx = '0' ");
    $stat4->execute();
    $allprofil  = $stat4->fetchAll();
    return $allprofil;

    
}


function specializare($name){

    include('connect.php');
    $stat4 = $con->prepare("SELECT 	bilingv,  specializare , u_medie_2025 FROM " . DB_PREFIX . "medie   WHERE name= '$name' AND stopx = '0' ");
    $stat4->execute();
    $specializare  = $stat4->fetchAll();
    return $specializare;

    
}

function countspecializare($name){

    include('connect.php');
    $stat4 = $con->prepare("SELECT 	bilingv,  specializare , u_medie_2025 FROM " . DB_PREFIX . "medie   WHERE name= '$name' AND stopx = '0' ");
    $stat4->execute();
    $specializare  = $stat4->fetchAll();
    return $stat4->rowCount();

    
}


function program_clasa($name) {
    global $con;

    $stat4 = $con->prepare("SELECT specializare,program_9 ,program_10 ,program_11 ,program_12 FROM " . DB_PREFIX . "liceu  WHERE name = '$name' AND stopx = '0' LIMIT 1");
    $stat4->execute();
    $program_clase = $stat4->fetchAll();

    return $program_clase;
}

function is_mobile() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return (bool) preg_match(
        '/Android|iPhone|iPod|Opera Mini|IEMobile|Mobile Safari|BlackBerry|webOS/i',
        $ua
    );
}

}
?>