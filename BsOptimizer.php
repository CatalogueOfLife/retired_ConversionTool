<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<title>Base Scheme Optimizer</title>
</head>

<body style="font: 12px verdana; width: 700px;">
    <h3>Base Scheme Optimizer</h3>
<?php
ini_set('memory_limit', '1024M');
set_include_path('library' . PATH_SEPARATOR . get_include_path());

require_once 'library/BsOptimizerLibrary.php';
require_once 'DbHandler.php';
require_once 'Indicator.php';
require_once 'library/Zend/Log/Writer/Stream.php';
require_once 'library/Zend/Log.php';
alwaysFlush();

// Path to sql files
//define('PATH', realpath('.') . '/docs_and_dumps/dumps/base_scheme/ac/');
define('PATH', '/Users/ruud/ETI/Zend workbenches/Current/4D4Life Base Scheme (github)/ac/');
define('DENORMALIZED_TABLES_PATH', 'denormalized_tables/');

// SQL for denormalized tables, .sql omitted!
define('SCHEMA_SQL', 'denormalized_schema');

// Names of SQL queries in files, .sql omitted!
define('SEARCH_ALL', '_search_all');
define('SEARCH_ALL_COMMON_NAMES', '_search_all_common_names');
define('TAXON_TREE_SPECIES_TOTALS', '_taxon_tree_species_totals');
define('SEARCH_DISTRIBUTION', '_search_distribution');
define('SEARCH_SCIENTIFIC', '_search_scientific');
define('SEARCH_FAMILY', '_search_family');
define('SOURCE_DATABASE_DETAILS', '_source_database_details');
define('SOURCE_DATABASE_TO_TAXON_TREE_BRANCH', '_source_database_to_taxon_tree_branch');
define('SPECIES_DETAILS', '_species_details');
define('TAXON_TREE', '_taxon_tree');
define('TOTALS', '_totals');
define('NATURAL_KEYS', '_natural_keys');

$files = array(
    array(
        'path' => PATH,
        'dumpFile' => SCHEMA_SQL,
        'message' => 'Creating denormalized tables'
    ),
    array(
        'path' => PATH . DENORMALIZED_TABLES_PATH,
        'dumpFile' => SEARCH_ALL,
        'message' => 'Populating ' . SEARCH_ALL . ' table'
    ),
    array(
        'path' => PATH . DENORMALIZED_TABLES_PATH,
        'dumpFile' => SEARCH_DISTRIBUTION,
        'message' => 'Populating ' . SEARCH_DISTRIBUTION . ' table'
    ),
    array(
        'path' => PATH . DENORMALIZED_TABLES_PATH,
        'dumpFile' => SEARCH_SCIENTIFIC,
        'message' => 'Populating ' . SEARCH_SCIENTIFIC . ' table'
    ),
    array(
        'path' => PATH . DENORMALIZED_TABLES_PATH,
        'dumpFile' => SEARCH_FAMILY,
        'message' => 'Populating ' . SEARCH_FAMILY . ' table'
    ),
    array(
        'path' => PATH . DENORMALIZED_TABLES_PATH,
        'dumpFile' => SOURCE_DATABASE_DETAILS,
        'message' => 'Populating ' . SOURCE_DATABASE_DETAILS . ' table'
    ),
    array(
        'path' => PATH . DENORMALIZED_TABLES_PATH,
        'dumpFile' => SPECIES_DETAILS,
        'message' => 'Populating ' . SPECIES_DETAILS . ' table'
    ),
    array(
        'path' => PATH . DENORMALIZED_TABLES_PATH,
        'dumpFile' => TAXON_TREE,
        'message' => 'Populating ' . TAXON_TREE . ' table'
    ),
    array(
        'path' => PATH . DENORMALIZED_TABLES_PATH,
        'dumpFile' => TOTALS,
        'message' => 'Populating ' . TOTALS . ' table'
    )
);

// Denormalized tables and their indices
// Indices on multiple columns should be written as 'column1, column2, etc'
// Add [size] in case a partial index is needed, e.g. 'column1[10], column2[10], etc'
// Use [0] for int columns in partial indices, so these are properly parsed
$tables = array(
    SEARCH_ALL => array(
        'name_element',
        'name',
        'rank',
        'name_status',
        'accepted_taxon_id',
        'is_extinct'
    ),
    SEARCH_DISTRIBUTION => array(
        'accepted_species_id',
        'is_extinct'
    ),
    SEARCH_SCIENTIFIC => array(
        'kingdom',
        'phylum',
        'class',
        'order',
        'superfamily',
        'family',
        'subgenus',
        'species',
        'infraspecies',
        'genus, species, infraspecies',
        'status',
        'accepted_species_id',
        'accepted_species_id[0], genus[15], subgenus[10], species[10], infraspecies[10]',
        'accepted_species_id[0], genus[10]',
        'accepted_species_id[0], infraspecies[10]',
        'accepted_species_id[0], species[10]',
        'accepted_species_id[0], subgenus[10]',
        'is_extinct'
    ),
    SOURCE_DATABASE_DETAILS => array(
    ),
    SPECIES_DETAILS => array(
        'kingdom_id',
        'phylum_id',
        'class_id',
        'order_id',
        'superfamily_id',
        'family_id',
        'genus_id',
        'subgenus_id',
        'source_database_id'
    ),
    TAXON_TREE => array(
        'name',
        'rank'
    )
);

// Indices that should be removed when ready
$tempIndices = array(
    SPECIES_DETAILS => array(
        'kingdom_id',
        'phylum_id',
        'class_id',
        'order_id',
        'superfamily_id',
        'family_id',
        'genus_id',
        'source_database_id'
    ),
    SEARCH_DISTRIBUTION => array(
        'accepted_species_id'
    ),
    SEARCH_ALL => array(
        'temp'
    ),
    SEARCH_SCIENTIFIC => array(
        'subgenus'
    )
);

// Name elements in _search_all table to be discarded
$delete_name_elements = array(
    'sp.',
    'subsp.',
    'sp',
    'subsp.',
    'spec',
    'singular',
    'plural'
);

// Characters in name elements in _search_all table to be discarded
$delete_chars = array(
    '(',
    ')',
    '=',
    '?',
    '+',
    '.',
    ',',
    ';',
    PHP_EOL
);

$higherTaxa = array(
    'genus',
    'family',
    'superfamily',
    'order',
    'class',
    'phylum',
    'kingdom'
);



if (isset($argv) && isset($argv[1])) {
    $config = parse_ini_file($argv[1], true);
}
else {
    $config = parse_ini_file('config/AcToBs.ini', true);
}

foreach ($config as $k => $v) {
    $o = array();
    if (isset($v["options"])) {
        $options = explode(",", $v["options"]);
        foreach ($options as $option) {
            $parts = explode("=", trim($option));
            $o[$parts[0]] = $parts[1];
        }
        DbHandler::createInstance($k, $v, $o);
    }
}
$pdo = DbHandler::getInstance('target');
$writer = new Zend_Log_Writer_Stream('logs/' . date("Y-m-d") . '-basescheme-optimizer.log');
$logger = new Zend_Log($writer);
$indicator = new Indicator();

$scriptStart = microtime(true);






echo '<p>First denormalized tables are created and indices are created for the denormalized tables.
        Taxonomic coverage is processed from free text field to a dedicated database table to determine
        points of attachment for each GSD sector. Finally species estimates and source databases
        are linked to the tree.</p>';

foreach ($files as $file) {
    $start = microtime(true);
    writeSql($file['path'], $file['dumpFile'], $file['message']);
    $runningTime = round(microtime(true) - $start);
    echo "Script took $runningTime seconds to complete</p>";
}

$start = microtime(true);
createTaxonTreeFunction();
echo '<p>Adding species totals to ' . TAXON_TREE . ' table...<br>';
$sql = file_get_contents(PATH . DENORMALIZED_TABLES_PATH . TAXON_TREE_SPECIES_TOTALS . '.sql');
$stmt = $pdo->query($sql);
$runningTime = round(microtime(true) - $start);
echo "Script took $runningTime seconds to complete<br></p>";

$start = microtime(true);
echo '<p>Setting fossil flags to higher taxa in ' . TAXON_TREE . ' table...<br>';
//
$runningTime = round(microtime(true) - $start);
echo "Script took $runningTime seconds to complete<br></p>";


$start = microtime(true);
echo '<p>Adding common name elements to ' . SEARCH_ALL . ' table...<br>';
$sql = file_get_contents(PATH . DENORMALIZED_TABLES_PATH . SEARCH_ALL_COMMON_NAMES . '.sql');
$stmt = $pdo->prepare($sql);
$stmt->execute();
while ($cn = $stmt->fetch(PDO::FETCH_ASSOC)) {
    insertCommonNameElements($cn);
}
$runningTime = round(microtime(true) - $start);
echo "Script took $runningTime seconds to complete<br></p>";

echo '<p><br>Indices are created for denormalized tables.</p>';
foreach ($tables as $table => $indices) {
    echo "<p><b>Processing table $table...</b><br>";
    $pdo->query('ALTER TABLE `' . $table . '` DISABLE KEYS');
    foreach ($indices as $index) {
        $indexParameters = getOptimizedIndex($table, $index);
        $query = 'ALTER TABLE `' . $table . '` ADD INDEX (';
        // Index on int column
        if (empty($indexParameters)) {
            $indexType = 'int';
            $query .= '`' . $index . '`';
            // Index on single varchar column
        }
        else if (count($indexParameters) == 1) {
            $indexType = 'varchar (' . $indexParameters[$index] . ')';
            $query .= '`' . $index . '` (' . $indexParameters[$index] . ')';
            // Index on combined column (results in varchar)
        }
        else {
            $indexType = 'multi-column';
            $index = '';
            foreach ($indexParameters as $column => $size) {
                // Use [0] to disable partial index on int columns
                $query .= '`' . $column . ($size != 0 ? '` (' . $size . '), ' : '`, ');
                $index .= $column . ', ';
            }
            $query = substr($query, 0, -2);
            $index = substr($index, 0, -2);
        }
        $query .= ')';
        echo 'Adding ' . $indexType . " index on $index...<br>";
        $stmt2 = $pdo->prepare($query);
        $stmt2->execute();
    }
    // Create fulltext index on distribution
    if ($table == SEARCH_DISTRIBUTION) {
        echo "Adding fulltext index to distribution...<br>";
        $query = 'ALTER TABLE `' . $table . '` ADD FULLTEXT (`distribution`)';
        $stmt2 = $pdo->prepare($query);
        $stmt2->execute();
    }
    $pdo->query('ALTER TABLE `' . $table . '` ENABLE KEYS');
    echo '</p>';
}


echo '<p><b>Post-processing ' . SEARCH_ALL . ', ' . SEARCH_DISTRIBUTION . ', ' . SEARCH_SCIENTIFIC . ' and ' . TAXON_TREE . ' tables</b><br>';
echo 'Updating ' . SEARCH_ALL . '...<br>';
echo '&nbsp;&nbsp;&nbsp; Cleaning name elements...<br>';
$query = 'SELECT `id`, `name_element` FROM `' . SEARCH_ALL . '`';
$stmt = $pdo->prepare($query);
$stmt->execute();
while ($ne = $stmt->fetch(PDO::FETCH_ASSOC)) {
    cleanNameElements($ne, $delete_name_elements, $delete_chars);
}

echo '&nbsp;&nbsp;&nbsp; Creating temporary column to mark rows that should be processed...<br>';
$query = 'ALTER TABLE `' . SEARCH_ALL . '` ADD `delete_me` TINYINT( 1 ) NOT NULL , ADD INDEX ( `delete_me` ) ';
$stmt = $pdo->prepare($query);
$stmt->execute();
echo '&nbsp;&nbsp;&nbsp; Marking rows with name elements containing spaces...<br>';
$query = 'UPDATE `' . SEARCH_ALL . '` SET `delete_me` = ? WHERE `name_element` LIKE "% %" and `name_element` != "not assigned"';
$stmt = $pdo->prepare($query);
$stmt->execute(array(
    1
));

echo '&nbsp;&nbsp;&nbsp; Splitting ' . $stmt->rowCount() . ' rows...<br>';
$query = 'SELECT `id`, `name_element`, `name`, `name_suffix`, `rank`, `name_status`,
             `name_status_suffix`, `name_status_suffix_suffix`, `group`,
             `source_database_name`, `source_database_id`, `accepted_taxon_id`
          FROM `' . SEARCH_ALL . '`
          WHERE `delete_me` = 1';
$stmt = $pdo->prepare($query);
$stmt->execute();
while ($ne = $stmt->fetch(PDO::FETCH_ASSOC)) {
    splitAndInsertNameElements($ne);
}

echo '&nbsp;&nbsp;&nbsp; Deleting original rows...<br>';
$query = 'DELETE FROM `' . SEARCH_ALL . '` WHERE `delete_me` = ?';
$stmt = $pdo->prepare($query);
$stmt->execute(array(
    1
));

echo '&nbsp;&nbsp;&nbsp; Dropping temporary column...<br>';
$query = 'ALTER TABLE `' . SEARCH_ALL . '` DROP `delete_me`';
$stmt = $pdo->prepare($query);
$stmt->execute();

$pdo->query("SET SESSION sql_mode = '';");
echo '&nbsp;&nbsp;&nbsp; Creating common name entries without spaces...<br>';
$query = 'INSERT INTO `' . SEARCH_ALL . '` (
  		SELECT DISTINCT NULL, LOWER(REPLACE(`name`, " ", "")) AS `name_element`,
  		`name`, `name_suffix`, `rank`, 6, `name_status_suffix`, `name_status_suffix_suffix`,
  		`group`, `source_database_name`, `source_database_id`, `accepted_taxon_id`,
        `has_preholocene`, `has_modern`, `is_extinct`
  		FROM `' . SEARCH_ALL . '`
  		WHERE `name_status` = 6 AND `name` LIKE "% %"
  	)';
$stmt = $pdo->prepare($query);
$stmt->execute();
$pdo->query("SET SESSION sql_mode = 'TRADITIONAL';");

echo 'Updating ' . SEARCH_DISTRIBUTION . '...<br>';
$query = 'UPDATE `' . SEARCH_DISTRIBUTION . '` AS sd, `' . SEARCH_ALL . '` AS sa
    SET sd.`name` = sa.`name`, sd.`kingdom` = sa.`group` WHERE sd.`accepted_species_id` = sa.`id`';
$stmt = $pdo->prepare($query);
$stmt->execute();

echo 'Updating ' . SEARCH_SCIENTIFIC . '...<br>';
$queries = array(
    'UPDATE `' . SEARCH_SCIENTIFIC . '` AS dss
     SET dss.`author` = IF(dss.`accepted_species_id` = "",
  		  (
  	      	  SELECT `string`
  		      FROM `taxon_detail`
  		      LEFT JOIN `author_string` ON `author_string_id` = `author_string`.`id`
          	  WHERE `taxon_id` = dss.`id`
  		  ),
          (
	  		  SELECT `string`
		      FROM `synonym`
		      LEFT JOIN `author_string` ON `synonym`.`author_string_id` = `author_string`.`id`
		      WHERE `synonym`.`id` = dss.`id`
  		  )
      ),
      dss.`status` = IF(dss.`accepted_species_id` = "",
  		  (
	  		  SELECT `scientific_name_status_id`
	          FROM `taxon_detail`
	          WHERE `taxon_id` = dss.`id`
  		  ),
  		  (
	  		  SELECT `scientific_name_status_id`
	          FROM `synonym`
	          WHERE `synonym`.`id` = dss.`id`
          )
      );',

    'UPDATE `' . SEARCH_SCIENTIFIC . '` AS dss
	 JOIN `' . SEARCH_ALL . '` AS sa ON dss.`id` = sa.`id`
     SET dss.`source_database_name` = sa.`source_database_name`
  	 WHERE dss.`id` = sa.`id`
  	 AND sa.`name_status` = dss.`status`;',

    'UPDATE `' . SEARCH_SCIENTIFIC . '` AS dss
	 JOIN `' . SEARCH_ALL . '` AS sa ON dss.`id` = sa.`id`
  	 SET dss.`accepted_species_author` =  sa.`name_status_suffix_suffix`,
     	 dss.`accepted_species_name` =  sa.`name_status_suffix`
  	 WHERE dss.`accepted_species_id` > 0
  	 AND sa.`name_status` = dss.`status`;'
);

foreach ($queries as $query) {
    $pdo->query("SET SESSION sql_mode = '';");
    $stmt = $pdo->prepare($query);
    $stmt->execute();
}

echo 'Updating ' . TAXON_TREE . '...<br>';
$query = 'SELECT `taxon_id` FROM `' . TAXON_TREE . '`
    WHERE `rank` NOT IN ("kingdom", "phylum", "class", "order", "family", "superfamily", "genus", "species")';
$stmt = $pdo->prepare($query);
$stmt->execute();
while ($id = $stmt->fetchColumn()) {
    updateTaxonTreeName($id);
}

echo '</p><p><b>Fixing virus names</b><br/>';
echo 'Getting viruses from source database...<br/>';
$viruses = getViruses();
echo 'Updating ' . SEARCH_ALL . '...<br>';
updateViruses(SEARCH_ALL, $viruses);
echo 'Updating ' . SEARCH_SCIENTIFIC . '...<br>';
updateViruses(SEARCH_SCIENTIFIC, $viruses);
echo 'Updating ' . SEARCH_SCIENTIFIC . '...<br>';
updateViruses(SPECIES_DETAILS, $viruses);
echo 'Updating ' . TAXON_TREE . '...<br>';
updateViruses(TAXON_TREE, $viruses);


echo '</p><p><b>Converting taxonomic coverage column to database table</b><br>';
echo 'Converting text to table...<br>';
$pdo->query('TRUNCATE TABLE `taxonomic_coverage`');
$stmt = $pdo->prepare('SELECT `id`, `taxonomic_coverage` FROM `source_database`');
$stmt->execute();
while ($tc = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sectors = explode(';', $tc['taxonomic_coverage']);
    foreach ($sectors as $key => $sector) {
        $sector_number = $key + 1; // Up with 1 to start with 1 rather than 0
        $taxa = explode(' ', $sector);
        foreach ($taxa as $taxon) {
            $taxon = str_replace($delete_chars, '', $taxon);
            if ($taxon_id = getIdFromSearchAll($taxon)) {
                insertTaxonomicCoverage($tc['id'], $taxon_id, $sector_number);
            }
        }
    }
}
echo 'Finding points of attachment...<br>';
$stmt = $pdo->prepare('SELECT `source_database_id` FROM `taxonomic_coverage`');
$stmt->execute();
while ($source_database_id = $stmt->fetchColumn()) {
    $tc = getTaxonomicCoverage($source_database_id);
    $points_of_attachments = determinePointsOfAttachment($tc);
    foreach ($points_of_attachments as $sector => $taxonomic_rank_id) {
        updatePointsOfAttachment($source_database_id, $sector, $taxonomic_rank_id);
    }
}

echo 'Updating ' . SPECIES_DETAILS . ' with points of attachments...<br>';
$stmt = $pdo->prepare('SELECT `id`, `abbreviated_name` FROM `source_database` ORDER BY `abbreviated_name`');
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo '&nbsp;&nbsp;&nbsp; Processing ' . $row[1] . ' source database...<br>';
    $points_of_attachments = getPointsOfAttachment($row[0]);
    foreach ($points_of_attachments as $taxon_id) {
        setPointsOfAttachment($row[0], $taxon_id);
    }
}
echo '</p><p>Adding source databases to ' . TAXON_TREE . '...<br>';
$query = 'SELECT t1.`taxon_id`,
              t1.`rank`,
              t1.`name`,
              t1.`parent_id`,
              t2.`rank` AS parent_rank,
              t2.`name` AS parent_name
            FROM `' . TAXON_TREE . '` AS t1
            LEFT JOIN `' . TAXON_TREE . '` AS t2 ON (t1.`parent_id` = t2.`taxon_id`)';
$stmt = $pdo->prepare($query);
$stmt->execute();
$indicator->init($stmt->rowCount(), 75, 1000);
while ($tt = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $source_database_ids = getSourceDatabaseIds($tt);
    updateTaxonTree($tt['taxon_id'], $source_database_ids);
    $indicator->iterate();
}

echo '</p><p><b>Capitalizing valid hybrids in denormalized tables</b><br>';
echo 'Updating ' . SEARCH_ALL . '...<br>';
$query = 'SELECT `id`, `name` FROM `' . SEARCH_ALL . '`
      		WHERE `name` REGEXP "^[^A-Za-z]+([A-Za-z])" AND `name_status` IN (0,1,4)';
$stmt = $pdo->prepare($query);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    updateHybrid(SEARCH_ALL, array(
        'name' => capitalizeHybridName($row['name'])
    ), array(
        'id' => $row['id']
    ));
}
echo 'Updating ' . TAXON_TREE . '...<br>';
$query = 'SELECT `taxon_id`, `name` FROM `' . TAXON_TREE . '`
  			WHERE `name` REGEXP "^[^A-Za-z]+([A-Za-z])"';
$stmt = $pdo->prepare($query);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    updateHybrid(TAXON_TREE, array(
        'name' => capitalizeHybridName($row['name'])
    ), array(
        'taxon_id' => $row['taxon_id']
    ));
}
echo 'Updating ' . SPECIES_DETAILS . '...<br>';
$query = 'SELECT `taxon_id`, `genus_name` FROM `' . SPECIES_DETAILS . '`
  			WHERE `genus_name` REGEXP "^[^A-Za-z]+([A-Za-z])" AND `status` IN (0, 1, 4)';
$stmt = $pdo->prepare($query);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    updateHybrid(SPECIES_DETAILS, array(
        'genus_name' => capitalizeHybridName($row['genus_name'])
    ), array(
        'taxon_id' => $row['taxon_id']
    ));
}


// Natural keys for accepted (infra)species
echo '</p><p><b>Creating natural keys</b><br>Creating keys for valid (infra)species...<br>';
$query = 'SELECT t1.`id`, t1.`family`, t1.`genus`, t1.`subgenus`, t1.`species`,
        t1.`infraspecies`, t1.`status`, t1.`infraspecific_marker`, t1.`author`, t2.`original_id`
    FROM `' . SEARCH_SCIENTIFIC . '` AS t1
    LEFT JOIN `taxon` AS t2 ON t1.`id` = t2.`id`
    WHERE t1.`species` != "" AND t1.`accepted_species_id` = 0';
$stmt = $pdo->prepare($query);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $name = $row['genus'] . (!empty($row['subgenus']) ? ' (' . $row['subgenus'] . ')' : '') . ' ' . $row['species'] . (!empty($row['infraspecies']) ? ' ' . $row['infraspecies'] : '');
    // hash is combination of kingdom, name, status, author and infraspecific marker
    $hash = md5($row['family'] . $name . $row['author'] . $row['infraspecific_marker'] . $row['status']);
    insertNaturalKey(array(
        $row['id'],
        $hash,
        $name,
        $row['author'],
        $row['original_id'],
        1
    ));
}

// Natural keys for synonyms
echo 'Creating keys for synonyms...<br>';
$query = 'SELECT `id`, `family`, `genus`, `subgenus`, `species`, `infraspecies`,
        `status`, `infraspecific_marker`, `author`, `accepted_species_name`, `accepted_species_author`
    FROM `' . SEARCH_SCIENTIFIC . '`
    WHERE `species` != "" AND `accepted_species_id` > 0';
$stmt = $pdo->prepare($query);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $name = $row['genus'] . (!empty($row['subgenus']) ? ' (' . $row['subgenus'] . ')' : '') .
        ' ' . $row['species'] . (!empty($row['infraspecies']) ? ' ' . $row['infraspecies'] : '');
    // hash is combination of kingdom, name, status, author, infraspecific marker, accepted name and author
    $hash = md5($row['family'] . $name . $row['author'] . $row['infraspecific_marker'] .
        $row['status'] . $row['accepted_species_name'] . $row['accepted_species_author']);
    insertNaturalKey(array(
        $row['id'],
        $hash,
        $name,
        $row['author'],
        null,
        0
    ));
}

// Natural keys for higher taxa
echo 'Creating keys for higher taxa...<br>';
$query = 'SELECT `id`, `kingdom`, `genus`,  `family`, `superfamily`, `order`, `class`, `phylum`
    FROM `' . SEARCH_SCIENTIFIC . '`
    WHERE `species` = "" AND `subgenus` = ""';
$stmt = $pdo->prepare($query);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    foreach ($higherTaxa as $rank) {
        if (!empty($row[$rank])) {
            // hash is combination of kingdom, rank and taxon fro family and up
            // and family, rank and taxon for genera
            $topLevel = $rank == 'genus' ? $row['family'] : $row['kingdom'];
            $hash = md5($topLevel . $rank . $row[$rank]);
            if ($row[$rank] != 'Not assigned') {
                insertNaturalKey(array(
                    $row['id'],
                    $hash,
                    $row[$rank],
                    null,
                    null,
                    1
                ));
            }
            break;
        }
    }
}


// Natural keys for common names
echo 'Creating keys for common names...<br>';
$query = 'SELECT DISTINCT t1.`id`, t1.`name` AS `common_name`, t1.`name_suffix` AS `language`,
     t1.`name_status_suffix` AS `scientific_name`, t1.`name_status_suffix_suffix` AS `author`,
     t2.`family`
    FROM `' . SEARCH_ALL . '` AS t1
    LEFT JOIN `' . SEARCH_SCIENTIFIC . '` AS t2 ON t1.`accepted_taxon_id` = t2.`id`
    WHERE t1.`name_status` = 6 AND t1.`id` != 0';
$stmt = $pdo->prepare($query);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $hash = md5($row['family'] . $row['common_name'] . $row['language'] . $row['scientific_name'] . $row['author']);
    insertNaturalKey(array(
        $row['id'],
        $hash,
        $row['common_name'],
        null,
        null,
        0
    ));
}

/*
	$ht = array('kingdom', 'phylum', 'class', 'order', 'superfamily', 'family', 'genus');
	$c = mysql_connect('localhost', 'root', 'root');
	mysql_select_db('bs_latest', $c);
	mysql_query('SET NAMES "utf8"');

	$q = 'select count(`hash`), `hash`, `name` from `_natural_keys`
		where `name` != "Not assigned" group by `hash` having count(`hash`) > 1';
	$r = mysql_query($q);
	while ($row = mysql_fetch_assoc($r)) {
		echo '<p>' . $row['name'] . ', hash: ' . $row['hash'] . "<br>\n";
		$q2 = 'SELECT `id`, `name_code` FROM `_natural_keys` WHERE `hash` = "' . $row['hash'] . '"';
		$r2 = mysql_query($q2) or die(mysql_error() . $q2);
		while ($row2 = mysql_fetch_assoc($r2)) {
			$q3 = 'select * from `_search_scientific` WHERE `id` = ' . $row2['id'];
			$r3 = mysql_query($q3) or die(mysql_error() . $q3);
			$row3 = mysql_fetch_assoc($r3);
			$header = !empty($row2['name_code']) ? 'Name code ' . $row2['name_code'] . ', classification: ' :
				'Classification: ';
			$path = '';
			foreach ($ht as $rank) {
				if ((strpos($row['name'], ' ') === false && $rank == 'genus') || empty($row3[$rank])) {
					continue;
				}
				$path .= $row3[$rank] . ' &gt; ';
			}
			echo $header . (!empty($path) ? substr($path, 0, -6) : '- (common name)') . "<br>\n";
		}
		echo "</p>\n";
	}
*/


echo '</p><p>Adding tree estimates...<br>';
copyEstimates();

echo '</p><p><b>Final housecleaning</b>
    Deleting subgenus from ' . TAXON_TREE . ' AND ' . SEARCH_SCIENTIFIC . '...<br>';
$queries = array(
    'UPDATE `' . TAXON_TREE . '` AS t1
        LEFT JOIN `' . TAXON_TREE . '` AS t2 ON t1.`parent_id` = t2.`taxon_id`
        SET t1.`parent_id` = t2.`parent_id`
        WHERE t2.`rank` = "subgenus";',
    'DELETE FROM `' . TAXON_TREE . '` WHERE `rank` = "subgenus";',
    'DELETE FROM `' . SEARCH_SCIENTIFIC . '` WHERE `subgenus` != "" AND `species` = "";',
    'DELETE FROM `' . SEARCH_ALL . '` WHERE `rank` = "subgenus";'
);
foreach ($queries as $query) {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
}
echo 'Deleting temporary indices...<br>';
foreach ($tempIndices as $table => $indices) {
    foreach ($indices as $index) {
        $stmt = $pdo->prepare('ALTER TABLE ' . $table . ' DROP INDEX IF EXISTS ' . $index);
        $stmt->execute();
    }
}


echo '</p><p><b>Optimizing denormalized tables</b><br>';
foreach ($tables as $table => $indices) {
    echo "Analyzing table $table...<br>";
    $pdo->query('ANALYZE TABLE `' . $table . '`');
}

$totalTime = round(microtime(true) - $scriptStart);
echo '</p><p>Ready! Optimalization took ' . $indicator->formatTime($totalTime) . '.</p>';
?>
</body>
</html>
