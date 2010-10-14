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
    // Path to sql files
    /*define('PATH', 
       realpath('.').PATH_SEPARATOR.
       'docs_and_dumps'.PATH_SEPARATOR.
       'dumps'.PATH_SEPARATOR.
       'base_scheme'.PATH_SEPARATOR.
       'ac'.PATH_SEPARATOR); Doesn't work; PATH_SEPARATOR returns : on Mac OS X */
    define('PATH', realpath('.').'/docs_and_dumps/dumps/base_scheme/ac/');
    define('DENORMALIZED_TABLES_PATH', 'denormalized_tables/');
    
    // SQL for denormalized tables, .sql omitted!
    define('SCHEMA_SQL', 'denormalized_schema');

    // Names of SQL queries in files, .sql omitted!
    define('SEARCH_ALL', '_search_all');
    define('SEARCH_ALL_COMMON_NAMES', '_search_all_common_names');
    define('SEARCH_DISTRIBUTION', '_search_distribution');
    define('SEARCH_SCIENTIFIC', '_search_scientific');
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
            'path' => PATH.DENORMALIZED_TABLES_PATH,
            'dumpFile' => SEARCH_ALL,
            'message' => 'Filling '.SEARCH_ALL.' table'
        ),
        array(
            'path' => PATH.DENORMALIZED_TABLES_PATH,
            'dumpFile' => SEARCH_DISTRIBUTION,
            'message' => 'Filling '.SEARCH_DISTRIBUTION.' table'
        ),
        array(
            'path' => PATH.DENORMALIZED_TABLES_PATH,
            'dumpFile' => SEARCH_SCIENTIFIC,
            'message' => 'Filling '.SEARCH_SCIENTIFIC.' table'
            
        ),
        array(
            'path' => PATH.DENORMALIZED_TABLES_PATH,
            'dumpFile' => SOURCE_DATABASE_DETAILS,
            'message' => 'Filling '.SOURCE_DATABASE_DETAILS.' table'
        ),
        array(
            'path' => PATH.DENORMALIZED_TABLES_PATH,
            'dumpFile' => SPECIES_DETAILS,
            'message' => 'Filling '.SPECIES_DETAILS.' table'
        ),
        array(
            'path' => PATH.DENORMALIZED_TABLES_PATH,
            'dumpFile' => TAXON_TREE,
            'message' => 'Filling '.TAXON_TREE.' table'
        ),
        array(
            'path' => PATH.DENORMALIZED_TABLES_PATH,
            'dumpFile' => TOTALS,
            'message' => 'Filling '.TOTALS.' table'
        )
    );
    
    // Denormalized tables and their indices
    $tables = array(
        SEARCH_ALL => array('name_element', 'name'),
        SEARCH_DISTRIBUTION => array(),
        SEARCH_SCIENTIFIC => array(
            'kingdom', 'phylum', 'class', 'order', 'superfamily', 
            'family', 'species', 'infraspecies', 'genus,species,infraspecies'
        ),
        SOURCE_DATABASE_DETAILS => array('id'),
        SOURCE_DATABASE_TAXONOMIC_COVERAGE => array('source_database_id'),
        SPECIES_DETAILS => array('taxon_id'),
        TAXON_TREE => array('taxon_id', 'parent_id'),
        TOTALS => array()
    );
    
    echo '<h4>Creating and filling denormalized tables</h4>';
    echo '<p>Depending on your server, this action may takes minutes to
        hours to complete.</p>';
    
    foreach ($files as $file) {
        $start = microtime(true);
        writeSql($file['path'], $file['dumpFile'], $file['message']);
        $runningTime = round(microtime(true) - $start);
        echo "Script took $runningTime seconds to complete</p>";
    };  
    
    $start = microtime(true);
    echo '<p>Adding common names to _search_all table...<br>';
    $sql = file_get_contents(
        PATH.DENORMALIZED_TABLES_PATH.SEARCH_ALL_COMMON_NAMES.'.sql'
    );
    $pdo->query('ALTER TABLE `_search_all` DISABLE KEYS');
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    while ($cn = $stmt->fetch(PDO::FETCH_ASSOC)) {
        insertCommonNameElements($cn);
    }
    $pdo->query('ALTER TABLE `_search_all` ENABLE KEYS');
    $runningTime = round(microtime(true) - $start);
    echo "Script took $runningTime seconds to complete</p>";
    
    echo '<h4>Optimizing denormalized tables</h4>';
    echo '<p>Table columns are trimmed to the minimum size and 
        indices are created.</p>';
    
    foreach ($tables as $table => $indices) {
        echo "<p><b>Processing table $table...</b><br>";
        $stmt = $pdo->prepare(
           'SHOW COLUMNS FROM `'.$table.'` WHERE `Type` LIKE "varchar%"'
        );
        $stmt->execute();
        // Trim all varchar fields to minimum size and create indices
        while ($cl = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $column = $cl['Field'];
            echo 'Shrinking column '.$column.'...<br>';
            $stmt2 = $pdo->prepare(
                'SELECT MAX(LENGTH(`'.$column.'`)) FROM `'.$table.'`'
            );
            $stmt2->execute();
            $maxLength = $stmt2->fetch();
            if ($maxLength[0] == '' || $maxLength[0] == 0) {
                $maxLength[0] = 1;
            }
            $query = 'ALTER TABLE `'.$table.'` CHANGE `'.$column.'` `'.
                $column.'` VARCHAR('.$maxLength[0].') ';
            $cl['Null'] == 'NO' ? $query .= 'NOT NULL' : $query .= 'NULL';
            $query .= ' DEFAULT \''.$cl['Default'].'\'';
            //echo "$query<br>";
            $stmt2 = $pdo->prepare($query);
            $stmt2->execute();
        }
        // Create indices
        foreach ($indices as $index) {
            echo "Adding index to $index...<br>";
            // Combined index
            if (strpos($index, ',') !== false) {
                $query2 = 'ALTER TABLE `'.$table.'` ADD INDEX (';
                $indexParts = explode(',', $index);
                for ($i = 0; $i < count($indexParts); $i++) {
                    $query2 .= '`'.$indexParts[$i].'`,';
                }
                $query2 = substr($query2, 0, -1).')';
                //echo "<b>$query2</b><br>";
                $stmt2 = $pdo->prepare($query2);
                $stmt2->execute();
            // Single index
            } else {
                $query3 = 'ALTER TABLE `'.$table.'` ADD INDEX (`'.$index.'`)';
                //echo "$query3<br>";
                $stmt2 = $pdo->prepare($query3);
                $stmt2->execute();
            }
        }
        // Create fulltext index on distribution
        if ($table == SEARCH_DISTRIBUTION) {
            echo "Adding FULLTEXT index to distribution...<br>";
            $query4 = 'ALTER TABLE `'.$table.'` ADD INDEX 
                FULLINDEX (`distribution`)';
            $stmt2 = $pdo->prepare($query4);
            $stmt2->execute();
        }
        echo '</p>';
    };
    
    echo '<h4>Analyzing denormalized tables</h4><p>';
    foreach ($tables as $table => $indices) {
        echo "Analyzing table $table...<br>";
        $pdo->query('ANALYZE TABLE `'.$table.'`');
    }    
    
    echo '</p><p>Ready!</p>';

    
    
        
    function writeSql($path, $dumpFile, $message) {
        $pdo = DbHandler::getInstance('target');
        echo '<p>'.$message.'...<br>';
        try {
            $sql = file_get_contents($path.$dumpFile.'.sql');
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        } catch (PDOException $e) {
            echo $e->getMessage();
        }   
    }
    
    function insertCommonNameElements($cn) {
        $pdo = DbHandler::getInstance('target');
        $insert = 'INSERT INTO `_search_all` 
            (`id`, `name_element`, `name`, `rank`, `name_status`, 
            `name_status_suffix`, `group`, `source_database`, 
            `source_database_id`, `accepted_taxon_id`) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $cnElements = explode(' ', $cn['name']);
        foreach ($cnElements as $cne) {
            $stmt = $pdo->prepare($insert);
            $stmt->execute(
                array(
                    $cn['id'],
                    strtolower($cne),
                    $cn['name'],
                    $cn['rank'],
                    $cn['name_status'],
                    $cn['name_status_suffix'],
                    $cn['kingdom'],
                    $cn['source_database'],
                    $cn['source_database_id'],
                    $cn['accepted_taxon_id']
                )
            );
        }
    }
    
?>