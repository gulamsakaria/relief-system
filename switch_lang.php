<?php
require 'config.php';

$lang = $_GET['lang'] ?? 'bn';
$_SESSION['lang'] = in_array($lang, ['bn', 'en'], true) ? $lang : 'bn';

$back = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $back);
exit;
