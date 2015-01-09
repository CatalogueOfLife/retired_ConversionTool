<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<title>Annual Checklist sitemaps</title>
</head>
<body style="font: 11px verdana; width: 600px;">
<h3>Annual Checklist sitemaps</h3>
<p style="font-size: 10px; margin-bottom: 20px;">
<p>Creating sitemaps...</p>

<?php
set_time_limit(0);
ini_set('memory_limit', '1024M');
set_include_path('library' . PATH_SEPARATOR . get_include_path());

require_once 'library/bootstrap.php';
require_once 'library/BsOptimizerLibrary.php';
require_once 'DbHandler.php';
require_once 'Indicator.php';
require_once 'library/Zend/Log/Writer/Stream.php';
require_once 'library/Zend/Log.php';
alwaysFlush();

$config = isset($argv) && isset($argv[1]) ?
    parse_ini_file($argv[1], true) : parse_ini_file('config/AcToBs.ini', true);
$options = explode(",", $config['target']['options']);
foreach ($options as $option) {
    $parts = explode("=", trim($option));
    $o[$parts[0]] = $parts[1];
}
DbHandler::createInstance('target', $config['target'], $o);
$pdo = DbHandler::getInstance('target');
$writer = new Zend_Log_Writer_Stream('logs/' . date("Y-m-d") . '-sitemap-creator.log');
$logger = new Zend_Log($writer);
$indicator = new Indicator();

$batchSize = 45000;
$file = $config['sitemaps']['sitemapPath'] . 'sitemap_';
$baseUrl = $config['sitemaps']['sitemapBaseUrl'];


// Write sitemap files
$xmlWriter = new XMLWriter();
$xmlWriter->openMemory();
$xmlWriter->setIndent(true);
$xmlWriter->setIndentString("   ");

$r = $pdo->query('SELECT COUNT(DISTINCT `id`)
	FROM `_search_all` WHERE `rank` IN ("species", "infraspecies")');
$total = $r->fetchColumn();
$stmt = $config['sitemaps']['naturalKeys'] == 0 ?
    $pdo->prepare(
    	'SELECT DISTINCT `id`, `name_status`, `accepted_taxon_id`
    	FROM `_search_all` WHERE `rank` IN ("species", "infraspecies")
    	LIMIT :offset, :limit') :
    $pdo->prepare(
    	'SELECT DISTINCT t2.`hash` AS `id`, `name_status`, t3.`hash` AS `accepted_taxon_id`
    	FROM `_search_all` AS t1
    	LEFT JOIN `_natural_keys` AS t2 ON t1.`id` = t2.`id`
    	LEFT JOIN `_natural_keys` AS t3 ON t1.`accepted_taxon_id` = t3.`id`
    	WHERE `rank` IN ("species", "infraspecies")
    	LIMIT :offset, :limit');
$fileNr = 0;

for ($offset = 0; $offset < $total; $offset += $batchSize) {
	$fileNr++;
	if (file_exists($file . $fileNr . '.xml')) {
		unlink($file . $fileNr . '.xml');
	}

	$xmlWriter->startDocument('1.0', 'UTF-8');
	$xmlWriter->startElement('urlset');
	$xmlWriter->startAttribute('xmlns');
	$xmlWriter->text('http://www.sitemaps.org/schemas/sitemap/0.9');
	$xmlWriter->endAttribute();

	$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
	$stmt->bindValue(':limit', (int)$batchSize, PDO::PARAM_INT);
	$stmt->execute();

	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		switch ($row['name_status']) {
			case 1:
			case 4:
				$url = $row['id'];
				break;
			case 2:
			case 3:
			case 5:
				$url = $row['id'] . '/synonym/' . $row['accepted_taxon_id'];
				break;
			case 6:
				$url = $row['id'] . '/common/' . $row['accepted_taxon_id'];
				break;
		}
		$xmlWriter->startElement('url');
		$xmlWriter->writeElement('loc', $baseUrl . $url);
		$xmlWriter->endElement();
		if ($offset % 1000 == 0) {
			file_put_contents($file . $fileNr . '.xml', $xmlWriter->flush(true), FILE_APPEND);
		}
	}
	$xmlWriter->endElement();
	file_put_contents($file . $fileNr . '.xml', $xmlWriter->flush(true), FILE_APPEND);
}

// Write index
$file = 'sitemaps/sitemap_index.xml';
if (file_exists($file)) {
	unlink($file);
}

$xmlWriter->startDocument('1.0', 'UTF-8');
$xmlWriter->startElement('sitemapindex');
$xmlWriter->startAttribute('xmlns');
$xmlWriter->text('http://www.sitemaps.org/schemas/sitemap/0.9');
$xmlWriter->endAttribute();
for ($i = 1; $i <= $fileNr; $i++) {
	$xmlWriter->startElement('sitemap');
	$xmlWriter->writeElement('loc', 'sitemap_' . $i . '.xml');
	$xmlWriter->writeElement('lastmod', date('Y-m-d'));
	$xmlWriter->endElement();
}
$xmlWriter->endElement();
file_put_contents($file, $xmlWriter->flush(true), FILE_APPEND);

echo 'done!';
?>