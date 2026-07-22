<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pluginDir = __DIR__ . '/../../plugin/';

require_once $pluginDir . 'config.php';


set_include_path(get_include_path() . PATH_SEPARATOR . $pluginDir);
require_once $pluginDir . 'connect.php';

require_once __DIR__ . '/helpers.php';

global $con;
if (!isset($con) || !($con instanceof PDO)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Conexiunea la baza de date a eșuat.']);
    exit;
}
$con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$con->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

define('TBL_LICEU', DB_PREFIX . 'numa_liceu');
define('TBL_TIP',   DB_PREFIX . 'tip_liceu');
define('PHOTO_DIR', __DIR__ . '/../src/imges/liceu/');
define('PHOTO_URL', '../src/images/liceu/');
