<?php
ob_start();
session_start();
$noNavbar = '';
global $con;
$pageTitle1 = 'LOGIN';
if (isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}
include 'plugin/init.php';
$token = $_GET["token"];
$token_hash = hash("sha256", $token);

$stmt   = $con->prepare("SELECT id FROM " . DB_PREFIX . "user_details  WHERE  account_activation_hash = ? LIMIT 1");
$stmt->execute([$token_hash]);
$user   = $stmt->rowCount();
$stmt1  = $stmt->fetch();
$userid = $stmt1['id'];

if ($user == null) {
    die("token not found");
}else{
    $upd = $con->prepare("UPDATE " . DB_PREFIX . "user_details SET account_activation_hash = NULL , is_active = 1 WHERE id = ?");
    $upd->execute([$userid]);
    header('Location: login.php');
    exit;
}
?>

