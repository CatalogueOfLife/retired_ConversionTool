<?php

/**
 * Initiate this function to flush the cache automatically
 * 
 * Sets various parameters so the cache is always immediately flushed.
 * This obviates the need to add flush()/ob_flush().
 */
function alwaysFlush ()
{
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    for ($i = 0; $i < ob_get_level(); $i++) {
        ob_end_flush();
    }
    ob_implicit_flush(1);
    set_time_limit(0);
}

/**
 * Format exception
 * 
 * Logs and optionally dumps the exception on the screen 
 * in a better readable format.
 * 
 * @param object $e exception to be formatted
 * @returns string
 */
function formatException (Exception $e)
{
    $trace = $e->getTrace();
    $result = 'Exception: "';
    $result .= $e->getMessage();
    $result .= '" @ ';
    if ($trace[0]['class'] != '') {
        $result .= $trace[0]['class'];
        $result .= '->';
    }
    $result .= $trace[0]['function'];
    $result .= '();<br>';
    return $result;
}

/**
 * Shows currently PHP memory use, nicely formatted
 */
function showMemoryUse ()
{
    $unit = array(
        'B', 
        'KB', 
        'MB', 
        'GB', 
        'TB', 
        'PB'
    );
    $memory = memory_get_usage(true);
    return round($memory / pow(1024, ($i = floor(log($memory, 1024)))), 2) . ' ' . $unit[$i];
}

/**
 * Logs taxa that will be skipped during import
 */
function logInvalidRecords ()
{
    createErrorTable();
    $invalidRecords = array(
        array(
            'query' => 'SELECT t1.`record_id`, t1.`name` 
                        FROM `taxa` AS t1 
                        LEFT JOIN `taxa` AS t2 ON  t1.`parent_id` = t2.`record_id`
                        WHERE t1.`is_accepted_name` = 1 AND 
                        t2.`is_accepted_name` = 0', 
            'message' => 'Valid taxon with synonym as parent'
        ), 
        array(
            'query' => 'SELECT t1.`record_id`, t1.`name`  
                        FROM `taxa`AS t1 
                        LEFT JOIN `taxa` AS t2 ON  t1.`parent_id` = t2.`record_id`
                        WHERE t1.`taxon` = "Infraspecies" AND 
                        t2.`taxon` != "Species" AND 
                        t1.`is_accepted_name` = 1', 
            'message' => 'Valid infraspecies with genus (not species) as parent'
        )
    );
    $pdo = DbHandler::getInstance('source');
    foreach ($invalidRecords as $invalid) {
        $stmt = $pdo->prepare($invalid['query']);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            writeToErrorTable($row[0], $row[1], $invalid['message']);
        }
    }
}

/**
 * Creates log table for invalid taxa
 */
function createErrorTable ()
{
    $pdo = DbHandler::getInstance('target');
    $pdo->query(
        'DROP TABLE IF EXISTS `_conversion_errors`;
        CREATE TABLE `_conversion_errors` (
          `id` int(10) unsigned NOT NULL,
          `name` varchar(150) NOT NULL,
          `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `message` varchar(150) NOT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;');
}

/**
 * Modified version of function in TaxonAbstract.php
 */
function writeToErrorTable ($id, $name, $message)
{
    $pdo = DbHandler::getInstance('target');
    $stmt = $pdo->prepare('INSERT INTO `_conversion_errors` (`id`, `name`, `message`) VALUES (?, ?, ?)');
    $stmt->execute(array(
        $id, 
        $name, 
        $message
    ));
}

/**
 * Writes data from sql dump to database
 */
function writeSql ($path, $dumpFile, $message)
{
    $pdo = DbHandler::getInstance('target');
    echo '<p>' . $message . '...<br>';
    try {
        $sql = file_get_contents($path . $dumpFile . '.sql');
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    catch (PDOException $e) {
        echo $e->getMessage();
    }
}

/**
 * Tests if string contains 'varchar'
 */
function isVarCharField ($field)
{
    if (strstr(strtolower($field), 'varchar') != false) {
        return true;
    }
    return false;
}

/**
 * Tests if string contains 'int'
 */
function isIntField ($field)
{
    if (strstr(strtolower($field), 'int') != false) {
        return true;
    }
    return false;
}

/**
 * Finds string with maximum length and adjusts varchar(x) column to this length
 */
function shrinkVarChar ($table, $cl)
{
    $pdo = DbHandler::getInstance('target');
    $column = $cl['Field'];
    $stmt = $pdo->prepare('SELECT MAX(LENGTH(`' . $column . '`)) FROM `' . $table . '`');
    $stmt->execute();
    $maxLength = $stmt->fetch();
    if ($maxLength[0] == '' || $maxLength[0] == 0) {
        $maxLength[0] = 1;
    }
    $query = 'ALTER TABLE `' . $table . '` CHANGE `' . $column . '` `' . $column . '` VARCHAR(' . $maxLength[0] . ')  NOT NULL';
    $stmt = $pdo->prepare($query);
    $stmt->execute();
}

/**
 * Finds largest integer and adjusts to minimal MySQL int type
 */
function shrinkInt ($table, $cl)
{
    $pdo = DbHandler::getInstance('target');
    $column = $cl['Field'];
    $type = 'MEDIUMINT';
    $stmt = $pdo->prepare('SELECT MAX(`' . $column . '`) FROM `' . $table . '`');
    $stmt->execute();
    $max = $stmt->fetch();
    if ($max[0] >= 16777215) {
        return;
    }
    else if ($max[0] <= 255) {
        $type = 'TINYINT';
    }
    else if ($max[0] <= 65535) {
        $type = 'SMALLINT';
    }
    $query = 'ALTER TABLE `' . $table . '` CHANGE `' . $column . '` `' . $column . '` ' . $type . '(' . strlen($max[0]) . ') UNSIGNED NOT NULL';
    $stmt = $pdo->prepare($query);
    $stmt->execute();
}

/**
 * Used to create denormalized table
 */

function cleanNameElements ($ne, $delete_name_elements = array(), $delete_chars = array())
{
    $pdo = DbHandler::getInstance('target');
    $delete = 'DELETE FROM `' . SEARCH_ALL . '` WHERE `id` = ? AND `name_element` = ?';
    $update = 'UPDATE `' . SEARCH_ALL . '` SET `name_element` = ? WHERE `id` = ? AND `name_element` = ?';
    $nameElement = $ne['name_element'];
    // Delete row if name_element matches entry to be deleted
    if (in_array($nameElement, $delete_name_elements)) {
        $stmt = $pdo->prepare($delete);
        $stmt->execute(array(
            $ne['id'], 
            $nameElement
        ));
        return;
    }
    // Replace characters to be deleted with space and then remove double spaces
    $nameElement = str_replace($delete_chars, ' ', $nameElement);
    $nameElement = preg_replace('/\s+/', ' ', $nameElement);
    $nameElement = trim($nameElement);
    // Update only if parsed value does not match original value
    if ($nameElement != $ne['name_element']) {
        $stmt = $pdo->prepare($update);
        $stmt->execute(
            array(
                $nameElement, 
                $ne['id'], 
                $ne['name_element']
            ));
    }
}

function insertCommonNameElements ($cn)
{
    $pdo = DbHandler::getInstance('target');
    $insert = 'INSERT INTO `' . SEARCH_ALL . '` 
            (`id`, `name_element`, `name`, `name_suffix`, `rank`, `name_status`, 
            `name_status_suffix`, `name_status_suffix_suffix`, `group`, 
            `source_database_name`, `source_database_id`, `accepted_taxon_id`) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $cnElements = explode(' ', $cn['name']);
    foreach ($cnElements as $cne) {
        $stmt = $pdo->prepare($insert);
        $stmt->execute(
            array(
                $cn['id'], 
                strtolower($cne), 
                $cn['name'], 
                $cn['name_suffix'], 
                $cn['rank'], 
                $cn['name_status'], 
                $cn['name_status_suffix'], 
                $cn['name_status_suffix_suffix'], 
                $cn['kingdom'], 
                $cn['source_database_name'], 
                $cn['source_database_id'], 
                $cn['accepted_taxon_id']
            ));
    }
}

function splitAndInsertNameElements ($ne)
{
    $pdo = DbHandler::getInstance('target');
    $insert = 'INSERT INTO `' . SEARCH_ALL . '` 
            (`id`, `name_element`, `name`, `name_suffix`, `rank`, `name_status`, 
            `name_status_suffix`, `name_status_suffix_suffix`, `group`, 
            `source_database_name`, `source_database_id`, `accepted_taxon_id`, `delete_me`) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $ne['delete_me'] = 2;
    $elements = explode(' ', $ne['name_element']);
    foreach ($elements as $nameElement) {
        $stmt = $pdo->prepare($insert);
        $ne['name_element'] = $nameElement;
        $stmt->execute(array_values($ne));
    }
}

function updateTaxonTreeName ($id)
{
    $pdo = DbHandler::getInstance('target');
    $update = 'UPDATE `' . TAXON_TREE . '` SET `name` = ? WHERE `taxon_id` = ' . $id;
    $stmt = $pdo->prepare($update);
    $stmt->execute(array(
        getNameFromSearchAll($id)
    ));
}

function getNameFromSearchAll ($id)
{
    $pdo = DbHandler::getInstance('target');
    $query = 'SELECT `name` FROM `' . SEARCH_ALL . '` WHERE `id` = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(
        $id
    ));
    return $stmt->fetchColumn();
}

function getIdFromSearchAll ($name)
{
    $pdo = DbHandler::getInstance('target');
    $query = 'SELECT `id` FROM `' . SEARCH_ALL . '` WHERE `name` = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(
        $name
    ));
    return $stmt->fetchColumn();
}

function insertTaxonomicCoverage ($source_database_id, $taxon_id, $sector_number)
{
    $pdo = DbHandler::getInstance('target');
    $insert = 'INSERT INTO `taxonomic_coverage` (`source_database_id`, `taxon_id`, `sector`) VALUES (?, ?, ?)';
    $stmt = $pdo->prepare($insert);
    $stmt->execute(array(
        $source_database_id, 
        $taxon_id, 
        $sector_number
    ));
}

function getTaxonomicCoverage ($source_database_id)
{
    $pdo = DbHandler::getInstance('target');
    $query = 'SELECT t1.`taxon_id`, t1.`sector`, t2.`taxonomic_rank_id` 
    FROM `taxonomic_coverage` t1 
    LEFT JOIN `taxon` AS t2 ON t1.`taxon_id` = t2.`id` 
    WHERE t1.`source_database_id` = ? 
    ORDER BY t1.`sector`, t2.`taxonomic_rank_id`';
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(
        $source_database_id
    ));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function determinePointsOfAttachment ($tc)
{
    $taxonomic_order = array(
        54, 
        76, 
        6, 
        72, 
        112, 
        17, 
        20
    );
    $old_sector = $old_rank_key = $rank_key = 0;
    $poa = array();
    
    foreach ($tc as $branch) {
        $sector = $branch['sector'];
        $taxonomic_rank_id = $branch['taxonomic_rank_id'];
        $taxon_id = $branch['taxon_id'];
        
        if ($sector != $old_sector) {
            $poa[$sector] = $taxonomic_rank_id;
            $old_rank_key = 0;
        }
        else {
            $rank_key = array_search($taxonomic_rank_id, $taxonomic_order);
            $stored_rank_key = array_search($poa[$sector], $taxonomic_order);
            if ($rank_key >= $stored_rank_key) {
                $poa[$sector] = $taxonomic_rank_id;
            }
        }
        //echo "$taxon_id - $sector - $taxonomic_rank_id - $rank_key - $old_rank_key - $sectors[$sector]<br>";
        $old_rank_key = $rank_key;
        $old_sector = $sector;
    }
    return $poa;
}

function updatePointsOfAttachment ($source_database_id, $sector, $taxonomic_rank_id)
{
    $pdo = DbHandler::getInstance('target');
    // First get all taxa belonging to the specified taxonomic_rank_id...
    $query = 'SELECT t1.`taxon_id` 
    FROM `taxonomic_coverage` t1 
    LEFT JOIN `taxon` AS t2 ON t1.`taxon_id` = t2.`id` 
    WHERE t2.`taxonomic_rank_id` = ? 
    AND t1.`sector` = ? 
    AND t1.`source_database_id` = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(
        $taxonomic_rank_id, 
        $sector, 
        $source_database_id
    ));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ... then update all appropriate records
    foreach ($rows as $row) {
        $query = 'UPDATE `taxonomic_coverage` 
        SET `point_of_attachment` = 1 
        WHERE `source_database_id` = ? 
        AND `sector` = ?
        AND `taxon_id` = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute(
            array(
                $source_database_id, 
                $sector, 
                $row['taxon_id']
            ));
    }
}

function getPointsOfAttachment ($source_database_id)
{
    $pdo = DbHandler::getInstance('target');
    $query = 'SELECT `taxon_id` FROM `taxonomic_coverage` WHERE `point_of_attachment` = 1 AND `source_database_id` = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(
        $source_database_id
    ));
    $result = array();
    while ($id = $stmt->fetchColumn()) {
        $result[] = $id;
    }
    return $result;
}

function setPointsOfAttachment ($source_database_id, $taxon_id)
{
    $pdo = DbHandler::getInstance('target');
    $update = 'UPDATE ' . SPECIES_DETAILS . ' 
    SET `point_of_attachment_id` = ' . $taxon_id . ' 
    WHERE `source_database_id` = ?
    AND (`kingdom_id` = ' . $taxon_id . ' 
    OR `phylum_id` = ' . $taxon_id . ' 
    OR `class_id` = ' . $taxon_id . ' 
    OR `order_id` = ' . $taxon_id . ' 
    OR `superfamily_id` = ' . $taxon_id . ' 
    OR `family_id` = ' . $taxon_id . ' 
    OR `genus_id` = ' . $taxon_id . ')';
    $stmt = $pdo->prepare($update);
    $stmt->execute(array(
        $source_database_id
    ));
}

function getSourceDatabaseIds ($tt)
{
    $pdo = DbHandler::getInstance('target');
    $name_elements = explode(' ', $tt['name']);
    $nr_elements = count($name_elements);
    // Higher taxon
    if ($nr_elements == 1 || $tt['name'] == 'Not assigned') {
        // Top level
        $query = 'SELECT DISTINCT `source_database_id` 
                  FROM ' . SEARCH_SCIENTIFIC . ' 
                  WHERE `' . strtolower($tt['rank']) . '` = ? 
                  AND `source_database_id` != 0 ';
        $params = array(
            $tt['name']
        );
        // Extend for any rank but top level
        if ($tt['parent_id'] != 0) {
            $query .= 'AND `' . strtolower($tt['parent_rank']) . '` = ? ';
            $params[] = $tt['parent_name'];
        }
    }
    // Species
    else if ($nr_elements == 2) {
        $query = 'SELECT DISTINCT `source_database_id` 
                  FROM ' . SEARCH_SCIENTIFIC . ' 
                  WHERE `genus` = ? 
                  AND `species` = ?
                  AND `infraspecies` = "" 
                  AND `source_database_id` != 0 ';
        $params = array(
            $name_elements[0], 
            $name_elements[1]
        );
    }
    // Infraspecies; query _search_all for this
    else {
        $query = 'SELECT DISTINCT `source_database_id` 
                  FROM ' . SEARCH_ALL . ' 
                  WHERE `name` = ? 
                  AND `rank` = ? ';
        $params = array(
            $tt['name'], 
            $tt['rank']
        );
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_NUM);
    return $result ? $result : array();
}

function countSpecies ($tt)
{
    $pdo = DbHandler::getInstance('target');
    $name_elements = explode(' ', $tt['name']);
    $nr_elements = count($name_elements);
    if ($nr_elements == 1 || $tt['name'] == 'Not assigned') {
        $query = 'SELECT COUNT(1) 
                  FROM `' . SEARCH_SCIENTIFIC . '` 
                  WHERE `' . strtolower($tt['rank']) . '` = ? 
                  AND `species` != "" 
                  AND `infraspecies` = "" 
                  AND `accepted_species_id` = 0 ';
        $params = array(
            $tt['name']
        );
        if ($tt['parent_id'] != 0) {
            $query .= 'AND `' . strtolower($tt['parent_rank']) . '` = ? ';
            $params[] = $tt['parent_name'];
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_NUM);
        return $result ? $result[0][0] : 0;
    }
    else if ($nr_elements == 2) {
        return 1;
    }
    return 0;
}

function updateTaxonTree ($tt, $source_database_ids, $species_count = 0)
{
    $pdo = DbHandler::getInstance('target');
    foreach ($source_database_ids as $row) {
        $source_database_id = $row[0];
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . SOURCE_DATABASE_TO_TAXON_TREE_BRANCH . ' (`source_database_id`, `taxon_tree_id`) VALUES (?, ?)');
            $stmt->execute(
                array(
                    $source_database_id, 
                    $tt['taxon_id']
                ));
        }
        catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    try {
        $stmt = $pdo->prepare('UPDATE ' . TAXON_TREE . ' SET `total_species` = ? WHERE `taxon_id` = ?');
        $stmt->execute(array(
            $species_count, 
            $tt['taxon_id']
        ));
    }
    catch (Exception $e) {
        echo $e->getMessage();
    }
}

function checkImportTables ($dbName, $tables)
{
    $pdo = DbHandler::getInstance('target');
    $empty = array();
    foreach ($tables as $table) {
        $stmt = $pdo->query(
            'SELECT COUNT(1) FROM `information_schema`.`tables` 
                             WHERE `table_schema` = "' . $dbName . '" 
                             AND `table_name` = "' . $table . '";');
        if ($stmt->fetchColumn() == 0) {
            $empty[] = $table;
            continue;
        }
        $stmt = $pdo->query('SELECT COUNT(1) FROM `' . $table . '`');
        if ($stmt->fetchColumn() == 0) {
            $empty[] = $table;
        }
    }
    return $empty;
}