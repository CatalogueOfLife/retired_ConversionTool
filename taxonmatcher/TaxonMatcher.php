<?php

include 'TaxonMatcherException.php';
include 'TaxonMatcherEventListener.php';

class TaxonMatcher {

	private static $PREFIX_LSID = 'urn:lsid:catalogueoflife.org:taxon:';
	private static $UUID_START_POS = 36;
	private static $UUID_LENGTH = 36;

	// e.g. "2011"
	private $_currentEdition;
	// e.g. "2012"
	private $_nextEdition;

	// db connection params
	private $_dbHost = 'localhost';
	private $_dbUser;
	private $_dbPassword;
	private $_dbNameCTL;
	private $_currentDbName;
	private $_nextDbName;

	/**
	 * An array of TaxonMatcherEventListener objects.
	 * @var array
	 */
	private $_eventListeners = array();

	/**
	 * @var PDO
	 */
	private $_pdo;

	/**
	 * An SQL LIKE expression (without the LIKE keyword or SQL quotes)
	 * @var string
	 */
	private $_taxonNameFilter;

	// debug
	private $_readLimitClause = '';
	private $_showLimitClause = '';








	public function __construct()
	{
		// ...
	}

	public function run()
	{
		$this->_validateInput();
		$this->_setup();
		$this->_importAC($this->_currentDbName);
		$this->_importAC($this->_nextDbName);
	}


	/**
	 * Set DB host. Required. Default: "localhost"
	 * @param string $host
	 */
	public function setDbHost($host)
	{
		$this->_dbHost = $host;
	}


	/**
	 * Set DB user. Required.
	 * @param string $user
	 */
	public function setDbUser($user)
	{
		$this->_dbUser = $user;
	}


	/**
	 * Set DB password. Optional.
	 * @param string $password
	 */
	public function setDbPassword($password)
	{
		$this->_dbPassword = $password;
	}


	/**
	 * Set the identifier of the current edition of the CoL (e.g. "2011"). Required.
	 * @param string $edition
	 */
	public function setCurrentEdition($edition)
	{
		$this->_currentEdition = $edition;
	}


	/**
	 * Set the identifier of the next edition of the CoL (e.g. "2012"). Required.
	 * @param string $edition
	 */
	public function setNextEdition($edition)
	{
		$this->_nextEdition = $edition;
	}

	/**
	 * Set name of the "Cumulative Taxon List" database. Required.
	 * @param string $dbName
	 */
	public function setDbNameCTL($dbName)
	{
		$this->_dbNameCTL = $dbName;
	}


	/**
	 * Set name of the database containing the data for current edition of the CoL. Required.
	 * @param string $dbName
	 */
	public function setCurrentDbName($dbName)
	{
		$this->_currentDbName = $dbName;
	}

	/**
	 * Set name of the database containing the data for next edition of the CoL. Required.
	 * @param string $dbName
	 */
	public function setNextDbName($dbName)
	{
		$this->_nextDbName = $dbName;
	}

	/**
	 * Results in a WHERE clause that puts a constraint on the taxon name. Optional.
	 * @param string $filter
	 */
	public function setTaxonNameFilter($filter)
	{
		$this->_taxonNameFilter = $filter;
	}


	/**
	 * Set maxmimum number of records to display (debug option). Optional.
	 * Null or zero means no maximum.
	 * @param number $i
	 */
	public function setShowLimit($i)
	{
		if($i === null || $i < 1) {
			$this->_showLimitClause = '';
		}
		else {
			$this->_showLimitClause = ' LIMIT ' . $i;
		}
	}


	/**
	 * Set maxmimum number of records to fetch (debug option). Optional.
	 * Null or zero means no maximum.
	 * @param number $i
	 */
	public function setReadLimit($i)
	{
		if($i === null || $i < 1) {
			$this->_readLimitClause = '';
		}
		else {
			$this->_readLimitClause = ' LIMIT ' . $i;
		}
	}

	public function addEventListener(TaxonMatcherEventListener $listener)
	{
		$this->_eventListeners[] = $listener;
	}



	private function _validateInput()
	{
		if($this->_dbHost === null) {
			throw new InvalidInputException('Missing database host');
		}
		if($this->_dbUser === null) {
			throw new InvalidInputException('Missing database user');
		}
		if($this->_dbNameCTL === null) {
			throw new InvalidInputException('Missing database name for Cumulative Taxon List');
		}
		if($this->_currentDbName === null) {
			throw new InvalidInputException('Missing database name for current edition');
		}
		if($this->_dbNameImport2 === null) {
			throw new InvalidInputException('Missing database name for new edition');
		}
	}

	/**
	 * @throws PDOException
	 */
	private function _setup()
	{
		$this->_pdo = $this->_connect();
		$this->_createCTLDatabase();
	}


	private function _importAC($dbName)
	{

		if(self::_hasLSIDs($dbName)) {
			$sqlExpression = sprintf('SUBSTRING(AC.lsid,%s,%s)', self::$UUID_START_POS, self::$UUID_LENGTH);
		}
		else {
			$sqlExpression = 'NULL';
		}

		$this->_createTaxonTable();
		$this->_importSpecies($dbName);
		$this->_importLSIDsForSpecies($dbName, $sqlExpression);
		$this->_importCommonNames($dbName);
		$this->_copyCommonNamesToTaxonTable($dbName);
		$this->_importDistributionData($dbName);
		$this->_importGenera($dbName, $sqlExpression);
		$this->_importFamilies($dbName, $sqlExpression);
		$this->_importSuperFamilies($dbName, $sqlExpression);
		$this->_importOrders($dbName, $sqlExpression);
		$this->_importClasses($dbName, $sqlExpression);
		$this->_importPhyla($dbName, $sqlExpression);
		$this->_importKingdoms($dbName, $sqlExpression);
		$this->_concatenateIdentifierCompnents();

	}


	private function _listMatches($database)
	{
		$sql = <<<SQL
			SELECT COUNT(*), $database.databases.database_name
			  FROM Taxon, $database.databases
			 WHERE databaseId = $database.databases.record_id
			 GROUP BY databaseId
SQL;
		$statement = $this->_pdoCTL->query($sql);
		$this->_showResult($statement);
	}


	private function _showMatches()
	{
		$sql = <<<SQL
			SELECT edition, id, code, allData, nameCodes, lsid
			  FROM Taxon
			 WHERE sciNames like 'Allo%'
			 ORDER BY allData, edition
		 	{$this->_showLimitClause}
SQL;
		 	$statement = $this->_pdoCTL->query($sql);
		 	$this->_showResult($statement);
	}

	/**
	 * @throws TaxonMatcherException
	 */
	private function _createTaxonTable()
	{
		$sql = <<<SQL
			CREATE TABLE IF NOT EXISTS Taxon(
				edition      VARCHAR(15) NOT NULL DEFAULT '',
                edRecordId   INT(10) UNSIGNED NOT NULL,
		 		databaseId   INT(10) UNSIGNED,
		 		code         VARCHAR(137) NOT NULL DEFAULT '',
		 		lsid         VARCHAR(36),
		 		rank         VARCHAR(12),
				nameCodes    TEXT,
		 		sciNames     TEXT,
		 		commonNames  TEXT,
		 		distribution TEXT,
		 		otherData    TEXT,
		 		allData      TEXT, /* was NOT NULL in v.1.04, was DEFAULT '' in v.1.03 */
		 		INDEX        (edition),
		 		INDEX        (edRecordId),
		 		INDEX        (code),
		 		INDEX        (lsid)
			)
SQL;
		self::_exec($this->_pdo, $sql);
	}


	private function _importSpecies($dbName)
	{
		$edition = $this->_getEdition($dbName);
		$whereClause = $this->_taxonNameFilter === null ? "" : "WHERE genus LIKE '{$this->_taxonNameFilter}'";
		$this->_disableKeys('Taxon');
		$sql = <<<SQL
			INSERT INTO `{$this->_dbNameCTL}`.Taxon(
							edition,
							edRecordId,
							databaseId,
							code,
							rank,
							nameCodes,
							sciNames,
							otherData)
					SELECT
							'{$edition}',
							0,
							S.database_id,
							S.accepted_name_code,
							'(sp/infra)',
			                GROUP_CONCAT(S.name_code ORDER BY sp2000_status_id SEPARATOR '; '),
			                GROUP_CONCAT(CONCAT_WS(' ', genus, species, NULLIF(infraspecies_marker, ''), infraspecies, author)
			                                       ORDER BY sp2000_status_id, genus, species, infraspecies, author
			                                       SEPARATOR ', '),
			                S.accepted_name_code
					 FROM {$dbName}.scientific_names S
					 {$whereClause}
					GROUP BY S.accepted_name_code
					{$this->_readLimitClause}
SQL;

					$this->_exec($sql);
					$this->_enableKeys('Taxon');
	}

	private function _importLSIDsForSpecies($dbName, $sqlExpression)
	{
		$sql = <<<SQL
			UPDATE Taxon T, {$dbName}.taxa AC
			   SET T.lsid = {$sqlExpression}
			 WHERE T.code = AC.name_code
SQL;
		$this->_exec($sql);
	}


	private function _importCommonNames($dbName)
	{
		$edition = $this->_getEdition($dbName);
		$this->_exec("DROP TABLE IF EXISTS `{$this->_dbNameCTL}`.CommonName");
		$sql = <<<SQL
			CREATE TABLE `{$this->_dbNameCTL}`CommonName(
						edition     VARCHAR(15) NOT NULL DEFAULT '',
						code        VARCHAR(137) NOT NULL DEFAULT '',
						commonNames TEXT,
						INDEX (code))
			SELECT
						T.edition,
						T.code,
		                GROUP_CONCAT(CONCAT_WS('/', common_name, `language`, country) ORDER BY common_name, `language`, country SEPARATOR ', ') AS commonNames
              FROM Taxon T, `{$dbName}`.common_names C
             WHERE T.edition = '{$edition}'
               AND T.code = C.name_code
             GROUP BY C.name_code
             {$this->_readLimitClause}
SQL;
             $this->_exec($sql);
             $this->_exec("DROP TABLE `{$this->_dbNameCTL}`.CommonName");
	}


	private function _copyCommonNamesToTaxonTable($dbName)
	{
		$edition = $this->_getEdition($dbName);
		$sql = <<<SQL
			UPDATE `{$this->_dbNameCTL}`.Taxon T
			     , `{$this->_dbNameCTL}`.CommonName C
			   SET T.commonNames = C.commonNames
			 WHERE T.code = C.code AND T.edition = '$edition'
SQL;
		$this->_exec($sql);
	}


	private function _importDistributionData($dbName)
	{
		$edition = $this->_getEdition($dbName);
		$sql = <<<SQL
			UPDATE `{$this->_dbNameCTL}`.Taxon T,
			     , `{$dbName}`.distribution D
			   SET T.distribution = D.distribution
			 WHERE T.code = D.name_code AND T.edition = '$edition'
SQL;
		$this->_exec($sql);
	}


	private function _importGenera($dbName,$sqlExpression)
	{
		$edition = $this->_getEdition($dbName);
		$whereClause = $this->_taxonNameFilter === null? "" : "AND AC.name LIKE '{$this->_taxonNameFilter}'";
		$sql = <<<SQL
			INSERT INTO `{$this->_dbNameCTL}`.Taxon (
							edition,
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT
							'{$edition}',
							AC.record_id,
							AC.database_id,
							CAST(AC.record_id AS CHAR),
							{$sqlExpression},
							'Genus',
							'',
							CONCAT_WS(': ', AC.name, P1.name, P2.name, P3.name, P4.name, P5.name)
					  FROM `{$dbName}`.taxa AC,
					  	   `{$dbName}`.taxa P1,
					  	   `{$dbName}`.taxa P2,
					  	   `{$dbName}`.taxa P3,
					  	   `{$dbName}`.taxa P4,
					  	   `{$dbName}`.taxa P5
					  WHERE AC.taxon = 'Genus' AND AC.is_accepted_name = 1
					    {$whereClause}
					    AND AC.parent_id = P1.record_id AND P1.parent_id = P2.record_id
					    AND P2.parent_id = P3.record_id AND P3.parent_id = P4.record_id
					    AND P4.parent_id = P5.record_id
					    {$this->_readLimitClause}
SQL;
					    $this->_exec($sql);
	}

	private function _importFamilies($dbName, $sqlExpression)
	{
		$edition = $this->_getEdition($dbName);
		$whereClause = $this->_taxonNameFilter === null? "" : "AND family LIKE '{$this->_taxonNameFilter}'";
		$sql = <<<SQL
			INSERT INTO `{$this->_dbNameCTL}`.Taxon (
							edition,
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames,
							otherData)
					SELECT
						    '{$edition}',
						    AC.record_id,
						    F.database_id,
						    CONCAT_WS(': ', family, `order`, `class`, phylum, kingdom),
						    {$sqlExpression},
						    'Family',
						    hierarchy_code,
						    if(superfamily = '' or superfamily is null,
						    		CONCAT_WS(': ', family, `order`, `class`, phylum, kingdom),
						    		CONCAT_WS(': ', family, superfamily, `order`, `class`, phylum, kingdom)
						    ),
						    AC.name
					  FROM  `{$dbName}`.families F,
					  	    `{$dbName}`.taxa AC
					 WHERE  taxon = 'Family'
					   AND  name = family
					   {$whereClause}
					   {$this->_readLimitClause}
SQL;
					   $this->_exec($sql);
	}


	private function _importSuperFamilies($dbName, $sqlExpression)
	{
		$edition = $this->_getEdition($dbName);
		$whereClause = $this->_taxonNameFilter === null? "" : "AND superfamily LIKE '{$this->_taxonNameFilter}'";
		$sql = <<<SQL
			INSERT INTO `{$this->_dbNameCTL}`.Taxon (
							edition,
							edRecordId,
							databaseId,
							code, lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT
						    '{$edition}',
						    AC.record_id,
						    F.database_id,
						    CONCAT_WS(': ', superfamily, `order`, `class`, phylum, kingdom),
						    {$sqlExpression},
						    'Superfamily',
						    hierarchy_code,
						    CONCAT_WS(': ', superfamily, `order`, `class`, phylum, kingdom)
					  FROM  `{$dbName}`.families F,
					  	    `{$dbName}`.taxa AC
					 WHERE  taxon = 'Superfamily'
					   AND  name = superfamily
					   {$whereClause}
					 GROUP  BY superfamily, `order`
					 {$this->readLimitClause}
SQL;
					 $this->_exec($sql);
	}


	private function _importOrders($dbName, $sqlExpression)
	{
		$edition = $this->_getEdition($dbName);
		$whereClause = $this->_taxonNameFilter === null? "" : "AND `order` LIKE '{$this->_taxonNameFilter}'";
		$sql = <<<SQL
			INSERT INTO Taxon (
							edition,
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT  '{$edition}',
							AC.record_id,
							F.database_id,
							CONCAT_WS(': ', `order`, `class`, phylum, kingdom),
							{$sqlExpression},
							'Order',
							hierarchy_code,
							CONCAT_WS(': ', `order`, `class`, phylum, kingdom)
					  FROM  `{$dbName}`.families F,
					  		`{$dbName}`.taxa AC
					 WHERE  taxon = 'Order'
					   AND  name = `order`
					   {$whereClause}
					 GROUP  BY `order`, `class`
					 {$this->readLimitClause}
SQL;
					 $this->_exec($sql);
	}


	private function _importClasses($dbName, $sqlExpression)
	{
		$edition = $this->_getEdition($dbName);
		$whereClause = $this->_taxonNameFilter === null? "" : "AND `class` LIKE '{$this->_taxonNameFilter}'";
		$sql = <<<SQL
			INSERT INTO Taxon (
							edition,
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT  '{$edition}',
							AC.record_id,
							F.database_id,
							CONCAT_WS(': ', `class`, phylum, kingdom),
							{$sqlExpression},
							'Class',
							hierarchy_code,
							CONCAT_WS(': ', `class`, phylum, kingdom)
					  FROM  `{$dbName}`.families F,
					  		`{$dbName}`.taxa AC
					 WHERE  taxon = 'Class'
					   AND  name = `class`
					   {$whereClause}
					 GROUP  BY `class`, phylum
					 {$this->readLimitClause}
SQL;
					 $this->_exec($sql);
	}


	private function _importPhyla($dbName, $sqlExpression)
	{
		$edition = $this->_getEdition($dbName);
		$whereClause = $this->_taxonNameFilter === null? "" : "AND `class` LIKE '{$this->_taxonNameFilter}'";
		$sql = <<<SQL
			INSERT INTO Taxon (
							edition,
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT  '{$edition}',
							AC.record_id,
							F.database_id,
							CONCAT_WS(': ', phylum, kingdom),
							{$sqlExpression},
							'Class',
							hierarchy_code,
							CONCAT_WS(': ', phylum, kingdom)
					  FROM  `{$dbName}`.families F,
					  		`{$dbName}`.taxa AC
					 WHERE  taxon = 'Phylum'
					   AND  name = phylum
					   {$whereClause}
					 GROUP  BY phylum, kingdom
					 {$this->readLimitClause}
SQL;
					 $this->_exec($sql);
	}


	private function _importKingdoms($dbName, $sqlExpression)
	{
		$edition = $this->_getEdition($dbName);
		$whereClause = $this->_taxonNameFilter === null? "" : "AND `class` LIKE '{$this->_taxonNameFilter}'";
		$sql = <<<SQL
			INSERT INTO Taxon (
							edition,
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT  '{$edition}',
							AC.record_id,
							F.database_id,
							CONCAT_WS(': ', kingdom, '(top-level domain)'),
							{$sqlExpression},
							'Kingdom',
							hierarchy_code,
							CONCAT_WS(': ', phylum, kingdom)
					  FROM  `{$dbName}`.families F,
					  		`{$dbName}`.taxa AC
					 WHERE  taxon = 'Kingdom'
					   AND  name = kingdom
					   {$whereClause}
					 GROUP  BY kingdom
					 {$this->readLimitClause}
SQL;
					 $this->_exec($sql);
	}


	private function _concatenateIdentifierComponents()
	{
		$sql = <<<SQL
				UPDATE `{$this->_dbNameCTL}`.Taxon
				   SET allData = IFNULL(REPLACE(
				   							CONCAT_WS('; ', TRIM(sciNames), TRIM(commonNames), TRIM(distribution), TRIM(otherData)),
											'  ',
											' '),
										'')
SQL;
		$this->_exec($sql);
	}


	private function _compareEditions()
	{
		$this->_exec("ALTER TABLE Taxon ADD INDEX (allData(100))");
		$this->_exec("ALTER TABLE Taxon ENABLE KEYS");
		$this->_exec("OPTIMIZE TABLE Taxon");
		$this->_exec("
				UPDATE Taxon T1, Taxon T2
				SET T2.lsid = T1.lsid
				WHERE T1.allData = T2.allData
				AND T1.edition = '{$this->_currentEdition}'
				AND T2.edition = '{$this->_nextEdition}'
				");
		$this->_exec("
				UPDATE Taxon
				SET lsid = UUID()
				WHERE edition = '{$this->_nextEdition}'
				AND lsid IS NULL
				");
	}


	private function _addLSIDs()
	{
		
		$this->_useDatabase($this->_dbNameCTL);
		
		$sql = <<<SQL
			UPDATE `{$this->_nextDbName}`.taxa A, Taxon T
			   SET A.lsid = T.lsid
			 WHERE T.edition = '{$this->_nextEdition}' AND A.is_accepted_name = 1
			   AND T.code = A.name_code		
SQL;
		$this->_exec($sql);

		
		$sql = <<<SQL
			UPDATE `{$this->_nextDbName}`.taxa A, Taxon T
			   SET A.lsid = T.lsid
			 WHERE T.edition = '{$this->_nextEdition}' AND A.is_accepted_name = 1
			   AND T.edRecordId = A.record_id
SQL;
		$this->_exec($sql);
		

		$prefix = self::$PREFIX_LSID;
		$sql = <<<SQL
			UPDATE `{$this->_nextDbName}`.taxa
			   SET lsid = CONCAT('$prefix', lsid, ':{$this->_nextEdition}')
			 WHERE is_accepted_name = 1
SQL;
		$this->_exec($sql);		
		
	}

	/**
	 * @param string $dbName Must be either $this->_currentDbName or $this->_dbNameImport2
	 * @return boolean
	 */
	private function _hasLSIDs($dbName)
	{
		return (0 != $this->_fetchOne("SELECT COUNT(*) FROM `{$dbName}`.Taxon"));
	}

	private function _getEdition($dbName)
	{
		return $dbName === $this->_currentDbName ? $this->_currentEdition : $this->_nextEdition;
	}


	private function _countTaxa()
	{
		$count =  $this->_fetchOne('SELECT COUNT(*) FROM Taxon');
		$this->_printAndLogEntry('Number of records in Taxon table: ' . $count);
	}


	private function _countTaxaWithLsids()
	{
		return $this->_fetchOne('SELECT COUNT(*) FROM Taxon WHERE LENGTH(lsid) = 36');
	}


	private function _emptyCTL()
	{
		$this->_exec("DROP TABLE IF EXISTS `{$this->_dbNameCTL}`.Taxon");
	}


	private function _showResult(PDOStatement $statement, $colWidth=15)
	{
		$rowNum = 0;
		while(($row = $statement->fetch()) !== false) {
			$line = self::_showRow($row, ++$rowNum, $colWidth);
			$this->_output($line);
		}
	}


	private static function _showRow(array $row, $rowNum, $colWidth)
	{
		$line = array();
		$line[] = str_pad($rowNum, 5, '0', STR_PAD_LEFT);
		foreach($row as $column) {
			if(strlen($column) >= $colWidth) {
				$line[] = substr($column, 0, ($colWidth - 4)) . '****';
			}
			else {
				$line[] = str_pad($column, $colWidth);
			}
		}
		return implode(' | ', $line);
	}


	private function _fetchOne($sql)
	{
		return $this->_query($sql)->fetchColumn();
	}


	/**
	 * @param string $table
	 * @throws TaxonMatcherException
	 */
	private function _disableKeys($table)
	{
		$this->_exec('ALTER TABLE ' . $table . ' DISABLE KEYS');
	}


	/**
	 * @param string $table
	 * @throws TaxonMatcherException
	 */
	private function _enableKeys($table)
	{
		$this->_exec('ALTER TABLE ' . $table . ' ENABLE KEYS');
	}


	/**
	 * @throws TaxonMatcherException
	 */
	private function _createCTLDatabase()
	{
		$this->_exec("CREATE DATABASE IF NOT EXISTS `{$this->_dbNameCTL}`");
	}


	/**
	 * @throws TaxonMatcherException
	 */
	private function _useDatabase($dbName)
	{
		$this->_exec('USE DATABASE `' . $dbName . '`');
	}


	/**
	 * Executes a SELECT query.
	 *
	 * @param string $sql
	 * @throws TaxonMatcherException
	 * @return PDOStatement
	 */
	private function _query($sql)
	{
		$this->_debug(__METHOD__ . ' Executing SQL: ' . $sql);
		$statement = $this->_pdo->query($sql);
		if($statement === false) {
			$error = $this->_pdo->errorInfo();
			$this->_throwException(new TaxonMatcherException($error[2]));
		}
		return $statement;
	}


	/**
	 * Executes a non-SELECT query.
	 *
	 * @param string $sql
	 * @throws TaxonMatcherException
	 * @return number
	 */
	private function _exec($sql) {
		$this->_debug(__METHOD__ . ' Executing SQL: ' . $sql);
		$i = $this->_pdo->exec($sql);
		if($i === false) {
			$error = $this->_pdo->errorInfo();
			$this->_throwException(new TaxonMatcherException($error[2]));
		}
		return $i;
	}


	/**
	 * Get a connection to the database containing the three databases that we are
	 * working with (Cumulative Taxon List, current CoL, next CoL).
	 *
	 * @throws PDOException
	 * @return PDO
	 */
	private function _getPDO()
	{
		try {
			$dsn = 'mysql:host=' . $this->_dbHost;
			$options = array();
			$options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES \'UTF8\'';
			return new PDO($dsn, $this->_dbUser, $this->_dbPassword);
		}
		catch(PDOException $e) {
			$this->_throwException($e, 'Cannot connect to database server using the specified connection parameters');
		}
	}


	private function _throwException(Exception $exception, $message = null)
	{
		if($message === null) {
			$message = $exception->getMessage();
		}
		$this->_error($message, $exception);
		throw $exception;
	}

	private function _error($message, Exception $exception = null)
	{
		foreach($this->_eventListeners as $listener) {
			$listener->onMessage(TaxonMatcherEventListener::MESSAGE_TYPE_ERROR, $message, $exception);
		}
	}


	private function _warning($message)
	{
		foreach($this->_eventListeners as $listener) {
			$listener->onMessage(TaxonMatcherEventListener::MESSAGE_TYPE_WARNING, $message);
		}
	}


	private function _info($message)
	{
		foreach($this->_eventListeners as $listener) {
			$listener->onMessage(TaxonMatcherEventListener::MESSAGE_TYPE_INFO, $message);
		}
	}
	
	
	private function _output($message)
	{
		foreach($this->_eventListeners as $listener) {
			$listener->onMessage(TaxonMatcherEventListener::MESSAGE_TYPE_OUTPUT, $message);
		}
	}
	
	
	private function _debug($message)
	{
		foreach($this->_eventListeners as $listener) {
			$listener->onMessage(TaxonMatcherEventListener::MESSAGE_TYPE_DEBUG, $message);
		}
	}
	

}