<?php
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



/////////////// Assembly database functions

function mysqlConnect () {
    $config = parse_ini_file('config/AcToBs.ini', true);
    $link = mysql_connect(
        $config['source']['host'],
        $config['source']['username'],
        $config['source']['password'])
        or die("Error: connection failure: " . mysql_error());
    mysql_select_db($config['source']['dbname']) 
        or die("Error: could not select database: " . mysql_error());
    return $link;
}

function dbName () {
    $config = parse_ini_file('config/AcToBs.ini', true);
    return $config['source']['dbname'];
}

function printErrors ($errors) {
    if (is_array($errors) && !empty($errors)) {
        $output = '<p style="color: red;">';
        foreach ($errors as $error) {
            $output .= $error.'<br>';
        }
        echo $output.'</p>';
    }
}

function checkDatabase () {
    $tables = array("common_names", "databases", "distribution", "families", "references", "scientific_names", 
      "scientific_name_references", "specialists", "sp2000_statuses") ;
    $fields["common_names"] = array("record_id", "name_code", "common_name", "language", "country", "reference_id", 
      "database_id", "is_infraspecies", "reference_code") ;
    $fields["databases"] = array("record_id", "database_name", "database_name_displayed", "database_full_name", 
       "web_site", "organization" , 
      "contact_person", "taxa", "taxonomic_coverage", "abstract", "version", "release_date", "authors_editors", 
      "accepted_species_names", "accepted_infraspecies_names", "species_synonyms", "infraspecies_synonyms", 
      "species_synonyms", "infraspecies_synonyms", "common_names", "total_names") ;
    $fields["distribution"] = array("record_id",  "name_code", "distribution") ;
    $fields["families"] = array("record_id", "kingdom", "phylum", "class", "order", "family", "superfamily", 
      "is_accepted_name", "database_id", "family_code") ;
    $fields["references"] = array("record_id", "author", "year", "title", "source", "database_id", "reference_code") ;
    $fields["scientific_names"] = array("record_id", "name_code", "web_site", "genus", "species", "infraspecies", 
      "infraspecies_marker", "author", "accepted_name_code", "comment", "scrutiny_date", "sp2000_status_id", 
      "database_id", "specialist_id", "family_id", "specialist_code", "family_code", "is_accepted_name") ;
    $fields["scientific_name_references"] = array("record_id", "name_code", "reference_type", "reference_id", 
        "reference_code");
    $fields["specialists"] = array("record_id", "specialist_name", "specialist_code") ;
    $fields["sp2000_statuses"] = array("record_id", "sp2000_status") ;
    
    $link = mysqlConnect();
    $tables_in_database = array() ;
    $sql_query = 'SHOW TABLES FROM `' . dbName() . '`';
    $sql_result = mysql_query($sql_query) or die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
    while ($row = mysql_fetch_row($sql_result)) {
        array_push($tables_in_database,strtolower($row[0])) ;
    }
    $errors_found = array() ;
    foreach ($tables as $table) {
        //echo "<p><b>Checking table $table ...</b> " ; ;
        if (!in_array($table,$tables_in_database)) {
            $errors_found[] = "Table $table missing";
            continue ;
        }
        $sql_query = "SELECT COUNT(*) FROM `$table`" ;
        $sql_result = mysql_query($sql_query) or die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
        $row = mysql_fetch_row($sql_result) ;
        if ($row[0] == 0) {
            $errors_found[] = "Table $table is empty";
        }
        $fields_in_this_table = array() ;
        $sql_query = "SHOW FIELDS FROM `$table` " ;
        $sql_result = mysql_query($sql_query) or die("Error: MySQL query failed:" . mysql_error() . "</p>");
        while ($row = mysql_fetch_array($sql_result)) {
            array_push($fields_in_this_table,$row["Field"]);
        }
        foreach ($fields[$table] as $field) {
            if (!in_array($field,$fields_in_this_table)) {
                $errors_found[] = "Field $field in table $table is missing";
                continue ;
            }
        }
     }
     return $errors_found;
}

function getAcceptedStatuses () {
    $link = mysqlConnect();
    $accepted_name_id = 1 ;
    $provisionally_accepted_name_id = 4 ;
    $sql_query2 = "SELECT `record_id`
               FROM   `sp2000_statuses`  
               WHERE  `sp2000_status` = 'accepted name' " ;
    $sql_result2 = mysql_query($sql_query2) or die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
    if ( mysql_num_rows($sql_result2) > 0 ) {
        $row2 = mysql_fetch_row($sql_result2);
        $accepted_name_id = $row2[0] ;
    }
    $sql_query2 = "SELECT `record_id`
               FROM   `sp2000_statuses`  
               WHERE  `sp2000_status` = 'provisionally accepted name' " ;
    $sql_result2 = mysql_query($sql_query2) or die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
    if ( mysql_num_rows($sql_result2) > 0 ) {
        $row2 = mysql_fetch_row($sql_result2);
        $provisionally_accepted_name_id = $row2[0] ;
    }
    return array($accepted_name_id, $provisionally_accepted_name_id);
}

function copyCodesToIds () {
    $fields["family"] = array ("scientific_names","family_code","family_id","families") ;
    $fields["specialist"] = array ("scientific_names","specialist_code","specialist_id","specialists") ;
    $fields["reference"] = array ("scientific_name_references","reference_code","reference_id","references") ;
    $fields["reference2"] = array ("common_names","reference_code","reference_id","references") ;
    
    $indicator = new Indicator();
    $link = mysqlConnect();
    $errors_found = array() ;

    $code_fields = array() ;
    foreach($fields as $field) {
        $table = $field[0] ;
        $code_field = $field[1] ;
        $lookup_table = $field[3] ;
        if (in_array($table . "|" . $code_field, $code_fields) == FALSE) {
            array_push($code_fields,$table . "|" . $code_field) ;
        }
        if (in_array($lookup_table . "|" . $code_field, $code_fields) == FALSE) {
            array_push($code_fields,$lookup_table . "|" . $code_field) ;
        }
    }
    foreach ($code_fields as $code_field) {
        $code_field = explode("|",$code_field) ;
        $table = $code_field[0] ;
        $field = $code_field[1] ;
        $index_exists = $anyAction = false ;
        $sql_query = "SHOW INDEX FROM `$table` " ;
        $sql_result = mysql_query($sql_query) or die("Error: MySQL query failed:" . mysql_error() . "</p>");
        while ($row = mysql_fetch_array($sql_result)) {
            if ($row["Key_name"] == $field) {
                $index_exists = true ;
                break ;
            }
        }
        if ($index_exists == false) {
            $anyAction = true;
            echo "Indexing field '$field' in table '$table'... <br>" ;
            $sql_query = "ALTER TABLE `$table` ADD INDEX (`$field`)" ;
            $sql_result = mysql_query($sql_query) or die("Error: MySQL query failed:" . mysql_error() . "</p>");
        }
    }
    echo $anyAction ? '</p><p>' : '';
    echo "Copying family codes from accepted names to synonyms in table 'scientific_names'<br>" ;
    list($accepted_name_id, $provisionally_accepted_name_id) = getAcceptedStatuses();

    $sql_query = "SELECT `name_code`, `family_code` 
                  FROM `scientific_names` 
                  WHERE `family_code` !=  '' AND `family_code` IS NOT NULL 
                  AND (`sp2000_status_id` = $accepted_name_id OR `sp2000_status_id` = $provisionally_accepted_name_id) " ;
    $sql_result = mysql_query($sql_query) or die("Error: MySQL query failed:" . mysql_error() . "</p>");
    $number_of_records = mysql_num_rows($sql_result) ;
    
    list($i, $n, $iterationsPerMarker, $sql_query2) = array(0, 0, 2500, false);
    $indicator->init($number_of_records, 100, $iterationsPerMarker);
    while ($row = mysql_fetch_array($sql_result)) {
        $indicator->iterate();
        $name_code = $row[0] ;
        $family_code = $row[1] ;
        $sql_query2 = "UPDATE `scientific_names` SET `family_code` = '" . mysql_real_escape_string($family_code) . "' 
                        WHERE `accepted_name_code` = '" . mysql_real_escape_string($name_code) . "'; " ;
        $sql_result2 = mysql_query($sql_query2) or die("Error: MySQL query failed:" . $sql_query2. mysql_error() . "</p>");
     }
    
    foreach ($fields as $field) {
        $table = $field[0] ;
        $code_field = $field[1] ;
        $id_field = $field[2] ;
        $lookup_table = $field[3] ;

        $sql_query = "SELECT DISTINCT t1.`$code_field`, t2.`record_id` FROM `$table` AS t1 
                      LEFT JOIN `$lookup_table` AS t2 ON t1.`$code_field` = t2.`$code_field` 
                      WHERE t1.`$code_field` != '' AND t1.`$code_field` IS NOT NULL" ;
        $sql_result = mysql_query($sql_query) or die("Error: MySQL query failed:" . mysql_error() . "</p>");
        $number_of_records = mysql_num_rows($sql_result) ;
        $iterationsPerMarker = $number_of_records < 10000 ? 100 : 1000;
        list($i, $j, $sql_query2) = array(0, 0, false);
        $indicator->init($number_of_records, 100, $iterationsPerMarker);
        echo "</p><p>Converting $number_of_records '".$code_field."s' to '$id_field' in table '$table'<br>\n" ;
        while ($row = mysql_fetch_array($sql_result)) {
            $indicator->iterate();
            $this_code = $row[0] ;
            $this_id = $row[1] ;
            if (empty($this_id)) {
                $errors_found[] = "Name code '$this_code' referenced in table '$table' not present in table '$lookup_table'";
                continue;
            }
            $sql_query2 = "UPDATE `$table` SET `$id_field` = $this_id WHERE `$code_field` =  '" . 
                mysql_real_escape_string($this_code) . "';\n" ;
            $sql_result2 = mysql_query($sql_query2) or die("Error: MySQL query failed: " . mysql_error() . "</p>");
        }
    }
    return $errors_found;
}

function checkForeignKeys () {
    $fields = array() ;
    array_push($fields,array("scientific_names","accepted_name_code","scientific_names","name_code")) ;
    array_push($fields,array("scientific_names","family_id","families","record_id")) ;
    array_push($fields,array("scientific_names","sp2000_status_id","sp2000_statuses","record_id")) ;
    array_push($fields,array("scientific_names","database_id","databases","record_id")) ;
    array_push($fields,array("scientific_names","specialist_id","specialists","record_id")) ;
    array_push($fields,array("common_names","reference_id","references","record_id")) ;
    array_push($fields,array("common_names","database_id","databases","record_id")) ;
    array_push($fields,array("common_names","name_code","scientific_names","name_code")) ;
    array_push($fields,array("distribution","name_code","scientific_names","name_code")) ;
    array_push($fields,array("references","database_id","databases","record_id")) ;
    array_push($fields,array("scientific_name_references","name_code","scientific_names","name_code")) ;
    array_push($fields,array("scientific_name_references","reference_id","references","record_id")) ;
    
    $indicator = new Indicator();
    $link = mysqlConnect();
    $errors_found = array() ;
    
    foreach($fields as $field) {
        $foreign_key_table = $field[0] ;
        $foreign_key_field = $field[1] ;
        $primary_key_table = $field[2] ;
        $primary_key_field = $field[3] ;

        echo "Checking links from '$foreign_key_field' in table '$foreign_key_table' to '$primary_key_field' 
            in table '$primary_key_table' ...<br>\n" ;
        $sql_query = "SELECT DISTINCT t1.`$foreign_key_field`, t2.`$primary_key_field` FROM `$foreign_key_table` AS t1
                      LEFT JOIN `$primary_key_table` AS t2 ON t1.`$foreign_key_field` = t2.`$primary_key_field`
                      WHERE t1.`$foreign_key_field` != '' AND t1.`$foreign_key_field` IS NOT NULL 
                      AND t2.`$primary_key_field` IS NULL";
        $sql_result = mysql_query($sql_query) or die("Error: MySQL query failed:" . mysql_error() . "</p>");
        $number_of_records = mysql_num_rows($sql_result);
        $recordsToDelete = array();
        while ($row = mysql_fetch_array($sql_result)) {
            $recordsToDelete[] = $row[0] ;
            $errors_found[] = "Foreign key '$row[0]' for '$foreign_key_field' in table 
                '$foreign_key_table' not found as primary key in table '$primary_key_table'";
        }
        echo "Number of problematic records deleted: " . mysql_num_rows($sql_result) . "</br>\n" ;
        if (!empty($recordsToDelete)) {
            $sql_query2 = "DELETE FROM `$foreign_key_table` WHERE `$foreign_key_field` IN ('" . 
                implode("', '", array_map('mysql_escape_string', $recordsToDelete)) . "')";
//          echo "<br><br>$sql_query<br><br>$sql_query2<br><br>";
            mysql_query($sql_query2) or die("Error: MySQL query failed:" . mysql_error() . "</p>");
        }
    }
    return $errors_found;
}

function createTaxaTable () {
    $link = mysqlConnect();
    $sql_query = "SHOW TABLES FROM `" . dbName() . "` WHERE `tables_in_" . dbName() . "` = 'taxa'";
    $sql_result = mysql_query($sql_query) or die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
    if (mysql_num_rows($sql_result) == 1) {
        $sql_result = mysql_query("DROP TABLE `taxa`") or die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
    }
    $sql_query = "CREATE TABLE `taxa` (
                      `record_id` int(10) unsigned NOT NULL,
                      `lsid` varchar(87) DEFAULT NULL,
                      `name` varchar(137) NOT NULL default '',
                      `name_with_italics` varchar(151) NOT NULL default '',
                      `taxon` varchar(12) NOT NULL default '',
                      `name_code` varchar(42) default NULL,
                      `parent_id` int(10) NOT NULL default '0',
                      `sp2000_status_id` int(1) NOT NULL default '0',
                      `database_id` int(2) NOT NULL default '0',
                      `is_accepted_name` int(1) NOT NULL default '0',
                      `is_species_or_nonsynonymic_higher_taxon` int(1) NOT NULL default '0',
                      `HierarchyCode` text NOT NULL,
                      PRIMARY KEY  (`record_id`),
                      KEY `name` (`name`,`is_species_or_nonsynonymic_higher_taxon`,`database_id`,`sp2000_status_id`),
                      KEY `sp2000_status_id` (`sp2000_status_id`),
                      KEY `parent_id` (`parent_id`),
                      KEY `database_id` (`database_id`),
                      KEY `taxon` (`taxon`),
                      KEY `is_accepted_name` (`is_accepted_name`),
                      KEY `name_code` (`name_code`),
                      KEY `is_species_or_nonsynonymic_higher_taxon` (`is_species_or_nonsynonymic_higher_taxon`),
                      KEY `HierarchyCode` (`HierarchyCode`(255))
                    ) ENGINE=MyISAM DEFAULT CHARSET=latin1  ;";
    $sql_result = mysql_query($sql_query) or die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
}

function startTaxaInsert () {
    return "INSERT INTO `taxa` (`record_id`, `name`, `name_with_italics`, `taxon`, `name_code`,
           `parent_id`, `sp2000_status_id`,`database_id`, `is_accepted_name`, 
           `is_species_or_nonsynonymic_higher_taxon`, `HierarchyCode`) VALUES ";
}

function extendTaxaInsert ($data) {
    return "('" . implode("', '", array_map('mysql_escape_string', $data)) . "'),";
}

function endTaxaInsert ($str) {
    return substr($str, 0, -1);
}

function taxaInsert ($data) {
    $link = mysqlConnect();
    $sql_query = startTaxaInsert() . extendTaxaInsert($data);
    mysql_query(endTaxaInsert($sql_query)) or die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
}

function getRecordIdInTaxa ($fields = array()) {
    $link = mysqlConnect();
    $sql_query = "SELECT `record_id` FROM `taxa` WHERE ";
    foreach ($fields as $column => $value) {
        $sql_query .= "`$column` = '" . mysql_real_escape_string($value) . "' AND ";
    }
    $sql_result = mysql_query(substr($sql_query, 0, -4)) or die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
    if (mysql_num_rows($sql_result) > 0) {
        return mysql_result($sql_result, 0);
    }
    return false;
}

function getHigherTaxonRecordId() {
    $link = mysqlConnect();
    $q = mysql_query("SELECT (MAX(`record_id`) + 1) FROM `taxa`") or 
        die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
    if ($higher_taxon_record_id = mysql_result($q, 0)) {
        return $higher_taxon_record_id;
    }
    $q = mysql_query("SELECT (MAX(`record_id`) + 1) FROM `scientific_names`") or 
        die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
    return mysql_result($q, 0);
}

function addHigherTaxaToTaxa () {
    $indicator = new Indicator();
    $link = mysqlConnect();
    $errors_found = array() ;
    
    // Ruud: 31-10-08
    // Create record_id for each higher taxon that will not overlap with real record_ids in scientific_names table
    $higher_taxon_record_id = getHigherTaxonRecordId();
    
    $sql_query = "SELECT `record_id`,`kingdom`, `phylum`, `class`, `order`,  `superfamily`, `family` FROM `families`";
    $sql_result = mysql_query($sql_query) or die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
    $number_of_records = mysql_num_rows($sql_result);
    
    $indicator->init($number_of_records, 100, 50);
    echo "$number_of_records records found in 'families' table<br>";
    while ($row = mysql_fetch_array($sql_result, MYSQL_NUM)) {
        $indicator->iterate();
        $record_id = $row[0];
        $this_kingdom = $row[1];
        $this_phylum = $row[2];
        $this_class = $row[3];
        $this_order = $row[4];
        $this_superfamily = $row[5];
        $this_family = $row[6];
        
        for ($j = 1; $j <= 6; $j ++) {
            if ($j == 5 && $this_superfamily == "") {
                // do nothing
                continue;
            } else if ($j == 1) {
                $taxon = $this_kingdom;
                $taxon_level = "Kingdom";
                $hierarchy = $this_kingdom;
                $parent_hierarchy = "";
            } else if ($j == 2) {
                $taxon = $this_phylum;
                $taxon_level = "Phylum";
                $hierarchy = $this_kingdom . "_" . $this_phylum;
                $parent_hierarchy = $this_kingdom;
            } else if ($j == 3) {
                $taxon = $this_class;
                $taxon_level = "Class";
                $hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class;
                $parent_hierarchy = $this_kingdom . "_" . $this_phylum;
            } else if ($j == 4) {
                $taxon = $this_order;
                $taxon_level = "Order";
                $hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class . "_" . $this_order;
                $parent_hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class;
            } else if ($j == 5) {
                $taxon = $this_superfamily;
                $taxon_level = "Superfamily";
                $hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class . "_" . $this_order . "_" . 
                    $this_superfamily;
                $parent_hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class . "_" . $this_order;
            } else if ($j == 6) {
                $taxon = $this_family;
                $taxon_level = "Family";
                $hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class . "_" . $this_order . "_" . 
                    (($this_superfamily != "") ? $this_superfamily . "_" : "") . $this_family;
                $parent_hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class . "_" . $this_order . 
                    (($this_superfamily != "") ? "_" . $this_superfamily : "");
            }
            
            // check if it not yet in taxa
            if (!getRecordIdInTaxa(array('HierarchyCode' => $hierarchy))) {
                // find record ID of parent taxon
                if (!$parent_id = getRecordIdInTaxa(array('HierarchyCode' => $parent_hierarchy))) {
                    $parent_id = 0;
                    if ($taxon_level != "Kingdom") {
                         $errors_found[] = "No parent for $taxon_level $taxon (id: $record_id; hierarchy: $hierarchy)";
                    }
                }
                
                // add taxon to 'taxa' table
                $higher_taxon_record_id++;
                taxaInsert(
                    array($higher_taxon_record_id, $taxon, $taxon, $taxon_level, '', $parent_id, 0, 0, 0, 1, $hierarchy)
                );
            }
        }
    }
    return $errors_found;
}

function addGeneraToTaxa () {
    $indicator = new Indicator();
    $link = mysqlConnect();
    $errors_found = array() ;
    $higher_taxon_record_id = getHigherTaxonRecordId();
    
    $sql_query = "SELECT DISTINCT t1.`genus`, t1.`family_id`, t2.`kingdom`, t2.`phylum`, t2.`class`, 
                  t2.`order`, t2.`superfamily`, t2.`family`
                  FROM `scientific_names` AS t1 
                  LEFT JOIN `families` AS t2 ON t1.`family_id` = t2.`record_id`";
    $sql_result = mysql_query($sql_query) or die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
    $number_of_records = mysql_num_rows($sql_result);
    echo "$number_of_records genera found in 'scientific_names' table<br>";
    
    $indicator->init($number_of_records, 100, 300);
    $family_id = $hierarchy = $parent_hierarchy = $parent_id = "";
    while ($row = mysql_fetch_array($sql_result, MYSQL_NUM)) {
        $indicator->iterate();
        $old_family_id = $family_id;
        $old_hierarchy = $hierarchy;
        $old_parent_hierarchy = $parent_hierarchy;
        
        $this_genus = $row[0];
        $family_id = $row[1];
        $this_kingdom = $row[2];
        $this_phylum = $row[3];
        $this_class = $row[4];
        $this_order = $row[5];
        $this_superfamily = $row[6];
        $this_family = $row[7];
        
        if ($family_id == "") {
            $errors_found[] = "No family ID found for $taxon_level $taxon (id: $family_id)";
        }
        if ($this_kingdom == "Viruses" || $this_kingdom == "Subviral agents") {
            $taxon = $this_genus;
            $taxon_with_italics = "$this_genus";
        } else {
            $taxon = $this_genus;
            $taxon_with_italics = "<i>$this_genus</i>";
        }
        $taxon_level = "Genus";
        $parent_hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class . "_" . $this_order . "_" . 
            (($this_superfamily != "") ? $this_superfamily . "_" : "") . $this_family;
        $hierarchy = $parent_hierarchy . "_" . $this_genus;
        
        // find record ID of parent taxon
        if ($old_parent_hierarchy == "" || $parent_hierarchy != $old_parent_hierarchy) {
            if (!$parent_id = getRecordIdInTaxa(array('HierarchyCode' => $parent_hierarchy))) {
                $errors_found[] = "No parent taxon found for $taxon_level $taxon (id: $family_id)";
                $parent_id = 0;
            }
        }

        // add taxon to 'taxa' table
        if (!getRecordIdInTaxa(array('HierarchyCode' => $hierarchy, 'name' => $taxon))) {
            $higher_taxon_record_id++;
            taxaInsert(
                array($higher_taxon_record_id, $taxon, $taxon_with_italics, $taxon_level, '', 
                      $parent_id, 0, 0, 0, 1, $hierarchy)
            );
        }
    }
    return $errors_found;
}

function addSpeciesToTaxa () {
    $indicator = new Indicator();
    $link = mysqlConnect();
    $errors_found = array() ;
    $acceptedStatuses = getAcceptedStatuses();
    
// testing only!
//mysql_query('DELETE FROM `taxa` WHERE `taxon` = "Species"');
    
    $sql_query = "SELECT t1.`record_id`, t1.`genus`, t1.`species`, t1.`name_code`, t1.`sp2000_status_id`, 
                      t1.`accepted_name_code`, t1.`database_id`, t1.`family_id`, t2.`kingdom`, 
                      t2.`phylum`, t2.`class`, t2.`order`, t2.`superfamily`, t2.`family` 
                  FROM `scientific_names` AS t1 
                  LEFT JOIN `families` AS t2 ON t1.`family_id`= t2.`record_id` 
                  WHERE (t1.`infraspecies` = '' OR t1.`infraspecies` IS NULL)  ";
    $sql_result = mysql_query($sql_query) or die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
    $number_of_records = mysql_num_rows($sql_result);
    echo "$number_of_records species found in 'scientific_names' table<br>";
    
    list($i, $n, $recordsPerBatch, $sql_query2) = array(0, 0, 3000, startTaxaInsert());
    $indicator->init($number_of_records, 100, 2000);
    $family_id = $hierarchy = $parent_hierarchy = $parent_id = "";
    while ($row = mysql_fetch_array($sql_result, MYSQL_NUM)) {
        $indicator->iterate();
        $i++;
        $n++;
        $old_family_id = $family_id;
        $old_hierarchy = $hierarchy;
        $old_parent_hierarchy = $parent_hierarchy;
        
        $record_id = $row[0];
        $this_genus = $row[1];
        $this_species = $row[2];
        $this_name_code = $row[3];
        $this_sp2000_status_id = $row[4];
        $accepted_name_code = $row[5];
        $this_database_id = $row[6];
        $family_id = $row[7];

        $this_kingdom = $row[8];
        $this_phylum = $row[9];
        $this_class = $row[10];
        $this_order = $row[11];
        $this_superfamily = $row[12];
        $this_family = $row[13];
        
        if ($family_id == "") {
            $errors_found[] = "No family ID found for $taxon_level $taxon (id: $family_id)";
        }        
        if ($this_kingdom == "Viruses" || $this_kingdom == "Subviral agents") {
            $taxon = $this_species;
            $taxon_with_italics = $this_species;
        } else {
            $taxon = "$this_genus $this_species";
            $taxon_with_italics = "<i>$this_genus $this_species</i>";
        }
        $taxon_level = "Species";
        $parent_hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class . "_" . $this_order . "_" . 
            (($this_superfamily != "") ? $this_superfamily . "_" : "") . $this_family . "_" . $this_genus;
        $hierarchy = $parent_hierarchy . "_" . $this_species;
        
        if (!$parent_id = getRecordIdInTaxa(array('HierarchyCode' => $parent_hierarchy))) {
            $errors_found[] = "No parent taxon found for species $taxon (id: $record_id)";
            // Ruud 27-01-11: don't insert into taxa table, dismiss instead
            //continue;
        }
//echo "$family_id | $parent_id | $this_database_id<br>";
        if ($this_sp2000_status_id == "") {
            $errors_found[] = "No Species 2000 status found for species $taxon (id: $record_id)";
            // Ruud 27-01-11: don't insert into taxa table, dismiss instead
            continue;
        }
        if ($this_database_id == "") {
            $errors_found[] = "No database ID found for species $taxon (id: $record_id)";
            // Ruud 27-01-11: don't insert into taxa table, dismiss instead
            continue;
        }
        $is_accepted_name = "0";
        if (in_array($this_sp2000_status_id, $acceptedStatuses)) {
            $is_accepted_name = "1";
        }
        
        // add taxon to 'taxa' table
        $sql_query2 .= extendTaxaInsert(
            array($record_id, $taxon, $taxon_with_italics, $taxon_level, $this_name_code, $parent_id, 
                  $this_sp2000_status_id, $this_database_id, $is_accepted_name, 1, $hierarchy)
        );
        if ($i == $recordsPerBatch || $n == $number_of_records) {            //$link = mysqlConnect();
            mysql_query(endTaxaInsert($sql_query2)) or die("<p>Error: MySQL query failed: " . mysql_error() . "</p>");
            list($i, $sql_query2) = array(0, startTaxaInsert());
        }
    }
    return $errors_found;
}

function addInfraspeciesToTaxa() {
    $indicator = new Indicator();
    $link = mysqlConnect();
    $errors_found = array() ;
    $acceptedStatuses = getAcceptedStatuses();

    // testing only!
//mysql_query('DELETE FROM `taxa` WHERE `taxon` = "Infraspecies"');
    
    $sql_query = "SELECT t1.`record_id`, t1.`genus`, t1.`species`, t1.`infraspecies_marker`,
                  t1.`infraspecies`, t1.`name_code`, t1.`sp2000_status_id`, t1.`accepted_name_code`,
                  t1.`database_id`, t1.`family_id`, t2.`kingdom`, t2.`phylum`, t2.`class`, 
                  t2.`order`, t2.`superfamily`, t2.`family` 
                  FROM `scientific_names` AS t1 
                  LEFT JOIN `families` AS t2 ON t1.`family_id`= t2.`record_id` 
                  WHERE t1.`infraspecies` != '' AND t1.`infraspecies` IS NOT NULL ";
    $sql_result = mysql_query($sql_query) or die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
    $number_of_records = mysql_num_rows($sql_result);
    echo "$number_of_records species found in 'scientific_names' table<br>";
    
    list($i, $n, $recordsPerBatch, $sql_query2) = array(0, 0, 3000, startTaxaInsert());
    $indicator->init($number_of_records, 100, 500);
    $family_id = $hierarchy = $parent_hierarchy = $parent_id = "";
    while ($row = mysql_fetch_array($sql_result, MYSQL_NUM)) {
        $indicator->iterate();
        $i++;
        $n++;
        $old_family_id = $family_id;
        $old_hierarchy = $hierarchy;
        $old_parent_hierarchy = $parent_hierarchy;
        
        $record_id = $row[0];
        $this_genus = $row[1];
        $this_species = $row[2];
        $this_infraspecies_marker = $row[3];
        $this_infraspecies = $row[4];
        $this_name_code = $row[5];
        $this_sp2000_status_id = $row[6];
        $accepted_name_code = $row[7];
        $this_database_id = $row[8];
        $family_id = $row[9];
        
        $this_kingdom = $row[10];
        $this_phylum = $row[11];
        $this_class = $row[12];
        $this_order = $row[13];
        $this_superfamily = $row[14];
        $this_family = $row[15];
    
        if ($this_kingdom == "Viruses" || $this_kingdom == "Subviral agents") {
            $taxon = $this_species;
            if ($this_infraspecies_marker != "") {
                $taxon .= " $this_infraspecies_marker";
            }
            $taxon .= " $this_infraspecies";
            $taxon_with_italics = $taxon;
        } else {
            $taxon = "$this_genus $this_species";
            if ($this_infraspecies_marker != "") {
                $taxon .= " $this_infraspecies_marker";
            }
            $taxon .= " $this_infraspecies";
            if ($this_infraspecies_marker == "") {
                $taxon_with_italics = "<i>$this_genus $this_species $this_infraspecies</i>";
            } else {
                $taxon_with_italics = 
                    "<i>$this_genus $this_species</i> $this_infraspecies_marker <i>$this_infraspecies</i>";
            }
        }
        $taxon_level = "Infraspecies";
        $parent_hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class . "_" . $this_order . "_" . 
            (($this_superfamily != "") ? $this_superfamily . "_" : "") . $this_family . "_" . $this_genus . "_" . 
            $this_species;
        $hierarchy = $parent_hierarchy . "_" . $this_infraspecies;
        
        // Ruud 09-11-10: moved this check up so it can be used to check infraspecies parent
        $is_accepted_name = "0";
        if (in_array($this_sp2000_status_id, $acceptedStatuses)) {
            $is_accepted_name = "1";
        }
                
        // Ruud 09-11-10: if statement removed, as every infraspecies has to be checked as 
        // hierarchy can apply to valid infraspecies and synonyms!
        if ($parent_hierarchy != "" && $family_id != "") {
            if (!$parent_id = getRecordIdInTaxa(setInfraspeciesSearch($parent_hierarchy, $is_accepted_name))) {
                $parent_hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class . "_" . $this_order . "_" . 
                    (($this_superfamily != "") ? $this_superfamily . "_" : "") . $this_family . "_" . $this_genus;
                $hierarchy = $parent_hierarchy . "_" . $this_infraspecies;
                if (!$parent_id = getRecordIdInTaxa(setInfraspeciesSearch ($parent_hierarchy, $is_accepted_name))) {
                    $errors_found[] = "No parent taxon found for infraspecies $taxon (id: $record_id)";
                    continue;
                }
            }
        }
        if ($this_sp2000_status_id == "") {
            $errors_found[] = "No Species 2000 status found for infraspecies $taxon (id: $record_id)";
            // Ruud 27-01-11: don't insert into taxa table, dismiss instead
            continue;
        }
        if ($this_database_id == "") {
            $errors_found[] = "No database ID found for infraspecies $taxon (id: $record_id)";
            // Ruud 27-01-11: don't insert into taxa table, dismiss instead
            continue;
        }

        // add taxon to 'taxa' table
        $sql_query2 .= extendTaxaInsert(
            array($record_id, $taxon, $taxon_with_italics, $taxon_level, $this_name_code, $parent_id,
                  $this_sp2000_status_id, $this_database_id, $is_accepted_name, 1, '')
        );
        if ($i == $recordsPerBatch || $n == $number_of_records) {
            mysql_query(endTaxaInsert($sql_query2)) or die("<p>Error: MySQL query failed: " . mysql_error() . "</p>");
            list($i, $sql_query2) = array(0, startTaxaInsert());
        }
        
    }
    return $errors_found;
}

function setInfraspeciesSearch ($parent_hierarchy, $is_accepted_name) {
    $search = array('HierarchyCode' => $parent_hierarchy);
    if ($is_accepted_name == "1") {
        $search['is_accepted_name'] = 1;
    }
    return $search;
}

function higherTaxaWithAcceptedNames () {
    $link = mysqlConnect();
    $taxa = array("genera" => "Genus" , "families" => "Family" , "superfamilies" => "Superfamily" , 
                  "orders" => "Order" , "classes" => "Class" , "phyla" => "Phylum" , "kingdoms" => "Kingdom");
    foreach ($taxa as $label => $rank) {
        echo "Finding $label with accepted names...<br>";
        $sql_query = "UPDATE `taxa` parent, `taxa` child
                      SET parent.`is_accepted_name` = 1
                      WHERE parent.`taxon` = '$rank'
                      AND parent.`record_id` = child.`parent_id`
                      AND child.`is_accepted_name` = 1 ";
        $sql_result = mysql_query($sql_query) or die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
    }
}

function higherTaxaWithSynonyms () {
    $link = mysqlConnect();
    $sql_query = "UPDATE `taxa` 
                  SET `is_species_or_nonsynonymic_higher_taxon`  = 0 
                  WHERE `taxon` != 'Species' AND `taxon` != 'Infraspecies' AND `is_accepted_name` = 0";
    $sql_result = mysql_query($sql_query) or die("<p>Error: MySQL query failed:" . mysql_error() . "</p>");
}

    
function buildTaxaTable () {
    $errors_found = array() ;
    echo "Creating table 'taxa'...<br> ";
    createTaxaTable();
    echo "Adding higher taxa: ";
    $errors_found['Higher taxa'] = addHigherTaxaToTaxa();
    echo "<br><br>Adding genera: ";
    $errors_found['Genera'] = addGeneraToTaxa();
    echo "<br><br>Adding species: ";
    $errors_found['Species'] = addSpeciesToTaxa(); 
    echo "<br><br>Adding infraspecies: ";
    $errors_found['Infraspecies'] = addInfraspeciesToTaxa(); 
    echo "</p><p><b>Updating higher taxa</b><br>";
    higherTaxaWithAcceptedNames();
    echo "Finding higher taxa containing only synonyms...<br>";
    higherTaxaWithSynonyms();
    return $errors_found;
}