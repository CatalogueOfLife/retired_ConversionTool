<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<title>Base Scheme Optimizer</title>
</head>
<body style="font: 12px verdana;">
<h3>Base Scheme Optimizer</h3>

<?php
	require_once 'DbHandler.php';
	require_once 'Indicator.php';
	$config = parse_ini_file('config/AcToBs.ini', true);
	foreach ($config as $k => $v) {
		$o = array();
		if (isset($config["options"])) {
			$options = explode(",", $config["options"]);
			foreach ($options as $option) {
				$parts = explode("=", trim($option));
				$o[$parts[0]] = $parts[1];
			}
		}
		DbHandler::createInstance($k, $v, $o);
	}
	$pdo = DbHandler::getInstance('target');
	
	$stmt = $pdo->prepare('SELECT * FROM `specialist`');
	$stmt -> execute ( ) ;
	var_dump ( $stmt -> fetchAll ( ) ) ;
	
?>