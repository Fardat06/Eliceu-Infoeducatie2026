<?php
    require_once('config.php');


    $dbname =  DB_DATABASE ;
    $dsn = 'mysql:host='.DB_HOSTNAME. '; dbname='.$dbname;
    $user= DB_USERNAME;
    $pass= DB_PASSWORD;

    $option = array(
      PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8' ,
    );

    try {
        global $con;
        $con = new PDO($dsn, $user, $pass,$option);
        $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // ECHO 'You Are Connected';
    }
    catch (PDOException $e){
        echo'Failed To Connect' . $e->getMessage();
    }


?>