<?php

require_once 'Indicator.php';
require_once 'Zend/Log/Writer/Stream.php';
require_once 'Zend/Log.php';

class AdOptimizer {
    
    private $pdo;
    private $dbType;
    private $logger;
    private $indicator;
    private $config;
    private $taxaStmt;
    
    private $messages = [];
    
    public function __construct ($ini = false)
    {
        if (!$ini || !file_exists($ini)) {
            throw new Exception('AdOptimizer should be initialised with correct path to ini file!');
        }
        $config = parse_ini_file($ini, true);
        foreach ($config['source'] as $k => $v) {
            $this->config[$k] = $v;
        }
        foreach ($config['col_plus'] as $k => $v) {
            $this->config[$k] = $v;
        }
        $this->indicator = new Indicator();
    }
    
    public function setPdo ($dbType = 'mysql')
    {
        if (!$this->pdo) {
            $this->dbType = $dbType;
            $this->pdo = new PDO(
                sprintf('%s:host=%s;dbname=%s', $this->dbType, $this->config['host'], $this->config['dbname']), 
                $this->config['username'], 
                $this->config['password'],
                [
                    PDO::MYSQL_ATTR_LOCAL_INFILE => true,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                ]
            );
        }
        return $this->pdo;
    }
    
    public function setLogger ($logPath)
    {
        if (!$this->logger) {
            if (file_exists($logPath)) {
                unlink($logPath);
            }
            $writer = new Zend_Log_Writer_Stream($logPath);
            $this->logger = new Zend_Log($writer);
        }
        return $this->logger;
    }
    
    public function importCsv ()
    {
        $tmpDir = dirname(__DIR__) . '/tmp/';
        if (!is_readable($tmpDir)) {
            throw new Exception($tmpDir . ' to extract csv to is not readable!');
        }
        $zipFile = $tmpDir . 'ac-export.zip';
        
        // Is path available in config?
        if (!isset($this->config['csvPath'])) {
            throw new Exception('Please add path to zip with csv files to ini file ("csvPath")!');
        }
        // Download file
        $result = $this->downloadFile($this->config['csvPath'], $zipFile);
        if ($result['error'] !== false) {
            throw new Exception('Could not download ' . $this->config['csvPath'] . ': ' . $result['error']);
        }
        // ... and is it readable?
        if (!is_readable($zipFile)) {
            throw new Exception('Downloaded archive ' . $zipFile . ' is not readable!');
        }
        // Is destination present and readable?
        $files = [];
        $zip = new ZipArchive();
        $zip->open($zipFile);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat['size'] > 0){
                $files[] = $stat['name'];
            }
        }
        $zip->extractTo($tmpDir);
        foreach ($files as $file) {
            $table = str_replace('.csv', '', $file);
            $file = $tmpDir . $file;
            $tmp = new SplFileObject($file, 'r');
            $tmp->seek(PHP_INT_MAX);
            $nrLines = $tmp->key() - 1;

            $this->pdo->query('TRUNCATE table `' . $table . '`');
            $query = "
                LOAD DATA LOCAL INFILE '$file'
                INTO TABLE `$table`
                FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"'
                LINES TERMINATED BY '\n'
                IGNORE 1 LINES;";
            $res = $this->pdo->query($query);
            if (!$res) {
                throw new Exception($file . " file is corrupt!");
            } else if ($res->rowCount() < $nrLines) {
                $this->addMessage($file . ': not everything seems to be imported; veriying...');
                $this->reportMissingRecordIds($file);
            }
            unlink($file);
        }
        return $this;
    }
    
    private function reportMissingRecordIds ($file)
    {
        $table = str_replace('.csv', '', $file);
        $i = 0;
        if (($handle = fopen($file, "r")) !== false) {
            while (($row = fgetcsv($handle, 1000, ",")) !== false) {
                // Check header; set prepared statement
                $column = $row[0];
                if ($i == 0) {
                    if ($column != 'record_id' && $column != 'name_code') {
                        $this->addMessage($file . ": $column is not a valid header; aborting verification");
                        return false;
                    }
                    $stmt = $this->pdo->prepare("select `$column` from `$table` where `$column` = ?");
                } else {
                    $stmt->execute([$row[0]]);
                    if ($stmt->rowCount() != 1) {
                        $this->addMessage($file . ": $column not imported to $table!");
                    }
                }
                $i++;
            }
            fclose($handle);
        }
    }
    
    public function downloadFile ($from, $to)
    {
        $ch = @curl_init($from);
        if (!$ch) {
            $error = 'Could not locate file at ' . $from;
        }
        $fp = @fopen($to, 'w+');
        if (!$ch) {
            $error = 'Could not open destination file at ' . $to;
        }
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
        curl_setopt($ch, CURLOPT_USERAGENT, 'any');
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_exec($ch);
        if ($errno = curl_errno($ch)) {
            $error = "cURL error ({$errno}): " . curl_strerror($errno);
        }
        curl_close($ch);
        fclose($fp);
        return ['error' => isset($error) ? $error : false];
    }
    
    public function printMessages ($header = false, $clearAfterPrinting = true) {
        if (!empty($this->messages)) {
            $output = '<p>' . ($header ? '<b>' . $header .'</b><br>' : '');
            foreach ($this->messages as $error) {
                $this->logger->err("\n" . ($header ? "$header:\n" : '') . strip_tags($error) . "\n");
                $output .= $error.'<br>';
            }
            echo $output.'</p>';
        }
        if ($clearAfterPrinting) {
            $this->clearMessages();
        }
    }
    
    public function checkDatabase () {
        $tablesInDatabase = [];
        $stmt = $this->pdo->query('SHOW TABLES FROM `' . $this->config['dbname'] . '`');
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tablesInDatabase[] = $row[0];
        }
        foreach (self::$acefTables as $table => $columns) {
            if (!in_array($table, $tablesInDatabase)) {
                $this->addMessage("Table $table missing");
                continue ;
            }
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM `$table`");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            if ($row[0] == 0) {
                $this->addMessage("Table $table is empty");
            } else {
                $stmt = $this->pdo->query("SELECT * FROM `$table` LIMIT 1");
                $columnsInTable = array_keys($stmt->fetch(PDO::FETCH_ASSOC));
                foreach ($columns as $column) {
                    if (!in_array(strtolower($column), $columnsInTable)) {
                        $this->addMessage("Field $column in table $table is missing");
                    }
                }
            }
        }
        $this->pdo->query('UPDATE `families` SET `database_id` = 0 WHERE `database_id` IS NULL');
        return $this;
    }
    
    public function checkIndices ()
    {
        if ($this->dbType == 'mysql') {
            foreach (self::$indices as $table => $indices) {
                foreach ($indices as $index) {
                    $stmt = $this->pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = '$index'");
                    if (empty($stmt->fetch())) {
                        $this->pdo->query("ALTER TABLE `$table` ADD INDEX (`$index`)");
                        $this->addMessage("Missing index on '$index' in table '$table' added");
                    }
                }
            }
        }
        return $this;
    }
 
    public function familyCodeToSynonyms ()
    {
        $this->query('
            update scientific_names as t1
            left join scientific_names as t2 on t1.accepted_name_code = t2.name_code
            set t1.family_code = t2.family_code
            where t1.sp2000_status_id not in (1,4)',
            'Family codes to synonyms: already copied!'
        );
        return $this;
    }
    
    public function codesToIds ()
    {
        $sql = [
            'family ids to scientific_names' => 
                "update scientific_names as t1
                left join families as t2 on t1.family_code = t2.family_code
                set t1.family_id = t2.record_id
                where t1.family_code != '' and t1.family_code is not null",
            
            'specialist ids to scientific_names' => 
                "update scientific_names as t1
                left join specialists as t2 on t1.specialist_code = t2.specialist_code
                set t1.specialist_id = t2.record_id
                where t1.specialist_code != '' and t1.specialist_code is not null",
            
            'reference ids to scientific_name_references' => 
                "update scientific_name_references as t1
                left join `references` as t2 on t1.reference_code = t2.reference_code
                set t1.reference_id = t2.record_id
                where t1.reference_code != '' and t1.reference_code is not null",
            
            'reference ids to common_names' => 
                "update common_names as t1
                left join `references` as t2 on t1.reference_code = t2.reference_code
                set t1.reference_id = t2.record_id
                where t1.reference_code != '' and t1.reference_code is not null"
        ];
        foreach ($sql as $label => $query) {
            $this->query($query, ucfirst($label) . ': already copied!');
        }
        return $this;
    }
    
    public function checkForeignKeys () 
    {
        foreach (self::$fkFields as $field) {
            list($fkTable, $fkColumn, $pkTable, $pkColumn) = $field;
            
            $this->addMessage("Checking links from '$fkColumn' in table '$fkTable' to '$pkColumn'
                in table '$pkTable'...");

            $query = "
                SELECT DISTINCT t1.`$fkColumn` FROM `$fkTable` AS t1
                LEFT JOIN `$pkTable` AS t2 ON t1.`$fkColumn` = t2.`$pkColumn`
                WHERE t1.`$fkColumn` != '' AND t1.`$fkColumn` IS NOT NULL
                AND t2.`$pkColumn` IS NULL";
            
            $stmt = $this->query($query);
            $deleteIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($deleteIds)) {
                $delete = "DELETE FROM `$fkTable` WHERE `$fkColumn` IN ('" . implode("','", $deleteIds) . "')";
                $this->query($delete);
                foreach ($deleteIds as $errorId) {
                    $this->addMessage("Foreign key $errorId for '$fkColumn' in table '$fkTable' 
                        not found as primary key in table '$pkTable'");
                }
                $this->printMessages("Problematic records deleted from '$fkTable':");
            }
            
        }
        return $this;
    }
    
    public function buildTaxaTable ()
    {
        $this->createTaxaTable();
        $this->addHigherTaxaToTaxa();
        $this->addGeneraToTaxa();
        $this->addSubgeneraToTaxa();
        $this->addSpeciesToTaxa();
        $this->addInfraspeciesToTaxa();
        
        echo "</p><p>Updating higher taxa...<br>";
        $this->updateHigherTaxa();
        // echo "Verifying accepted status...<br>";
        // $this->verifyAcceptedStatus();
        return $this;
    }
    
    public function formatTime ($s)
    {
        return $this->indicator->formatTime($s);
    }
    
    private function query ($query = false, $message = false)
    {
        if (!$query) {
            throw new Exception('No query?!');
        }
        $stmt = $this->pdo->query($query);
        // We have a problem
        if (!$stmt) {
            throw new Exception('Query failed! ' . $query);
        }
        // No results; report if requested
        if ($stmt->rowCount() == 0 && $message) {
            $this->addMessage($message);
        }
        return $stmt;
    }
    
    private function updateHigherTaxa () 
    {
        $taxa = [
            "subgenera" => "Subgenus", 
            "genera" => "Genus" , 
            "families" => "Family" , 
            "superfamilies" => "Superfamily",
            "orders" => "Order",
            "classes" => "Class",
            "phyla" => "Phylum",
            "kingdoms" => "Kingdom"
        ];
        $stmt = $this->pdo->prepare("
            UPDATE `taxa` parent, `taxa` child
            SET parent.`is_accepted_name` = 1
            WHERE parent.`taxon` = ?
            AND parent.`record_id` = child.`parent_id`
            AND child.`is_accepted_name` = 1"
        );
        // Change line below to include dead ends in the tree!
        // $taxa = array("subgenera" => "Subgenus", "genera" => "Genus");
        foreach ($taxa as $label => $rank) {
            echo "Finding $label with accepted names...<br>";
            $stmt->execute([$rank]);
        }
        $this->query('
            UPDATE `taxa` 
            SET `is_accepted_name` = 1
            WHERE `taxon` in ("Family" "Superfamily", "Order", "Class", "Phylum", "Kingdom") AND `name` != ""'
        );
        $this->query("
            UPDATE `taxa`
            SET `is_species_or_nonsynonymic_higher_taxon` = 0
            WHERE `taxon` != 'Species' AND `taxon` != 'Infraspecies' AND `is_accepted_name` = 0"
        );
        return $this;
    }
    
    private function verifyAcceptedStatus () {
        $stmt = $this->query("
            SELECT t1.`record_id` FROM `taxa` t1
            LEFT JOIN `scientific_names` AS t2 ON t1.`record_id` = t2.`record_id`
            WHERE t1.`is_accepted_name` != t2.`is_accepted_name` AND
            t1.`is_accepted_name` = 1"
        );
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($ids) > 0) {
            $this->query("
                UPDATE `taxa` 
                SET `is_accepted_name` = 0 
                WHERE `record_id` IN (" . implode(',', $ids) . ")"
            );
        }
        
        // Ruud 05-02-15: delete taxa with conflicting flags
        $stmt = $this->query('
            SELECT `record_id`, `name`
            FROM `taxa`
            WHERE `sp2000_status_id` IN (1,4) AND `is_accepted_name` = 0
            
            UNION DISTINCT
            
            SELECT `record_id`, `name`
            FROM `taxa`
            WHERE `sp2000_status_id` IN (2,3,5) AND `is_accepted_name` = 1'
        );
        if ($stmt->rowCount() > 0) {
            $ids = [];
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $ids[$row[0]] = $row[1];
                $this->addMessage('Taxon deleted: conflicting status for ' . $row[1] . ' (id: ' . $row[0] . ')');
            }
            $this->query("DELETE FROM `taxa` WHERE `record_id` IN (" . implode(',', array_keys($ids)) . ")");
       }
        return $this;
    }
    
    private function addHigherTaxaToTaxa () 
    {
        // Ruud: 31-10-08
        // Create record_id for each higher taxon that will not overlap with real record_ids in scientific_names table
        $taxonId = $this->getHigherTaxonRecordId();
        $stmt = $this->query("
            SELECT `record_id`,`kingdom`, `phylum`, `class`, `order`,  `superfamily`, `family` 
            FROM `families`"
        );
        $total = $stmt->rowCount();
        
        $this->indicator->init($total, 100, 50);
        echo "<br>$total records found in 'families' table<br>";
        
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $this->indicator->iterate();
            list($record_id, $this_kingdom, $this_phylum, $this_class, $this_order, $this_superfamily ,$this_family) = $row;
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
                    $hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class . 
                        "_" . $this_order;
                    $parent_hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class;
                } else if ($j == 5) {
                    $taxon = $this_superfamily;
                    $taxon_level = "Superfamily";
                    $hierarchy = $this_kingdom . "_" . $this_phylum . "_" . 
                        $this_class . "_" . $this_order . "_" .  $this_superfamily;
                    $parent_hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class . 
                        "_" . $this_order;
                } else if ($j == 6) {
                    $taxon = $this_family;
                    $taxon_level = "Family";
                    $hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class . 
                        "_" . $this_order . "_" . (($this_superfamily != "") ? 
                        $this_superfamily . "_" : "") . $this_family;
                        $parent_hierarchy = $this_kingdom . "_" . $this_phylum . "_" . 
                        $this_class . "_" . $this_order . 
                        (($this_superfamily != "") ? "_" . $this_superfamily : "");
                }
                
                // check if it not yet in taxa
                if (!$this->getTaxaRecordId(['HierarchyCode' => $hierarchy])) {
                    // find record ID of parent taxon
                    if (!$parent_id = $this->getTaxaRecordId(['HierarchyCode' => $parent_hierarchy])) {
                        $parent_id = 0;
                        if ($taxon_level != "Kingdom") {
                            $this->addMessage("No parent for $taxon_level $taxon (id: 
                                $record_id; hierarchy: $parent_hierarchy)");
                        }
                    }
                    // add taxon to 'taxa' table
                    $taxonId++;
                    $this->taxaInsert([$taxonId, $taxon, $taxon, $taxon_level, '', $parent_id, 0, 0, 0, 1, $hierarchy, 0, 0, 1]);
                }
            }
        }
        return $this;
    }
    
    private function addGeneraToTaxa () 
    {
        $taxonId = $this->getHigherTaxonRecordId();
        $stmt = $this->query("
            SELECT DISTINCT t1.`genus`, t1.`family_id`, t2.`kingdom`, t2.`phylum`, t2.`class`,
                  t2.`order`, t2.`superfamily`, t2.`family`
            FROM `scientific_names` AS t1
            LEFT JOIN `families` AS t2 ON t1.`family_id` = t2.`record_id`
    		WHERE t1.`family_id` IS NOT NULL"
        );
        $total = $stmt->rowCount();
        echo "<br>$total genera found in 'scientific_names' table<br>";
        
        $this->indicator->init($total, 100, 300);
        $family_id = $hierarchy = $parent_hierarchy = $parent_id = "";
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $this->indicator->iterate();
            $old_family_id = $family_id;
            $old_hierarchy = $hierarchy;
            $old_parent_hierarchy = $parent_hierarchy;
            
            list($this_genus, $family_id, $this_kingdom, $this_phylum, $this_class, $this_order,
                $this_superfamily ,$this_family) = array_map('trim', $row);
            $taxon = $taxon_with_italics = $this_genus;
            if ($this_kingdom != "Viruses" && $this_kingdom != "Subviral agents") {
                $taxon_with_italics = "<i>$this_genus</i>";
            }
            $taxon_level = "Genus";
            
            $parent_hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class . "_" . $this_order . "_" .
                (($this_superfamily != "") ? $this_superfamily . "_" : "") . $this_family;
                $hierarchy = $parent_hierarchy . "_" . $this_genus;
                
                // find record ID of parent taxon
                if ($old_parent_hierarchy == "" || $parent_hierarchy != $old_parent_hierarchy) {
                    if (!$parent_id = $this->getTaxaRecordId(['HierarchyCode' => $parent_hierarchy])) {
                        $this->addMessage("No parent taxon found for genus $taxon
                            (parent family $this_family, hierarchy $parent_hierarchy)");
                        $parent_id = 0;
                    }
                }
                
                // add taxon to 'taxa' table
                if (!$this->getTaxaRecordId(['HierarchyCode' => $hierarchy, 'name' => $taxon])) {
                    $taxonId++;
                    $this->taxaInsert([
                        $taxonId, $taxon, $taxon_with_italics, $taxon_level, '',
                        $parent_id, 0, 0, 0, 1, $hierarchy, 0, 0, 1]
                    );
                }
        }
        return $this;
    }
    
    private function addSubgeneraToTaxa () 
    {
        $taxonId = $this->getHigherTaxonRecordId();
        $stmt = $this->query("
            SELECT DISTINCT t1.`genus`, t1.`family_id`, t2.`kingdom`, t2.`phylum`, t2.`class`,
                  t2.`order`, t2.`superfamily`, t2.`family`, t1.`subgenus`
            FROM `scientific_names` AS t1
            LEFT JOIN `families` AS t2 ON t1.`family_id` = t2.`record_id`
            WHERE t1.`subgenus` > '' AND t1.`family_id` IS NOT NULL"
        );
        $total = $stmt->rowCount();
        echo "<br>$total subgenera found in 'scientific_names' table<br>";
        
        $this->indicator->init($total, 100, 300);
        $family_id = $hierarchy = $parent_hierarchy = $parent_id = "";
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $this->indicator->iterate();
            
            $old_family_id = $family_id;
            $old_hierarchy = $hierarchy;
            $old_parent_hierarchy = $parent_hierarchy;
            
            $row = array_map('trim', $row);
            $this_subgenus = $row[8];
            $this_genus = $row[0];
            $family_id = $row[1];
            $this_kingdom = $row[2];
            $this_phylum = $row[3];
            $this_class = $row[4];
            $this_order = $row[5];
            $this_superfamily = $row[6];
            $this_family = $row[7];
            
            $taxon_level = 'Subgenus';
            $taxon_with_italics = $taxon = $this_subgenus;
            
            if ($this_kingdom != "Viruses" && $this_kingdom != "Subviral agents") {
                $taxon_with_italics = '<i>'.$taxon.'</i>';
            }
            
            if ($family_id == "") {
                $this->addMessage("No family ID found for subgenus $taxon (id: $family_id)");
            }
            
            $parent_hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class . "_" . $this_order . "_" .
                (($this_superfamily != "") ? $this_superfamily . "_" : "") . $this_family . "_" . $this_genus;
                $hierarchy = $parent_hierarchy . "_" . $this_subgenus;
                
            // find record ID of parent taxon
            if ($old_parent_hierarchy == "" || $parent_hierarchy != $old_parent_hierarchy) {
                if (!$parent_id = $this->getTaxaRecordId(['HierarchyCode' => $parent_hierarchy])) {
                    $this->addMessage("No parent taxon found for subgenus $taxon (parent: genus $this_genus)");
                    $parent_id = 0;
                }
            }
            
            // add taxon to 'taxa' table
            if (!$this->getTaxaRecordId(['HierarchyCode' => $hierarchy, 'name' => $taxon])) {
                $taxonId++;
                $this->taxaInsert([
                    $taxonId, $taxon, $taxon_with_italics, $taxon_level, '',
                    $parent_id, 0, 0, 0, 1, $hierarchy, 0, 0, 1
                ]);
            }
        }
        return $this;
    }
        
    private function addSpeciesToTaxa () 
    {
        $stmt = $this->query("
            SELECT COUNT(1)
            FROM `scientific_names` AS t1
            LEFT JOIN `families` AS t2 ON t1.`family_id`= t2.`record_id`
            WHERE (t1.`infraspecies` = '' OR t1.`infraspecies` IS NULL) AND t1.`family_id` IS NOT NULL"
        );
        $total = (int)$stmt->fetchColumn();
        echo "<br>$total species found in 'scientific_names' table<br>";
        
        $family_id = $hierarchy = $parent_hierarchy = $parent_id = "";
        $this->indicator->init($total, 100, 2000);
        $batch = 50000;
        
        for ($i = 0; $i <= $total; $i += $batch) {
            $query = "
                SELECT t1.`record_id`, t1.`genus`, t1.`species`, t1.`name_code`, t1.`sp2000_status_id`,
                    t1.`accepted_name_code`, t1.`database_id`, t1.`family_id`, t2.`kingdom`,
                    t2.`phylum`, t2.`class`, t2.`order`, t2.`superfamily`, t2.`family`, t1.`subgenus`,
                    t1.`is_extinct`, t1.`has_preholocene`, t1.`has_modern`
                FROM `scientific_names` AS t1
                LEFT JOIN `families` AS t2 ON t1.`family_id`= t2.`record_id`
                WHERE (t1.`infraspecies` = '' OR t1.`infraspecies` IS NULL) AND t1.`family_id` IS NOT NULL
                LIMIT %d, %d";
            $stmt = $this->pdo->query(sprintf($query, $i, $batch));
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $this->indicator->iterate();
                
                $old_family_id = $family_id;
                $old_hierarchy = $hierarchy;
                $old_parent_hierarchy = $parent_hierarchy;
                
                $row = array_map('trim', $row);
                $record_id = $row[0];
                $this_genus = $row[1];
                $this_subgenus = $row[14];
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
                $this_is_extinct = $row[15];
                $this_has_preholocene = $row[16];
                $this_has_modern = $row[17];
                
                if ($this_kingdom == "Viruses" || $this_kingdom == "Subviral agents") {
                    $taxon = $taxon_with_italics = $this_species;
                } else {
                    $taxon = $this_genus . ' ' . (($this_subgenus != "") ?
                        "($this_subgenus) " : "") . $this_species;
                    $taxon_with_italics = "<i>$taxon</i>";
                }
                $taxon_level = "Species";
                
                $parent_hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class . "_" .
                    $this_order . "_" .  (($this_superfamily != "") ? $this_superfamily . "_" : "") .
                    $this_family . "_" . $this_genus . (($this_subgenus != "") ? "_" . $this_subgenus : "");
                    $hierarchy = $parent_hierarchy . "_" . $this_species;
                    
                $is_accepted_name = $this->isAccepted($this_sp2000_status_id);
                $parent_id = $this->getTaxaRecordId(['HierarchyCode' => $parent_hierarchy]);
                if (!$parent_id) {
                    if ($is_accepted_name == 1) {
                        $this->addMessage("No parent taxon found for species $taxon_with_italics
                    (parent genus <i>$this_genus</i>)");
                        continue;
                    } else {
                        $parent_id = 0;
                    }
                }
                if ($this_sp2000_status_id == "") {
                    $this->addMessage("No Species 2000 status found for species $taxon_with_italics (id: $record_id)");
                    // Ruud 27-01-11: don't insert into taxa table, dismiss instead
                    continue;
                }
                if ($this_database_id == "") {
                    $this->addMessage("No database ID found for species $taxon_with_italics (id: $record_id)");
                    // Ruud 27-01-11: don't insert into taxa table, dismiss instead
                    continue;
                }
                $this->taxaInsert([
                    $record_id, $taxon, $taxon_with_italics, $taxon_level, $this_name_code, $parent_id,
                    $this_sp2000_status_id, $this_database_id, $is_accepted_name, 1, $hierarchy,
                    $this_is_extinct, $this_has_preholocene, $this_has_modern
               ]);
            }
        }
        return $this;
    }
    
    private function addInfraspeciesToTaxa () 
    {
        $stmt = $this->query("
            SELECT t1.`record_id`, t1.`genus`, t1.`species`, t1.`infraspecies_marker`,
                t1.`infraspecies`, t1.`name_code`, t1.`sp2000_status_id`,
                t1.`accepted_name_code`, t1.`database_id`, t1.`family_id`, t2.`kingdom`, 
                t2.`phylum`, t2.`class`,  t2.`order`, t2.`superfamily`, t2.`family`, 
                t1.`subgenus`, t1.`infraspecies_parent_name_code`,
                t1.`is_extinct`, t1.`has_preholocene`, t1.`has_modern`
            FROM `scientific_names` AS t1
            LEFT JOIN `families` AS t2 ON t1.`family_id`= t2.`record_id`
            WHERE (t1.`infraspecies` != '' AND t1.`infraspecies` IS NOT NULL) AND 
                t1.`family_id` IS NOT NULL"
        );
        $total = $stmt->rowCount();
        echo "<br>$total infraspecies found in 'scientific_names' table<br>";

        $this->indicator->init($total, 100, 500);
        $family_id = $hierarchy = $parent_hierarchy = $parent_id = "";
        
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $this->indicator->iterate();
            $old_family_id = $family_id;
            $old_hierarchy = $hierarchy;
            $old_parent_hierarchy = $parent_hierarchy;
            
            $row = array_map('trim', $row);
            $record_id = $row[0];
            $this_genus = $row[1];
            $this_subgenus = $row[16];
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
            
            $this_is_extinct = $row[18];
            $this_has_preholocene = $row[19];
            $this_has_modern = $row[20];
            
            if ($this_kingdom == "Viruses" || $this_kingdom == "Subviral agents") {
                $taxon = $this_species;
                if ($this_infraspecies_marker != "") {
                    $taxon .= " $this_infraspecies_marker";
                }
                $taxon .= " $this_infraspecies";
                $taxon_with_italics = $taxon;
            } else {
                $species_part = $taxon = $this_genus . ' ' . (($this_subgenus != "") ? "($this_subgenus) " : "") . $this_species;
                if ($this_infraspecies_marker != "") {
                    $taxon = $species_part . " $this_infraspecies_marker";
                }
                $taxon .= " $this_infraspecies";
                if ($this_infraspecies_marker == "") {
                    $taxon_with_italics = "<i>$taxon</i>";
                } else {
                    $taxon_with_italics =
                        "<i>$species_part</i> $this_infraspecies_marker <i>$this_infraspecies</i>";
                }
            }
            $taxon_level = "Infraspecies";
            $parent_hierarchy = $this_kingdom . "_" . $this_phylum . "_" . $this_class . "_" . $this_order . "_" .
                (($this_superfamily != "") ? $this_superfamily . "_" : "") . $this_family . "_" . $this_genus . "_" .
                (($this_subgenus != "") ? $this_subgenus . "_" : "") . $this_species;
            $hierarchy = $parent_hierarchy . "_" . $this_infraspecies;
                
            // Ruud 09-11-10: moved this check up so it can be used to check infraspecies parent
            $is_accepted_name = $this->isAccepted($this_sp2000_status_id);
            
            // Ruud 22-08-14: fetching parent by hierarchy results in errors when the same name
            // occurs both as accepted name and synonym. Obviously it should be fetched by
            // infraspecies_parent_name_code, as this value is present in the table!!
            // $parent_id = getRecordIdInTaxa(array('HierarchyCode' => $parent_hierarchy));
            $parent_id = $this->getInfraspeciesParentId($row[17]);
            if (!$parent_id) {
                // Try again with genus as direct parent
                $parent_id = $this->getTaxaRecordId(['HierarchyCode' =>
                    substr($parent_hierarchy, 0, strrpos($parent_hierarchy, "_"))]);
                if (!$parent_id) {
                    if ($is_accepted_name == 1) {
                        $this->addMessage("No parent taxon found for species $taxon_with_italics
                            (parent genus <i>$this_genus</i>)");
                        continue;
                    } else {
                        $parent_id = 0;
                    }
                }
            }
            if ($this_sp2000_status_id == "") {
                $this->addMessage("No Species 2000 status found for infraspecies $taxon_with_italics (id: $record_id)");
                // Ruud 27-01-11: don't insert into taxa table, dismiss instead
                continue;
            }
            if ($this_database_id == "") {
                $this->addMessage("No database ID found for infraspecies $taxon_with_italics (id: $record_id)");
                // Ruud 27-01-11: don't insert into taxa table, dismiss instead
                continue;
            }
            // add taxon to 'taxa' table
            $this->taxaInsert([
                $record_id, $taxon, $taxon_with_italics, $taxon_level, $this_name_code, $parent_id,
                $this_sp2000_status_id, $this_database_id, $is_accepted_name, 1, '',
                $this_is_extinct, $this_has_preholocene, $this_has_modern
            ]);
        }
        return $this;
    }
    
    private function taxaInsert ($values = [])
    {
        // Only need to do this once
        if (!$this->taxaStmt) {
            $this->taxaStmt = $this->pdo->prepare("
                INSERT INTO `taxa` (`record_id`, `name`, `name_with_italics`, `taxon`, `name_code`,
                    `parent_id`, `sp2000_status_id`,`database_id`, `is_accepted_name`,
                   `is_species_or_nonsynonymic_higher_taxon`, `HierarchyCode`, `is_extinct`,
                    `has_preholocene`, `has_modern`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        }
        $this->taxaStmt->execute(array_values($values));
    }
    
    private function addMessage ($message)
    {
        $this->messages[] = $message;
    }
    
    private function clearMessages ()
    {
        $this->messages = [];
    }
    
    private function createTaxaTable ()
    {
        $this->query("DROP TABLE IF EXISTS `taxa`");
        $this->query("
            CREATE TABLE `taxa` (
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
                `HierarchyCode` varchar(1000) NOT NULL,
                `is_extinct` smallint(1) default 0,
                `has_preholocene` smallint(1) default 0,
                `has_modern` smallint(1) default 1,
                PRIMARY KEY  (`record_id`),
                KEY `name` (`name`,`is_species_or_nonsynonymic_higher_taxon`,`database_id`,`sp2000_status_id`),
                KEY `sp2000_status_id` (`sp2000_status_id`),
                KEY `parent_id` (`parent_id`),
                KEY `database_id` (`database_id`),
                KEY `taxon` (`taxon`),
                KEY `lsid` (`lsid`),
                KEY `is_accepted_name` (`is_accepted_name`),
                KEY `name_code` (`name_code`),
                KEY `is_species_or_nonsynonymic_higher_taxon` (`is_species_or_nonsynonymic_higher_taxon`),
                KEY `HierarchyCode` (`HierarchyCode`(255))
            ) ENGINE=MyISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;"
        );
    }
    
    private function getHigherTaxonRecordId () 
    {
        $stmt = $this->query('select record_id + 1 from taxa order by record_id desc limit 1');
        if ($stmt->rowCount() == 1) {
            return $stmt->fetch(PDO::FETCH_COLUMN);
        }
        $stmt = $this->query('select record_id + 1 from scientific_names order by record_id desc limit 1');
        return $stmt->fetch(PDO::FETCH_COLUMN);
    }
    
    private function getInfraspeciesParentId ($nameCode) {
        $stmt = $this->pdo->prepare('SELECT `record_id` FROM `scientific_names` WHERE `name_code` = ?');
        $stmt->execute([$nameCode]);
        $res = $stmt->fetch(PDO::FETCH_NUM);
        return $res ? $res[0] : false;
    }
    
    private function getTaxaRecordId ($fields = []) {
        $query = "SELECT `record_id` FROM `taxa` WHERE ";
        foreach ($fields as $column => $value) {
            $query .= "`$column` = ? AND ";
        }
        $stmt = $this->pdo->prepare(substr($query, 0, -4));
        $stmt->execute(array_values($fields));
        $res = $stmt->fetch(PDO::FETCH_NUM);
        return $res ? $res[0] : false;
    }
    
    private function isAccepted ($id)
    {
        return in_array($id, [self::$acceptedNameId, self::$provisionallyAcceptedNameId]) ? 1 : 0;
    }
        
    
    public static $acceptedNameId = 1;
    public static $provisionallyAcceptedNameId = 4;
    public static $acefTables = [
        "common_names" => [
            "record_id", "name_code", "common_name", "language", "country", "reference_id",
            "database_id", "is_infraspecies", "reference_code"
        ],
        "databases" => [
            "record_id", "database_name", "database_name_displayed", "database_full_name",
            "web_site", "organization", "contact_person", "taxa", "taxonomic_coverage",
            "abstract", "version", "release_date", "authors_editors", "accepted_species_names",
            "accepted_infraspecies_names", "species_synonyms", "infraspecies_synonyms",
            "species_synonyms", "infraspecies_synonyms", "common_names", "total_names"
        ],
        "distribution" => [
            "record_id",  "name_code", "distribution"
        ],
        "families" => [
            "record_id", "kingdom", "phylum", "class", "order", "family", "superfamily",
            "is_accepted_name", "database_id", "family_code"
        ],
        "references" => [
            "record_id", "author", "year", "title", "source", "database_id", "reference_code"
        ],
        "scientific_names" => [
            "record_id", "name_code", "web_site", "genus", "species", "infraspecies",
            "infraspecies_marker", "author", "accepted_name_code", "comment", "scrutiny_date",
            "sp2000_status_id", "database_id", "specialist_id", "family_id", "specialist_code",
            "family_code", "is_accepted_name"
        ],
        "scientific_name_references" => [
            "record_id", "name_code", "reference_type", "reference_id",  "reference_code"
        ],
        "specialists" => [
            "record_id", "specialist_name", "specialist_code"
        ],
        "sp2000_statuses" => [
            "record_id", "sp2000_status"
        ]
    ];
    public static $indices = [
        'scientific_names' => ['family_code', 'specialist_code'],
        'families' => ['family_code'],
        'specialists' => ['specialist_code'],
        'scientific_name_references' => ['reference_code'],
        'references' => ['reference_code'],
        'common_names' => ['reference_code']
    ];
    public static $fkFields = [
        ["scientific_names", "accepted_name_code", "scientific_names", "name_code"],
        ["scientific_names", "family_id", "families", "record_id"],
        ["scientific_names","sp2000_status_id","sp2000_statuses","record_id"],
        ["scientific_names","database_id","databases","record_id"],
        ["scientific_names","specialist_id","specialists","record_id"],
        ["common_names","reference_id","references","record_id"],
        ["common_names","database_id","databases","record_id"],
        ["common_names","name_code","scientific_names","name_code"],
        ["distribution","name_code","scientific_names","name_code"],
        ["references","database_id","databases","record_id"],
        ["scientific_name_references","name_code","scientific_names","name_code"],
        ["scientific_name_references","reference_id","references","record_id"],
    ];
}
