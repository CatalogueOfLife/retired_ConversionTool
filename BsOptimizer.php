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
alwaysFlush();

// Path to sql files
/*define('PATH', 
       realpath('.').PATH_SEPARATOR.
       'docs_and_dumps'.PATH_SEPARATOR.
       'dumps'.PATH_SEPARATOR.
       'base_scheme'.PATH_SEPARATOR.
       'ac'.PATH_SEPARATOR); Doesn't work; PATH_SEPARATOR returns : on Mac OS X */
define('PATH', realpath('.') . '/docs_and_dumps/dumps/base_scheme/ac/');
define('DENORMALIZED_TABLES_PATH', 'denormalized_tables/');

// SQL for denormalized tables, .sql omitted!
define('SCHEMA_SQL', 'denormalized_schema');

// Names of SQL queries in files, .sql omitted!
define('SEARCH_ALL', '_search_all');
define('SEARCH_ALL_COMMON_NAMES', '_search_all_common_names');
define('SEARCH_DISTRIBUTION', '_search_distribution');
define('SEARCH_SCIENTIFIC', '_search_scientific');
define('SEARCH_FAMILY', '_search_family');
define('SOURCE_DATABASE_DETAILS', '_source_database_details');
define('SOURCE_DATABASE_TO_TAXON_TREE_BRANCH', '_source_database_to_taxon_tree_branch');
define('SPECIES_DETAILS', '_species_details');
define('TAXON_TREE', '_taxon_tree');
define('TOTALS', '_totals');
define('IMPORT_SOURCE_DATABASE_QUALIFIERS', '__import_source_database_qualifiers');
define('IMPORT_SPECIES_ESTIMATE', '__import_species_estimate');
$files = array(
    array(
        'path' => PATH, 
        'dumpFile' => SCHEMA_SQL, 
        'message' => 'Creating denormalized tables'
    ), 
    array(
        'path' => PATH . DENORMALIZED_TABLES_PATH, 
        'dumpFile' => SEARCH_ALL, 
        'message' => 'Filling ' . SEARCH_ALL . ' table'
    ), 
    array(
        'path' => PATH . DENORMALIZED_TABLES_PATH, 
        'dumpFile' => SEARCH_DISTRIBUTION, 
        'message' => 'Filling ' . SEARCH_DISTRIBUTION . ' table'
    ), 
    array(
        'path' => PATH . DENORMALIZED_TABLES_PATH, 
        'dumpFile' => SEARCH_SCIENTIFIC, 
        'message' => 'Filling ' . SEARCH_SCIENTIFIC . ' table'
    ), 
    array(
        'path' => PATH . DENORMALIZED_TABLES_PATH, 
        'dumpFile' => SEARCH_FAMILY, 
        'message' => 'Filling ' . SEARCH_FAMILY . ' table'
    ), 
    array(
        'path' => PATH . DENORMALIZED_TABLES_PATH, 
        'dumpFile' => SOURCE_DATABASE_DETAILS, 
        'message' => 'Filling ' . SOURCE_DATABASE_DETAILS . ' table'
    ), 
    array(
        'path' => PATH . DENORMALIZED_TABLES_PATH, 
        'dumpFile' => SPECIES_DETAILS, 
        'message' => 'Filling ' . SPECIES_DETAILS . ' table'
    ), 
    array(
        'path' => PATH . DENORMALIZED_TABLES_PATH, 
        'dumpFile' => TAXON_TREE, 
        'message' => 'Filling ' . TAXON_TREE . ' table'
    ), 
    array(
        'path' => PATH . DENORMALIZED_TABLES_PATH, 
        'dumpFile' => TOTALS, 
        'message' => 'Filling ' . TOTALS . ' table'
    )
);

// Denormalized tables and their indices
$tables = array(
    SEARCH_ALL => array(
        'id', 
        'name_element', 
        'name', 
        'rank', 
        'name_status', 
        'accepted_taxon_id'
    ), 
    SEARCH_DISTRIBUTION => array(), 
    SEARCH_SCIENTIFIC => array(
        'id', 
        'kingdom', 
        'phylum', 
        'class', 
        'order', 
        'superfamily', 
        'family', 
        'species', 
        'infraspecies', 
        'genus,species,infraspecies', 
        'accepted_species_id'
    ), 
    SEARCH_FAMILY => array(), 
    SOURCE_DATABASE_DETAILS => array(
        'id'
    ), 
    SPECIES_DETAILS => array(
        'taxon_id', 
        'kingdom_id', 
        'phylum_id', 
        'class_id', 
        'order_id', 
        'superfamily_id', 
        'family_id', 
        'genus_id', 
        'source_database_id'
    ), 
    TAXON_TREE => array(
        'taxon_id', 
        'parent_id', 
        'name', 
        'rank'
    ), 
    TOTALS => array()
);

// Some columns are not shrunken immediately because some post-processing needs to take place first
$postponed_tables = array(
    SEARCH_ALL => array(
        'name_element' => 'varchar'
    ), 
    SEARCH_DISTRIBUTION => array(
        'name' => 'varchar', 
        'kingdom' => 'varchar'
    ), 
    SEARCH_SCIENTIFIC => array(
        'author' => 'varchar', 
        'accepted_species_name' => 'varchar', 
        'accepted_species_author' => 'varchar', 
        'source_database_name' => 'varchar'
    ), 
    SPECIES_DETAILS => array(
        'point_of_attachment_id' => 'varchar'
    ), 
    TAXON_TREE => array(
        'name' => 'varchar',
        'total_species_estimation' => 'int',
        'total_species' => 'int',
        'estimate_source' => 'varchar'
    ),
    SOURCE_DATABASE_TO_TAXON_TREE_BRANCH => array(
        'source_database_id' => 'int',
        'taxon_tree_id' => 'int'
    ), 
    SOURCE_DATABASE_DETAILS => array(
        'coverage' => 'varchar',
        'completeness' => 'int',
        'confidence' => 'int'
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

$config = parse_ini_file('config/AcToBs.ini', true);
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
$indicator = new Indicator();

// For 1.7: First test if import tables for species estimates and database qualifiers have been filled; if not, abort.
$empty = check17ImportTables($config['target']['dbname']);
if (!empty($empty)) {
    count($empty) == 1 ? $table = $empty[0] . ' is' : $table = 'these tables are';
    echo '<p>Currently the species estimate per higher taxon and database qualifiers are taken 
    from the tables<br>' . IMPORT_SPECIES_ESTIMATE . ' and ' . IMPORT_SOURCE_DATABASE_QUALIFIERS . '.</p>
    <p style="color: red; font-weight: bold;">This script can only proceed if ' . $table . ' present and not empty.</p>
    <p>If you are importing into a v1.6 database, you first should upgrade to the v1.7 structure.<br>
    The upgrade script is found at <b>docs_and_dumps/dumps/base_scheme/ac/upgrade_1-6_to_1-7.sql</b><br>
    SQL dumps to fill the import tables are found at <b>docs_and_dumps/dumps/base_scheme/ac/import_data_1-7</b>.</p>';
    exit('</body></html>');
}

echo '<p>First denormalized tables are created, filled and reduced to minimum size. Next indices are created.<br>
      Finally taxonomic coverage is processed from free text field to true database table to determine 
      points of attachment for each GSD sector.</p>';

foreach ($files as $file) {
    $start = microtime(true);
    writeSql($file['path'], $file['dumpFile'], $file['message']);
    $runningTime = round(microtime(true) - $start);
    echo "Script took $runningTime seconds to complete</p>";
}

$start = microtime(true);
echo '<p>Adding common names to ' . SEARCH_ALL . ' table...<br>';
$sql = file_get_contents(PATH . DENORMALIZED_TABLES_PATH . SEARCH_ALL_COMMON_NAMES . '.sql');
$pdo->query('ALTER TABLE `' . SEARCH_ALL . '` DISABLE KEYS');
$stmt = $pdo->prepare($sql);
$stmt->execute();
while ($cn = $stmt->fetch(PDO::FETCH_ASSOC)) {
    insertCommonNameElements($cn);
}
$pdo->query('ALTER TABLE `' . SEARCH_ALL . '` ENABLE KEYS');
$runningTime = round(microtime(true) - $start);
echo "Script took $runningTime seconds to complete<br></p>";

echo '<p><br>Optimizing denormalized tables. Table columns are trimmed to 
        the minimum size and indices are created.</p>';

foreach ($tables as $table => $indices) {
    echo "<p><b>Processing table $table...</b><br>";
    $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '`');
    $stmt->execute();
    // Trim all varchar and int fields to minimum size
    while ($cl = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isVarCharField($cl['Type']) || isIntField($cl['Type'])) {
            // Postpone shrinking of a few columns until table creation is complete
            if (array_key_exists(
                $table, $postponed_tables) && in_array($cl['Field'], 
                $postponed_tables[$table])) {
                continue;
            }
            echo 'Shrinking column ' . $cl['Field'] . '...<br>';
            if (isVarCharField($cl['Type'])) {
                shrinkVarChar($table, $cl);
            }
            else {
                shrinkInt($table, $cl);
            }
        }
    }
    // Create indices
    foreach ($indices as $index) {
        echo "Adding index to $index...<br>";
        // Combined index
        if (strpos($index, ',') !== false) {
            $query2 = 'ALTER TABLE `' . $table . '` ADD INDEX (';
            $indexParts = explode(',', $index);
            for ($i = 0; $i < count($indexParts); $i++) {
                $query2 .= '`' . $indexParts[$i] . '`,';
            }
            $query2 = substr($query2, 0, -1) . ')';
        }
        // Single index
        else {
            $query2 = 'ALTER TABLE `' . $table . '` ADD INDEX (`' . $index . '`)';
        }
        $stmt2 = $pdo->prepare($query2);
        $stmt2->execute();
    }
    // Create fulltext index on distribution
    if ($table == SEARCH_DISTRIBUTION) {
        echo "Adding FULLTEXT index to distribution...<br>";
        $query4 = 'ALTER TABLE `' . $table . '` ADD FULLTEXT (`distribution`)';
        $stmt2 = $pdo->prepare($query4);
        $stmt2->execute();
    }
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
echo '&nbsp;&nbsp;&nbsp; Splitting rows...<br>';
$query = 'SELECT * FROM `' . SEARCH_ALL . '` WHERE `delete_me` = 1';
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

echo 'Updating ' . SEARCH_DISTRIBUTION . '...<br>';
$query = 'UPDATE `' . SEARCH_DISTRIBUTION . '` AS sd, `' . SEARCH_ALL . '` AS sa SET sd.`name` = sa.`name`, sd.`kingdom` = sa.`group` WHERE sd.`accepted_species_id` = sa.`id`';
$stmt = $pdo->prepare($query);
$stmt->execute();

echo 'Updating ' . SEARCH_SCIENTIFIC . '...<br>';
$query = 'UPDATE `' . SEARCH_SCIENTIFIC . '` AS dss 
    SET dss.`author` = IF(dss.`accepted_species_id` = "", (
        SELECT `string` 
        FROM `taxon_detail` 
        LEFT JOIN `author_string` ON `author_string_id` = `author_string`.`id` 
        WHERE `taxon_id` = dss.`id`),
        (SELECT `string` 
        FROM `synonym` 
        LEFT JOIN `author_string` ON `synonym`.`author_string_id` = `author_string`.`id` 
        WHERE `synonym`.`id` = dss.`id`
    )
    ),
        dss.`status` = IF(dss.`accepted_species_id` = "", (
        SELECT `scientific_name_status_id` 
        FROM `taxon_detail` 
        WHERE `taxon_id` = dss.`id`), (
            SELECT `scientific_name_status_id` 
            FROM `synonym` 
            WHERE `synonym`.`id` = dss.`id`
        )
    ),
    dss.`source_database_name` = (
        SELECT DISTINCT sa.`source_database_name` 
        FROM `' . SEARCH_ALL . '` AS sa 
        WHERE dss.`id` = sa.`id` 
        AND sa.`name_status` = dss.`status`
    ),
    dss.`accepted_species_author` = (
        SELECT DISTINCT sa.`name_suffix` 
        FROM `' . SEARCH_ALL . '` AS sa 
        WHERE dss.`accepted_species_id` = sa.`id` 
        AND sa.`name_status` = dss.`status`
    ),
    dss.`accepted_species_name` = (
        SELECT DISTINCT sa.`name` 
        FROM `' . SEARCH_ALL . '` AS sa 
        WHERE dss.`accepted_species_id` = sa.`id` 
        AND sa.`name_status` = dss.`status`
    )';
$stmt = $pdo->prepare($query);
$stmt->execute();

echo 'Updating ' . TAXON_TREE . '...<br>';
$query = 'SELECT `taxon_id` FROM `' . TAXON_TREE . '` 
          WHERE `rank` NOT IN ("kingdom", "phylum", "class", "order", "family", "superfamily", "genus", "species")';
$stmt = $pdo->prepare($query);
$stmt->execute();
while ($id = $stmt->fetchColumn()) {
    updateTaxonTreeName($id);
}

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
echo 'Deleting temporary indices from ' . SPECIES_DETAILS . '...<br>';
foreach (array(
    'kingdom_id', 
    'phylum_id', 
    'class_id', 
    'order_id', 
    'superfamily_id', 
    'family_id', 
    'genus_id', 
    'source_database_id'
) as $index) {
    $stmt = $pdo->prepare('ALTER TABLE ' . SPECIES_DETAILS . ' DROP INDEX ' . $index);
    $stmt->execute();
}

echo '</p><p><b>New 1.7 functionality</b><br>
      Adding species count and source databases to ' . TAXON_TREE . '...<br>';
$clean = 'UPDATE ' . TAXON_TREE . ' SET `total_species` = 0, `total_species_estimation` = 0, `estimate_source` = ""; 
          TRUNCATE TABLE `' . SOURCE_DATABASE_TO_TAXON_TREE_BRANCH . '`;';
$stmt = $pdo->prepare($clean);
$stmt->execute();
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
$indicator->init($stmt->rowCount(), 150, 500);
while ($tt = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $source_database_ids = getSourceDatabaseIds($tt);
    $species_count = countSpecies($tt);
    updateTaxonTree($tt, $source_database_ids, $species_count);
    $indicator->iterate();
}

echo '</p><p>Importing species estimates from the ' . IMPORT_SPECIES_ESTIMATE . ' table...<br>';
$clean = 'UPDATE ' . TAXON_TREE . ' SET `total_species_estimation` = 0, `estimate_source` = ""';
$stmt = $pdo->prepare($clean);
$stmt->execute();
$query = 'SELECT `species_estimate`, `source`, `name`, `rank` 
          FROM ' . IMPORT_SPECIES_ESTIMATE;
$stmt = $pdo->prepare($query);
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_NUM);
foreach ($result as $est) {
    $update = 'UPDATE ' . TAXON_TREE . ' SET 
               `total_species_estimation` = ?, 
               `estimate_source` = ? 
               WHERE `name` = ? 
               AND `rank` = ?';
    $stmt = $pdo->prepare($update);
    $stmt->execute($est);
}
echo 'Importing qualifiers from the ' . IMPORT_SOURCE_DATABASE_QUALIFIERS . ' table...<br>';
$clean = 'UPDATE ' . SOURCE_DATABASE_DETAILS . ' SET `coverage` = "", `completeness` = 0, `confidence` = 0;';
$stmt = $pdo->prepare($clean);
$stmt->execute();
$query = 'SELECT `coverage`, `completeness`, `confidence`, `source_database_name` 
          FROM ' . IMPORT_SOURCE_DATABASE_QUALIFIERS;
$stmt = $pdo->prepare($query);
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_NUM);
foreach ($result as $sdd) {
    $update = 'UPDATE ' . SOURCE_DATABASE_DETAILS . ' SET 
               `coverage` = ?, 
               `completeness` = ?,
               `confidence` = ?  
               WHERE `short_name` = ?';
    $stmt = $pdo->prepare($update);
    $stmt->execute($sdd);
}


echo '</p><p><b>Shrinking columns of post-processed tables</b><br>';
foreach ($postponed_tables as $table => $columns) {
    foreach ($columns as $cl => $type) {
        echo 'Shrinking column ' . $cl . ' in table ' . $table . '...<br>';
        if ($type == 'varchar') {
            shrinkVarChar($table, array(
                'Field' => $cl
            ));
        } else {
            shrinkInt($table, array(
                'Field' => $cl
            ));
        }
    }
}

echo '</p><p><b>Analyzing denormalized tables</b><br>';
foreach ($tables as $table => $indices) {
    echo "Analyzing table $table...<br>";
    $pdo->query('ANALYZE TABLE `' . $table . '`');
}

echo '</p><p>Ready!</p>';

?>
</body>
</html>