<?php
$start = microtime(true);
require_once 'lib/octransfer.php';
$data = array(
	'bd_host' => 'localhost',
	'bd_user' => 'root',
	'bd_pass' => '',
	'bd_name' => '',
	'bd_prefix' => 'oc_'
);
$oc_transfer = new octransfer();
$oc_transfer->restoreSqlBase($data);
$oc_transfer->restoreFiles($data);
echo (microtime(true) - $start);
exit;