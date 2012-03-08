<?php

include 'TaxonMatcherException.php';

class TaxonMatcher {
	
	const PREFIX_LSID = 'urn:lsid:catalogueoflife.org:taxon:';

	// logging
	private $_logging = false;
	private $_logFileDir;
	private $_logFileName;


	// db
	private $_dbHost;
	private $_dbUser;
	private $_dbPassword;
	private $_dbNameCTL;
	private $_dbNameImport1;
	private $_dbNameImport2;
	/**
	 * Connection to Cumulative Taxon List database
	 * @var PDO
	 */
	private $_pdoCTL;
	/**
	 * @var PDO
	 */
	private $_pdoImport1;
	/**
	 * @var PDO
	 */
	private $_pdoImport2;


	private $_lsidsPresent = array();



	// debug
	private $_readLimitClause = '';
	private $_showLimitClause = '';








	public function __construct()
	{
		// ...
	}


	/**
	 * Set DB host.
	 * @param string $host
	 */
	public function setDbHost($host)
	{
		$this->_dbHost = $host;
	}


	/**
	 * Set DB user.
	 * @param string $user
	 */
	public function setDbUser($user)
	{
		$this->_dbUser = $user;
	}


	/**
	 * Set DB password.
	 * @param string $password
	 */
	public function setDbPassword($password)
	{
		$this->_dbPassword = $password;
	}


	/**
	 * Set name of the "Cumulative Taxon List" database.
	 * @param string $dbName
	 */
	public function setDbNameCTL($dbName)
	{
		$this->_dbNameCTL = $dbName;
	}


	/**
	 * Set name of the database for 1st import.
	 * @param string $dbName
	 */
	public function setDbNameImport1($dbName)
	{
		$this->_dbNameImport1 = $dbName;
	}

	/**
	 * Set name of the database for 1st import.
	 * @param string $dbName
	 */
	public function setDbNameImport2($dbName)
	{
		$this->_dbNameImport2 = $dbName;
	}


	/**
	 * Set maxmimum number of records to display (debug option). Null or zero
	 * means no maximum.
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
	 * Set maxmimum number of records to fetch (debug option). Null or zero
	 * means no maximum .
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


	/**
	 * @throws PDOException
	 */
	public function initialize()
	{
		$this->_pdoCTL = $this->_getPDOForCumulativeTaxonList();
		$this->_pdoImport1 = $this->_getPDO($this->_dbNameImport1);
		$this->_pdoImport2 = $this->_getPDO($this->_dbNameImport2);
	}

	private function _importAC()
	{
		$this->_createTaxonTable();
		$this->_insertSpeciesAndLowerTaxaWithSynonyms($edition, $database, $whereClause);
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

	private function _insertSpeciesAndLowerTaxaWithSynonyms($edition, $database, $whereClause)
	{
		self::_disableKeys($this->_pdoCTL, 'Taxon');
		$sql = <<<SQL
			INSERT INTO Taxon(
							edition,
							edRecordId,
							databaseId,
							code,
							rank,
							nameCodes,
							sciNames,
							otherData)
					SELECT
							'$edition',
							0,
							S.database_id,
							S.accepted_name_code,
							'(sp/infra)',
			                GROUP_CONCAT(S.name_code ORDER BY sp2000_status_id SEPARATOR '; '),
			                GROUP_CONCAT(CONCAT_WS(' ', genus, species, NULLIF(infraspecies_marker, ''), infraspecies, author)
			                                       ORDER BY sp2000_status_id, genus, species, infraspecies, author
			                                       SEPARATOR ', '),
			                S.accepted_name_code
					 FROM $database.scientific_names S $whereClause
					GROUP BY S.accepted_name_code
					{$this->_readLimitClause}
SQL;
					self::_exec($this->_pdoCTL, $sql);
					self::_enableKeys($this->_pdoCTL, 'Taxon');
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
		$this->_pdoCTL->exec($sql);
	}


	private function _countTaxa()
	{
		$count =  self::_fetchOne($this->_pdoCTL, 'SELECT COUNT(*) FROM Taxon');
		$this->_printAndLogEntry('Number of records in Taxon table: ' . $count);
	}
	

	private function _countTaxaWithLsids()
	{
		return self::_fetchOne($this->_pdoCTL, 'SELECT COUNT(*) FROM Taxon WHERE LENGTH(lsid) = 36');
	}
	
	
	private function _emptyCTL()
	{
		self::_exec($this->_pdoCTL, 'DROP TABLE IF EXISTS Taxon');
	}
	

	private function _showResult(PDOStatement $statement, $colWidth=15)
	{
		$rowNum = 0;
		while(($row = $statement->fetch()) !== false) {
			$line = self::_showRow($row, ++$rowNum, $colWidth);
			$this->_printAndLog($line);
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


	private static function _fetchOne(PDO $pdo, $sql)
	{
		return self::_query($pdo, $sql)->fetchColumn();
	}


	/**
	 * @param PDO $pdo
	 * @param string $table
	 * @throws TaxonMatcherException
	 */
	private static function _disableKeys(PDO $pdo, $table)
	{
		$pdo->exec('ALTER TABLE ' . $table . ' DISABLE KEYS');
	}


	/**
	 * @param PDO $pdo
	 * @param string $table
	 * @throws TaxonMatcherException
	 */
	private static function _enableKeys(PDO $pdo, $table)
	{
		$pdo->exec('ALTER TABLE ' . $table . ' ENABLE KEYS');
	}


	/**
	 * @return PDOStatement
	 */
	private static function _query(PDO $pdo, $sql)
	{
		$statement = $pdo->query($sql);
		if($statement === false) {
			$err = $pdo->errorInfo();
			$this->logAndThrowException($err[2]);
		}
		return $statement;
	}


	/**
	 * @param PDO $pdo
	 * @param string $sql
	 * @throws TaxonMatcherException
	 * @return number
	 */
	private static function _exec(PDO $pdo, $sql) {
		$i = $pdo->exec($sql);
		if($i === false) {
			$err = $pdo->errorInfo();
			$this->logAndThrowException($err[2]);
		}
		return $i;
	}


	/**
	 * @throws TaxonMatcherException
	 */
	private function _getPDOForCumulativeTaxonList()
	{
		$pdo = $this->_getPDO();
		$pdo->exec('CREATE DATABASE IF NOT EXISTS ' . $this->_dbNameCTL);
		$pdo->exec('USE ' .  $this->_dbNameCTL);
		return $pdo;
	}

	/**
	 * @param string $dbName
	 * @throws PDOException
	 * @return PDO
	 */
	private function _getPDO($dbName=null)
	{
		try {
			$dsn = 'mysql:host=' . $this->_dbHost;
			if($dbName !== null) {
				$dsn .= ';dbname=' . $dbName;
			}
			$options = array();
			$options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES \'UTF8\'';
			return new PDO($dsn, $this->_dbUser, $this->_dbPassword);
		}
		catch(PDOException $e) {
			$this->_printAndLogEntry("Cannot connect to database \"{$dbName}\" using the specified connection parameters");
			throw $e;
		}
	}


	private function _beginLogFile()
	{
		if(!is_dir($this->_logFileDir)) {
			throw new Exception("No such directory: " . $this->_logFileDir);
		}
		if(!is_writable($this->_logFileDir)) {
			throw new Exception("Cannot create log file in non-writable directory: " . $this->_logFileDir);
		}
		$this->_logFileName = realpath($this->_logFileDir . '/TaxonMatcher.' . date('ymdHis') . '.log');
	}

	private function _printOrLogEntry()
	{
		$message = implode(' ', func_get_args());
		if($this->_logging) {
			$this->_printLogEntry($message);
		}
		else {
			$this->_stdout($message);
		}
	}

	private function _printOrLog()
	{
		$message = implode(' ', func_get_args());
		if($this->_logging) {
			$this->_printLog($message);
		}
		else {
			$this->_stdout($message);
		}
	}

	private function _printAndLogEntry()
	{
		$message = implode(' ', func_get_args());
		$this->_printLogEntry($message);
		$this->_stdout("$message");
	}

	/**
	 * @throws TaxonMatcherException
	 */
	private function logAndThrowException()
	{
		$message = implode(' ', func_get_args());
		$this->_printLogEntry($message);
		throw new TaxonMatcherException($message);
	}

	private function _printAndLog()
	{
		$message = implode(' ', func_get_args());
		$this->_printLog($message);
		$this->_stdout("$message");
	}

	private function _printLogEntry()
	{
		$message = implode(' ', func_get_args());
		if($this->_logging) {
			$datetime = date('y-m-d H:i:s [u]');
			$this->_printLog($datetime, $message);
		}
	}

	private function _printLog()
	{
		$message = implode(' ', func_get_args());
		if($this->_logging) {
			file_put_contents($this->_logFileName, $message . "\n", FILE_APPEND);
		}
	}

	private function _stdout()
	{
		$message = implode(' ', func_get_args());
		print("\n" . $message);
	}

	private function _endLogFile()
	{
		$this->_printLogEntry("(log file \"$logFileName\" closed)");
		$this->_logging = false;
	}


}