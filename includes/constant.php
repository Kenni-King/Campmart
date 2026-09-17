<?php

define("DB_SERVER", "localhost");
define("DB_USER", "root");//enter your database username
define("DB_PASS", "");   //databse password
define("DB_NAME", "campmartv2");//database name

// Auto-detect base URL
if (!defined('SITE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $docRoot = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'])), '/');
    $appRoot = rtrim(str_replace('\\', '/', (string) realpath(dirname(__DIR__))), '/');
    $path = '/' . ltrim(str_replace($docRoot, '', $appRoot), '/');
    if ($path === '') {
        $path = '/';
    } elseif (substr($path, -1) !== '/') {
        $path .= '/';
    }
    define("SITE_URL", $protocol . $host . $path);
}


$db = new mysqli(DB_SERVER, DB_USER, DB_PASS, DB_NAME);
?>