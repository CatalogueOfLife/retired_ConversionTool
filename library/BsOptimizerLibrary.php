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
function logInvalidRecords ($logger)
{
    createErrorTable();
    $invalidRecords = array(
        array(
            'query' => 'SELECT t1.`record_id`, t1.`name`, t1.`name_code`
                        FROM `taxa` AS t1
                        LEFT JOIN `taxa` AS t2 ON  t1.`parent_id` = t2.`record_id`
                        WHERE t1.`is_accepted_name` = 1 AND
                        t2.`is_accepted_name` = 0',
            'message' => 'Valid taxon with synonym as parent'
        ) /*,
        array(
            'query' => 'SELECT t1.`record_id`, t1.`name`, t1.`name_code`
                        FROM `taxa`AS t1
                        LEFT JOIN `taxa` AS t2 ON t1.`parent_id` = t2.`record_id`
                        WHERE t1.`taxon` = "Infraspecies" AND
                        t2.`taxon` != "Species" AND
                        t1.`is_accepted_name` = 1',
            'message' => 'Valid infraspecies with genus (not species) as parent'
        )*/
    );
    $pdo = DbHandler::getInstance('source');
    foreach ($invalidRecords as $invalid) {
        $stmt = $pdo->prepare($invalid['query']);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            writeToErrorTable($row[0], $row[1], $invalid['message'], $row[2]);
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
          `name_code` varchar(255) NULL,
          `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `message` varchar(150) NOT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;');
}

function handleException ($message, $e = false) {
    global $logger;
    if (!$logger) {
        die('Logger not initialized');
    }
    $message = "\n$message: \n" . ($e ? "Exception: \n" . $e->getMessage() : '');
    $logger->err($message);
    die('<p>' . $message . '</p>');
}

/**
 * Modified version of function in TaxonAbstract.php
 */
function writeToErrorTable ($id, $name, $message, $nameCode = null)
{
    global $logger;
    $pdo = DbHandler::getInstance('target');
    $stmt = $pdo->prepare('INSERT INTO `_conversion_errors` (`id`, `name`, `message`, `name_code`)
        VALUES (?, ?, ?, ?)');
    $stmt->execute(array(
        $id,
        $name,
        $message,
        $nameCode
    ));
    if (!$logger) {
        die('Logger not initialized');
    }
    $m = "\nRecord skipped during conversion: \n" .
        "id: $id\n" .
        "name: $name\n" .
        "name code: $nameCode\n" .
        "reason: $message\n";
    $logger->err($m);
}

/**
 * Writes data from sql dump to database
 */
function writeSql ($path, $dumpFile, $message)
{
    $pdo = DbHandler::getInstance('target');
    echo '<p>' . $message . '...<br>';
    $sql = file_get_contents($path . $dumpFile . '.sql');
    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute();
    } catch (PDOException $e) {
        handleException("Cannot write sql dump", $e);
    }
}

function getOptimizedIndex ($table, $column)
{
    $pdo = DbHandler::getInstance('target');
    $indices = array();
    // Index over multiple columns
    if (strpos($column, ',') !== false) {
        $indexParts = explode(',', $column);
        for ($i = 0; $i < count($indexParts); $i++) {
        	$c = trim($indexParts[$i]);
        	// No fixed size given for index
        	if (strpos($c, '[') === false) {
            	$indices[$c] = getMaxLengthVarChar($table, $c);
        	// Use fixed size for index
        	} else {
        		list($a, $b) = explode('[', $c);
        		$indices[trim($a)] = substr($b, 0, -1);
        	}
        }
    // Single column index
    } else {
	    $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $table . "` WHERE `Field` = '$column'");
	    $stmt->execute();
	    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    	if (isVarCharField($r['Type'])) {
            $indices[$column] = getMaxLengthVarChar($table, $column);
        }
    }
    return $indices;
}

function getMaxLengthVarChar ($table, $column)
{
    $pdo = DbHandler::getInstance('target');
    $stmt = $pdo->prepare('SELECT MAX(LENGTH(`' . $column . '`)) FROM `' . $table . '`');
    $stmt->execute();
    $r = $stmt->fetch(PDO::FETCH_NUM);
    $maxLength = $r[0];
    if ($maxLength == '' || $maxLength == 0) {
        $maxLength = 1;
    }
    return $maxLength;
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
    $query = 'ALTER TABLE `' . $table . '` CHANGE `' . $column . '` `' . $column .
        '` VARCHAR(' . $maxLength[0] . ')  NOT NULL';
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
        try {
            $stmt->execute(array(
                $ne['id'],
                $nameElement
            ));
        } catch (PDOException $e) {
            handleException("Cannot delete name element", $e);
        }
        return;
    }
    // Replace characters to be deleted with space and then remove double spaces
    $nameElement = str_replace($delete_chars, ' ', $nameElement);
    $nameElement = preg_replace('/\s+/', ' ', $nameElement);
    $nameElement = trim($nameElement);
    // Update only if parsed value does not match original value
    if ($nameElement != $ne['name_element']) {
        $stmt = $pdo->prepare($update);
        try {
            $stmt->execute(
                array(
                    $nameElement,
                    $ne['id'],
                    $ne['name_element']
                ));
        } catch (PDOException $e) {
            handleException("Cannot update name element", $e);
        }
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
    $stmt = $pdo->prepare($insert);
    foreach ($cnElements as $cne) {
        try {
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
        } catch (PDOException $e) {
            handleException("Cannot insert common name element", $e);
        }
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
    $stmt = $pdo->prepare($insert);
    foreach ($elements as $nameElement) {
        $ne['name_element'] = $nameElement;
        try {
            $stmt->execute(array_values($ne));
        } catch (PDOException $e) {
            handleException("Cannot insert name element", $e);
        }
    }
}

function updateTaxonTreeName ($id)
{
    $pdo = DbHandler::getInstance('target');
    $update = 'UPDATE `' . TAXON_TREE . '` SET `name` = ? WHERE `taxon_id` = ' . $id;
    $stmt = $pdo->prepare($update);
    try {
        $stmt->execute(array(
            getNameFromSearchAll($id)
        ));
    } catch (PDOException $e) {
        handleException("Cannot update taxon tree name", $e);
    }
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
    try {
        $stmt->execute(array(
            $source_database_id,
            $taxon_id,
            $sector_number
        ));
    } catch (PDOException $e) {
        handleException("Cannot insert taxonomic coverage", $e);
    }
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
    $query = 'UPDATE `taxonomic_coverage`
        SET `point_of_attachment` = 1
        WHERE `source_database_id` = ?
        AND `sector` = ?
        AND `taxon_id` = ?';
    $stmt = $pdo->prepare($query);
    foreach ($rows as $row) {
        try {
            $stmt->execute(
                array(
                    $source_database_id,
                    $sector,
                    $row['taxon_id']
                ));
        } catch (PDOException $e) {
            handleException("Cannot insert taxonomic coverage", $e);
        }
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
    global $higherTaxa;
    $pdo = DbHandler::getInstance('target');
    $name_elements = explode(' ', $tt['name']);
    $nr_elements = count($name_elements);
    // Higher taxon
    if (in_array(strtolower($tt['rank']), $higherTaxa) &&
        ($nr_elements == 1 || $tt['name'] == 'Not assigned')) {
        // Top level
        $query = 'SELECT `source_database_id`
                  FROM `' . SEARCH_SCIENTIFIC . '`
                  WHERE `' . strtolower($tt['rank']) . '` = ?
                  AND `source_database_id` != 0 ';
        $params = array(
            $tt['name']
        );
        // Extend for any rank but top level
        if ($tt['parent_id'] != 0) {
            if (empty($tt['parent_rank'])) {
                throw new Exception($tt['rank'] . ' ' . $tt['name'] . ' has no parent.');
            }
            $query .= 'AND `' . strtolower($tt['parent_rank']) . '` = ? ';
            $params[] = $tt['parent_name'];
        }
    }
    // Species
    else if ($nr_elements == 2) {
        $query = 'SELECT `source_database_id`
                  FROM `' . SEARCH_SCIENTIFIC . '`
                  WHERE `genus` = ?
                  AND `species` = ?
                  AND `infraspecies` = ""
                  AND `source_database_id` != 0
                  AND `status` IN (1,4)';
        $params = array(
            $name_elements[0],
            $name_elements[1]
        );
    }
    // Infraspecies; query _search_all for this
    else {
        $query = 'SELECT `source_database_id`
                  FROM `' . SEARCH_ALL . '`
                  WHERE `name` = ?
                  AND `rank` = ?
                  AND `name_status` IN (1,4)';
        $params = array(
            $tt['name'],
            $tt['rank']
        );
    }
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        //$result = $stmt->fetchAll(PDO::FETCH_NUM);
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        	$result[$row[0]] = $row[0];
        }
        return isset($result) ? $result : array();
    }
    catch (Exception $e) {
        echo $e->getMessage() . '<br>' . $query;
        return array();
    }
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

function updateTaxonTree ($taxon_id, $source_database_ids)
{
    $pdo = DbHandler::getInstance('target');
    foreach ($source_database_ids as $source_database_id) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . SOURCE_DATABASE_TO_TAXON_TREE_BRANCH . ' (`source_database_id`, `taxon_tree_id`) VALUES (?, ?)');
            $stmt->execute(
                array(
                    $source_database_id,
                    $taxon_id
                ));
        }
        catch (Exception $e) {
            echo $e->getMessage();
        }
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

function capitalizeHybridName ($name) {
	preg_match('/^[^A-Za-z]+([A-Za-z])/', $name, $matches);
	return strtoupper($name[strlen($matches[0])-1]);
}

function updateHybrid ($table, array $name, array $id)
{
	$pdo = DbHandler::getInstance('target');
	$update = 'UPDATE `' . $table . '` SET `' . key($name) . '` = ? WHERE `' . key($id) . '` = ?';
	$stmt = $pdo->prepare($update);
	$stmt->execute(array(
		reset($name),
		reset($id)
	));
}

function createTaxonTreeFunction ()
{
	// show function status
	$pdo = DbHandler::getInstance('target');
	$sql =
		'DROP FUNCTION IF EXISTS getTotalSpeciesFromChildren;
		CREATE FUNCTION getTotalSpeciesFromChildren(
			X INT( 10 )
		) RETURNS INT( 10 ) READS SQL DATA BEGIN DECLARE tot INT;

		SELECT SUM( total_species )
		INTO tot
		FROM _taxon_tree
		WHERE parent_id = X;

		RETURN (
			tot
		);

		END;';
	$pdo->query($sql);
}

function insertNaturalKey ($d)
{
    $pdo = DbHandler::getInstance('target');
    $insert = 'INSERT INTO `' . NATURAL_KEYS .
        '` (`id`, `hash`, `name`, `author`, `name_code`, `accepted`, `status`)
        VALUES (?, ?, ?, ?, ?, ?, ?)';
    $stmt = $pdo->prepare($insert);
    $stmt->execute($d);
}

function copyEstimates () {
   $pdo = DbHandler::getInstance('estimates');
   $q = 'SELECT * FROM `estimates`';
   $stmt = $pdo->query($q);
   while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = getTaxonTreeId(array($r['name'], $r['rank'], $r['kingdom']));
        if ($id) {
            updateTaxonTreeEstimates(array($r['estimate'], $r['source'], $id));
        }
   }
}

function getTaxonTreeId ($p)
{
    $pdo = DbHandler::getInstance('target');
    $q = 'SELECT t1.`taxon_id` AS `id` FROM `_taxon_tree` AS t1
        LEFT JOIN `_search_scientific` AS t2 ON t1.`taxon_id` = t2.`id`
        WHERE t1.`name` = ? AND t1.`rank` = ? AND t2.`kingdom` = ?';
    $stmt = $pdo->prepare($q);
    $stmt->execute($p);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return !empty($r) ? $r['id'] : false;
}

function updateTaxonTreeEstimates ($p)
{
    $pdo = DbHandler::getInstance('target');
    $q = 'UPDATE `_taxon_tree` SET `total_species_estimation` = ?,
        `estimate_source` = ? WHERE `taxon_id` = ?';
    $stmt = $pdo->prepare($q);
    $stmt->execute($p);
}

function getViruses ()
{
    $pdo = DbHandler::getInstance('source');
    $q = 'SELECT t1.`record_id` AS `id`, t2.`name` AS `genus`, t1.`name` AS `species` FROM `taxa` AS t1
        LEFT JOIN `taxa` AS t2 ON t1.`parent_id` = t2.`record_id`
        WHERE t1.`HierarchyCode` LIKE "virus%" AND t1.`taxon` = "species"';
    $stmt = $pdo->prepare($q);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updateViruses ($table, $viruses)
{
    $pdo = DbHandler::getInstance('target');
    switch ($table) {
        case SEARCH_ALL:
            $q = 'UPDATE ' . SEARCH_ALL . ' SET `name` = ? WHERE `id` = ?';
            $stmt = $pdo->prepare($q);
            foreach ($viruses as $row) {
                $stmt->execute(array($row['genus'] . ' ' . $row['species'], $row['id']));
            }
            break;
        case SEARCH_SCIENTIFIC:
            $q = 'UPDATE ' . SEARCH_SCIENTIFIC . ' SET `species` = ? WHERE `id` = ?';
            $stmt = $pdo->prepare($q);
            foreach ($viruses as $row) {
                $stmt->execute(array($row['species'], $row['id']));
            }
            break;
        case SPECIES_DETAILS:
            $q = 'UPDATE ' . SPECIES_DETAILS . ' SET `species_name` = ? WHERE `taxon_id` = ?';
            $stmt = $pdo->prepare($q);
            foreach ($viruses as $row) {
                $stmt->execute(array($row['species'], $row['id']));
            }
            break;
        case TAXON_TREE:
            $q = 'UPDATE ' . TAXON_TREE . ' SET `name` = ? WHERE `taxon_id` = ?';
            $stmt = $pdo->prepare($q);
            foreach ($viruses as $row) {
                 $stmt->execute(array($row['genus'] . ' ' . $row['species'], $row['id']));
            }
            break;
    }
}

function updateFossilParents ()
{
    $pdo = DbHandler::getInstance('target');
    for ($i = 0; $i < 10; $i++) {
        $q = 'SELECT t2.`taxon_id`, COUNT(t1.`is_extinct`), t2.`number_of_children`
            FROM ' . TAXON_TREE . ' AS t1
            LEFT JOIN ' . TAXON_TREE . ' AS t2 ON t1.`parent_id` = t2.`taxon_id`
            WHERE t1.`is_extinct` = 1 AND t1.`delete_me` = ?
            GROUP BY t2.`taxon_id`
            HAVING COUNT(t1.`is_extinct`) = t2.`number_of_children`';
        $stmt = $pdo->prepare($q);
        $stmt->execute(array($i));
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $ids[] = $row[0];
        }

        if (!isset($ids)) {
            return;
        }

        $q = 'UPDATE ' . TAXON_TREE . ' SET `has_modern` = ?, `has_preholocene` = ?,
                `is_extinct` = ?, `delete_me` = ?
            WHERE `taxon_id` IN (' . implode(',', $ids) . ')';
        $stmt = $pdo->prepare($q);
        $stmt->execute(array(0, 1, 1, ($i + 1)));

        unset($ids);
    }
}

function getNameStatuses ()
{
    $pdo = DbHandler::getInstance('target');
    $stmt = $pdo->query('SELECT * FROM `scientific_name_status`');
    $d[0] = 'higher taxon';
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d[$row['id']] = $row['name_status'];
    }
     $d[6] = 'common name';
    return $d;
}

function getSynonymNameCode ($id)
{
    $pdo = DbHandler::getInstance('target');
    $stmt = $pdo->prepare('SELECT `original_id` FROM `synonym` WHERE `id` = ?');
    $stmt->execute(array($id));
    return $stmt->fetchColumn();
}

function getLogs ()
{
    $files = array();
    $dir = dirname(__FILE__) . '/../logs';
    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? $http = 'https://' : $http = 'http://';
    $d = dir($dir);
    $i = 0;
    while (false !== ($file = $d->read())) {
        $i++;
        if (is_numeric(substr($file, 0, 4)) && !is_dir($file)) {
            $size = getDownloadSize("logs/$file");
            if ($size > 0) {
                $files[substr($file, 0, 13) . $i] = "<a href='logs/$file'>$file</a> (" . $size . ')';
            }
        }
    }
    $d->close();
    krsort($files);
    return $files;
}

function clearSiteMaps ()
{
    $files = array();
    $dir = dirname(__FILE__) . '/../sitemaps';
    $d = dir($dir);
    while (false !== ($file = $d->read())) {
        if (!is_dir($file) && file_exists($dir . '/' . $file)) {
            unlink($dir . '/' . $file);
        }
    }
    $d->close();
}


function getDownloadSize ($path)
{
    $sizeKb = filesize($path) / 1024;
    $size = round($sizeKb, 1) . ' KB';
    if ($sizeKb > 999) {
        $size = round($sizeKb / 1024, 1) . ' MB';
    }
    return $size;
}

function getTaxonRank ($id) {
    $pdo = DbHandler::getInstance('target');
    $stmt = $pdo->prepare('SELECT `rank` FROM `' . SEARCH_ALL . '` WHERE `id` = ?');
    $stmt->execute(array($id));
    return $stmt->fetchColumn();
}

function getAcceptedNameForCommonName ($id)
{
    $pdo = DbHandler::getInstance('target');
    $stmt = $pdo->prepare('SELECT `name_status_suffix`, `name_status_suffix_suffix`
        FROM `' . SEARCH_ALL . '` WHERE `id` = ?');
    $stmt->execute(array($id));
    $row = $stmt->fetchAll(PDO::FETCH_NUM);
    if ($row) {
        return $row[0][0] . ' ' . html_entity_decode($row[0][1]);
    }
    return null;
}

function fputcsv2 ($fh, array $fields, $del = ',', $sep = '"', $mysql_null = false)
{
    $del_esc = preg_quote($del, '/');
    $sep_esc = preg_quote($sep, '/');

    $output = array();
    foreach ($fields as $field) {
        if ($field === null && $mysql_null) {
            $output[] = 'NULL';
            continue;
        }
        $field = cleanString($field);
        $output[] = preg_match("/(?:${del_esc}|${sep_esc}|\s)/", $field) ? ($sep . str_replace(
            $sep, $sep . $sep, $field) . $sep) : $field;
    }

    fwrite($fh, join($del, $output) . "\n");
}

function cleanString ($str)
{
    // Characters to remove
    $delete = array("\r", "\n", "\r\n", "\\");
    // Characters to transfer to space
    $space = array("\t" );
    // Characters to find...
    $find = array('""');
    //... and replace with...
    $replace = array('"');
    return str_replace($find, $replace,
        str_replace($space, ' ',
        str_replace($delete, '', $str)
    ));
}

function hashCoL ($s, $removeDiacritical = true) {
    if ($removeDiacritical) {
        $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    }
    return md5(strtolower(str_replace(' ', '_', $s)));
}