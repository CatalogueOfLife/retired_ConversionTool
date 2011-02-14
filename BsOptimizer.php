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
define('SOURCE_DATABASE_TAXONOMIC_COVERAGE', '_source_database_taxonomic_coverage');
define('SPECIES_DETAILS', '_species_details');
define('TAXON_TREE', '_taxon_tree');
define('TOTALS', '_totals');
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
        'name_status'
    ), 
    SEARCH_DISTRIBUTION => array(), 
    SEARCH_SCIENTIFIC => array(
        'kingdom', 
        'phylum', 
        'class', 
        'order', 
        'superfamily', 
        'family', 
        'species', 
        'infraspecies', 
        'genus,species,infraspecies'
    ), 
    SEARCH_FAMILY => array(), 
    SOURCE_DATABASE_DETAILS => array(
        'id'
    ), 
    SOURCE_DATABASE_TAXONOMIC_COVERAGE => array(
        'source_database_id'
    ), 
    SPECIES_DETAILS => array(
        'taxon_id'
    ), 
    TAXON_TREE => array(
        'taxon_id', 
        'parent_id', 
        'name'
    ), 
    TOTALS => array()
);

// Some columns are not shrunken immediately because some post-processing needs to take place first
$postponed_tables = array(
    SEARCH_ALL => array(
        'name_element'
    ), 
    SEARCH_DISTRIBUTION => array(
        'name', 
        'kingdom'
    ), 
    SEARCH_SCIENTIFIC => array(
        'author', 
        'accepted_species_name', 
        'accepted_species_author', 
        'source_database_name'
    ), 
    TAXON_TREE => array(
        'name'
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
    '.'
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

echo '<p>First denormalized tables are created and filled. Next indices are created.</p>';

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
    insertCommonNameElements($cn, SEARCH_ALL);
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
    cleanNameElements($ne, SEARCH_ALL, $delete_name_elements, $delete_chars);
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
    splitAndInsertNameElements($ne, SEARCH_ALL);
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

echo 'Updating ' . SEARCH_SCIENTIFIC . '...</p>';
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
        AND sa.`name_status` != 6
    ),
    dss.`accepted_species_author` = (
        SELECT DISTINCT sa.`name_suffix` 
        FROM `' . SEARCH_ALL . '` AS sa 
        WHERE dss.`accepted_species_id` = sa.`id` 
        AND sa.`name_status` != 6
    ),
    dss.`accepted_species_name` = (
        SELECT DISTINCT sa.`name` 
        FROM `' . SEARCH_ALL . '` AS sa 
        WHERE dss.`accepted_species_id` = sa.`id` 
        AND sa.`name_status` != 6
    )';
$stmt = $pdo->prepare($query);
$stmt->execute();

echo 'Updating ' . TAXON_TREE . '...<br>';
$query = 'SELECT `taxon_id` FROM `' . TAXON_TREE . '` 
          WHERE `rank` NOT IN ("kingdom", "phylum", "class", "order", "family", "superfamily", "genus", "species")';
$stmt = $pdo->prepare($query);
$stmt->execute();
while ($id = $stmt->fetchColumn()) {
    updateTaxonTreeName($id, TAXON_TREE, SEARCH_ALL);
}

echo '<p><b>Shinking columns of post-processed tables</b><br>';
foreach ($postponed_tables as $table => $columns) {
    foreach ($columns as $cl) {
        echo 'Shrinking column ' . $cl . ' in table ' . $table . '...<br>';
        shrinkVarChar($table, array(
            'Field' => $cl
        ));
    }
}

// Last minute change to revert taxonomic coverage in _source_database_details
$pdo->query('ALTER TABLE `' . SOURCE_DATABASE_DETAILS . '` ADD `taxonomic_coverage` LONGTEXT NOT NULL');
$stmt = $pdo->prepare('SELECT `id` FROM `' . SOURCE_DATABASE_DETAILS . '`');
$stmt->execute();
while ($id = $stmt->fetchColumn()) {
    copyTaxonomicCoverage($id, SOURCE_DATABASE_DETAILS);
}

echo '</p><p><b>Analyzing denormalized tables</b><br>';
foreach ($tables as $table => $indices) {
    echo "Analyzing table $table...<br>";
    $pdo->query('ANALYZE TABLE `' . $table . '`');
}

echo '</p><p>Ready!</p>';

?>