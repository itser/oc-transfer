<?php
$start = microtime(true);
require_once(dirname(__FILE__).'/../config.php');
require_once 'lib/octransfer.php';
$data = array(
	'bd_host' => DB_HOSTNAME,
	'bd_user' => DB_USERNAME,
	'bd_pass' => DB_PASSWORD,
	'bd_name' => DB_DATABASE
);
$oc_transfer = new octransfer();
$oc_transfer->backupSqlBase($data);
$oc_transfer->backupFiles();
echo (microtime(true) - $start);
exit;
