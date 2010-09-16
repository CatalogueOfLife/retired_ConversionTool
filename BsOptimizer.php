<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<title>Base Scheme Optimizer</title>
</head>
<body style="font: 12px verdana;">
<h3>Base Scheme Optimizer</h3>

<?php
	require_once 'library.php';
	require_once 'DbHandler.php';
	require_once 'Indicator.php';
	require_once 'converters/Bs/Model/BsOptimizer/ScientificSearch.php';
	
	alwaysFlush();
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
	$indicator = new Indicator();
	
	
	// Fill _search_scientific table
	clearTable('_search_scientific');
/*	$total = getTotalRecords('SELECT COUNT(1) FROM `taxon`');
	$indicator->init($total, 100);
	
	echo "<p>Adding $total valid taxa to _search_scientific table</p>";

	$insert = $pdo->prepare('INSERT INTO `_search_scientific` 
		(`id`, `kingdom`, `phylum`, `class`, `order`, `superfamily`, `family`, `genus`, `subgenus`,
		`species`, `infraspecies`, `author`, `status`, `source_database_id`, `source_database_name`)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

	$stmt = $pdo->prepare('SELECT `id`, `taxonomic_rank_id` as taxonomicRankId, 
		`source_database_id` as sourceDatabaseId FROM `taxon`');
	$stmt->execute();
	while ($taxon = $stmt->fetchObject('ScientificSearch')) {
		$indicator->iterate();
		$taxonomicRank = getTaxonomicRank($taxon->taxonomicRankId);
		$nameElement = upperCaseNameElement($taxonomicRank, getTaxonNameElement($taxon->id));
		setProperty($taxon, $taxonomicRank, $nameElement);
		
		// Loop through all parents to get the complete taxonomic structure
		// Animalia is the top level that should be excluded
		if ($taxonomicRank != 'Animalia') {
			$parentId = getParentId($taxon->id);
			do {
				$parentTaxonomicRank = getTaxonomicRank(
					getTaxonomicRankId($parentId)
				);
				$parentNameElement = upperCaseNameElement(
					$parentTaxonomicRank, getTaxonNameElement($parentId)
				);
				$parentId = getParentId($parentId);
				setProperty($taxon, $parentTaxonomicRank, $parentNameElement);
			} while ($parentId !== false);
		}
		$taxon->author = getTaxonAuthorString($taxonomicRank, $taxon->id);
		$taxon->sourceDatabaseName = getSourceDatabaseName($taxon->sourceDatabaseId);
		if (isSpeciesOrInfraspecies($taxonomicRank)) {
			$taxon->status = getScientificNameStatus(getScientificNameStatusId($taxon->id));
		}
		$insert->execute(array($taxon->id, $taxon->kingdom, $taxon->phylum, $taxon->class, 
			$taxon->order, $taxon->superfamily, $taxon->family, $taxon->genus, $taxon->subgenus,
			$taxon->species, $taxon->infraspecies, $taxon->author, $taxon->status, 
			$taxon->sourceDatabaseId, $taxon->sourceDatabaseName)
		);
		unset($taxon);
		//printObject($taxon);
	}
*/	
	$total = getTotalRecords('SELECT COUNT(1) FROM `synonym`');
	$indicator->init($total, 100);
	
    echo "<p>Adding $total synonyms to _search_scientific table</p>";

	$insert = $pdo->prepare('INSERT INTO `_search_scientific` 
		(`id`, `genus`, `subgenus`,
		`species`, `infraspecies`, `author`, `status`, `accepted_species_id`, 
		`accepted_species_name`, `accepted_species_author`, `source_database_id`, 
		`source_database_name`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

	$stmt = $pdo->prepare('SELECT `id`, `taxon_id` as acceptedSpeciesId, 
		`author_string_id` as authorStringId, `scientific_name_status_id` as scientificNameStatusId
		FROM `synonym` LIMIT 0, 15');
	$stmt->execute();
	while ($synonym = $stmt->fetchObject('ScientificSearch')) {
		//$indicator->iterate();
		setSynonymsNameElements($synonym);
		$synonym->author = getSynonymAuthorString($synonym->authorStringId);
		$synonym->status = getScientificNameStatus($synonym->scientificNameStatusId);

		//$taxonomicRank = getSynonymRank($synonym);

		$acceptedTaxon = array('infraspecies', 'species', 'genus');
		$acceptedTaxonomicRank = getTaxonomicRank(
			getTaxonomicRankId($synonym->acceptedSpeciesId)
		);
		$acceptedNameElement = upperCaseNameElement(
			$acceptedTaxonomicRank, getTaxonNameElement($synonym->acceptedSpeciesId)
		);
		$acceptedTaxon[$acceptedTaxonomicRank] = $acceptedNameElement;
		if ($acceptedTaxonomicRank != 'genus') {
			$parentId = getParentId($synonym->acceptedSpeciesId);
			do {
				$parentTaxonomicRank = getTaxonomicRank(
					getTaxonomicRankId($parentId)
				);
				$parentNameElement = upperCaseNameElement(
					$parentTaxonomicRank, getTaxonNameElement($parentId)
				);
				$parentId = getParentId($parentId);
				$acceptedTaxon[$parentTaxonomicRank] = $parentNameElement;
			} while ($parentTaxonomicRank != 'family');
		}
		$synonym->acceptedSpeciesName = trim(ucfirst($acceptedTaxon['genus']).' '.
			$acceptedTaxon['species'].' '.$acceptedTaxon['infraspecies']);
		$synonym->acceptedSpeciesAuthor = getTaxonAuthorString(
		    $acceptedTaxonomicRank, $synonym->acceptedSpeciesId
		);
			
		printObject($synonym);
		
	}




	function getSynonymRank($synonym) {
		foreach (array('infraspecies', 'species', 'genus') as $rank) {
			if (isset($synonym->$rank)) {
				return $rank;
			}
		}
	}

	function getSynonymAuthorString($authorId) {
		$pdo = DbHandler::getInstance('target');
		$stmt = $pdo->prepare('SELECT `string` FROM `author_string` WHERE `id` = ?');
		$stmt->execute(array($authorId));
        $result = $stmt->fetchColumn(0);
        unset($stmt);
        return $result;
	}
	
	function getSynonymNameElement($scientificNameElementId) {
		$pdo = DbHandler::getInstance('target');
		$stmt = $pdo->prepare('SELECT `name_element` FROM `scientific_name_element` 
			WHERE `id` = ?');
		$stmt->execute(array($scientificNameElementId));
        $result = $stmt->fetchColumn(0);
        unset($stmt);
        return $result;
	}
	
	function setSynonymsNameElements($synonym) {
		$pdo = DbHandler::getInstance('target');
		$stmt = $pdo->prepare('SELECT `taxonomic_rank_id`, `scientific_name_element_id` 
			FROM `synonym_name_element` WHERE `synonym_id` = ?');
		$stmt->execute(array($synonym->id));
		while ($row = $stmt->fetch()) {
			$taxonomicRank = getTaxonomicRank($row[0]);
			$nameElement = upperCaseNameElement($taxonomicRank, getSynonymNameElement($row[1]));
			setProperty($synonym, $taxonomicRank, $nameElement);
		}
		return $synonym;
	}

	function getTaxonomicRankId($taxonId) {
		$pdo = DbHandler::getInstance('target');
		$stmt = $pdo->prepare('SELECT `taxonomic_rank_id` FROM `taxon` WHERE `id` = ?');
		$stmt->execute(array($taxonId));
        $result = $stmt->fetchColumn(0);
        unset($stmt);
        return $result;
	}
	
	function getTaxonomicRank($rankId) {
		if (!$rankId) {
			return false;
		}
		$taxonomicRanks = array(
			54 => 'kingdom',	 	 	 	 	 	 	 
			76 => 'phylum',		 	 	 	 	 	 	 
			6 => 'class',		 	 	 	 	 	 	 
			72 => 'order',	 	 	 	 	 	 	 
			112 => 'superfamily',		 	 	 	 	 	 	 
			17 => 'family',	 	 	 	 	 	 	 
			20 => 'genus',	 	 	 	 	 	 
			96 => 'subgenus',	 	 	 	 	 	 	 
			83 => 'species'
		);
		if (array_key_exists($rankId, $taxonomicRanks)) {
			return $taxonomicRanks[$rankId];
		}
		return 'infraspecies';
	}
	
	function getTaxonNameElement($taxonId) {
		$pdo = DbHandler::getInstance('target');
		$stmt = $pdo->prepare('SELECT t2.`name_element` FROM `taxon_name_element` t1, 
			`scientific_name_element` t2 WHERE t1.`scientific_name_element_id` = t2.`id` 
			AND `taxon_id` = ?');
		$stmt->execute(array($taxonId));
        $result = $stmt->fetchColumn(0);
        unset($stmt);
        return $result;
	}
	
	function getParentId($taxonId) {
		$pdo = DbHandler::getInstance('target');
		$stmt = $pdo->prepare('SELECT `parent_id` FROM `taxon_name_element` WHERE `taxon_id` = ?');
		$stmt->execute(array($taxonId));
        $result = $stmt->fetchColumn(0);
        unset($stmt);
        return $result;
	}
	
	function getTaxonAuthorString($taxonomicRank, $taxonId) {
		// Skip higher taxa
		if (!isSpeciesOrInfraspecies($taxonomicRank)) {
			return null;
		}
		$pdo = DbHandler::getInstance('target');
		$stmt = $pdo->prepare('SELECT t2.`string` FROM `taxon_detail` t1, `author_string` t2 
			WHERE t1.`author_string_id` = t2.`id` AND t1.`taxon_id` = ?');
		$stmt->execute(array($taxonId));
        $result = $stmt->fetchColumn(0);
        unset($stmt);
        return $result;
	}
	
	function getSourceDatabaseName($databaseId) {
		$pdo = DbHandler::getInstance('target');
		$stmt = $pdo->prepare('SELECT `name` FROM `source_database` WHERE `id` = ?');
		$stmt->execute(array($databaseId));
        $result = $stmt->fetchColumn(0);
        unset($stmt);
        return $result;
	}
	
	function getScientificNameStatusId($taxonId) {
		$pdo = DbHandler::getInstance('target');
		$stmt = $pdo->prepare('SELECT `scientific_name_status_id` FROM `taxon_detail` 
			WHERE `taxon_id` = ?');
		$stmt->execute(array($taxonId));
        $result = $stmt->fetchColumn(0);
        unset($stmt);
        return $result;
	}
	
	function getScientificNameStatus($statusId) {
		if (!$statusId) {
			return false;
		}
		$scientificNameStatuses = array(
			1 => 'accepted name',
			2 => 'ambiguous synonym',
			3 => 'misapplied name',
			4 => 'provisionally accepted name',
			5 => 'synonym'
		);
		return $scientificNameStatuses[$statusId];
	}
	
	// General functions
	function clearTable($table) {
		$pdo = DbHandler::getInstance('target');
        $stmt = $pdo->prepare('TRUNCATE TABLE `'.$table.'`');
        $stmt->execute();
	}
	
	function getTotalRecords($query) {
		$pdo = DbHandler::getInstance('target');
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchColumn(0);
        unset($stmt);
        return $result;
	}
	
	function isSpeciesOrInfraspecies($taxonomicRank) {
		if (strpos($taxonomicRank, 'species') !== false) {
			return true;
		}
		return false;
	}
	
	function upperCaseNameElement($taxonomicRank, $nameElement) {
		if (!isSpeciesOrInfraspecies($taxonomicRank)) {
			return ucfirst($nameElement);
		}
		return $nameElement;
	}
	
	function setProperty($object, $property, $value) {
		if (property_exists($object, $property)) {
			$object->$property = $value;
		}
		return $object;
	}
	
	function printObject($object) {
		echo '<pre>';
		print_r($object);
		echo '</pre>';
	}
?>