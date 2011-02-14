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

function cleanNameElements ($ne, $search_all, $delete_name_elements = array(), $delete_chars = array())
{
    $pdo = DbHandler::getInstance('target');
    $delete = 'DELETE FROM `' . $search_all . '` WHERE `id` = ? AND `name_element` = ?';
    $update = 'UPDATE `' . $search_all . '` SET `name_element` = ? WHERE `id` = ? AND `name_element` = ?';
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

function insertCommonNameElements ($cn, $search_all)
{
    $pdo = DbHandler::getInstance('target');
    $insert = 'INSERT INTO `' . $search_all . '` 
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

function splitAndInsertNameElements ($ne, $search_all)
{
    $pdo = DbHandler::getInstance('target');
    $insert = 'INSERT INTO `' . $search_all . '` 
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

function updateTaxonTreeName ($id, $taxon_tree, $search_all)
{
    $pdo = DbHandler::getInstance('target');
    $update = 'UPDATE `' . $taxon_tree . '` SET `name` = ? WHERE `taxon_id` = ' . $id;
    $stmt = $pdo->prepare($update);
    $stmt->execute(array(
        getNameFromSearchAll($id, $search_all)
    ));
}

function getNameFromSearchAll ($id, $search_all)
{
    $pdo = DbHandler::getInstance('target');
    $query = 'SELECT `name` FROM `' . $search_all . '` WHERE `id` = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(
        $id
    ));
    return $stmt->fetchColumn();
}

function copyTaxonomicCoverage ($id, $source_database_details)
{
    $pdo = DbHandler::getInstance('target');
    $update = 'UPDATE `' . $source_database_details . '` SET `taxonomic_coverage` = ? WHERE `id` = ' . $id;
    $stmt = $pdo->prepare($update);
    $stmt->execute(array(
        getTaxonomicCoverage($id)
    ));

}

function getTaxonomicCoverage ($id)
{
    $pdo = DbHandler::getInstance('source');
    $query = 'SELECT `taxonomic_coverage` FROM `databases` WHERE `record_id` = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(
        $id
    ));
    return $stmt->fetchColumn();
}