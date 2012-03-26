<?php
/**
 *
 * @author Ayco Holleman, ETI BioInformatics
 * @author Richard White (original PERL implementation), Cardiff University
 *
 * <p>
 * The central class in the PHP taxon matcher module. Its most important method is the
 * run() method, which matches taxa between current and upcoming CoL editions,
 * copies LSIDs from the current edition to the upcoming edition for matching taxa,
 * and assigns new LSIDs to apparently new taxa in the upcoming edition.
 * </p>
 *
 * <p>
 * Before you can run the TaxonMatcher, you must configure it using a series of
 * setters, some of which are optional (see the setXXX methods). If you want to
 * receive any output from your TaxonMatcher instance, you must attach a
 * {@link TaxonMatcherEventListener} to it.
 * </p>
 *
 * <p>
 * The taxonmatcher-cli.php script provides a concrete example of how to instantiate,
 * configure and run a TaxonMatcher.
 * </p>
 */

interface_exists('TaxonMatcherEventListener', false) || include 'TaxonMatcherEventListener.php';
class_exists('TaxonMatcherException', false) || include 'TaxonMatcherException.php';
class_exists('InvalidInputException', false) || include 'InvalidInputException.php';

class TaxonMatcher {

	private static $PREFIX_LSID = 'urn:lsid:catalogueoflife.org:taxon:';
	private static $UUID_START_POS = 36;
	private static $UUID_LENGTH = 36;

	// The suffix for LSIDs in the new Col Edition (everything after the last colon).
	private $_lsidSuffix;

	// db connection params
	private $_dbHost = 'localhost';
	private $_dbUser;
	private $_dbPassword;
	// The staging area database. Formerly called the "Cumulative Taxon List".
	private $_dbNameStage;
	// The name of the database containing the data for the current CoL.
	private $_dbNameCurrent;
	// The name of the database containing the data for the upcoming CoL.
	private $_dbNameNext;

	private $_resetLSIDs = true;

	/**
	 * An array of TaxonMatcherEventListener objects.
	 * @var array
	 */
	private $_listeners = array();

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








	public function __construct()
	{
		// ...
	}


	public function run()
	{
		try {
			$start = time();
			$this->_validateInput();
			$this->_connect();
			$this->_initializeStagingArea();
			if($this->_resetLSIDs) {
				$this->_resetLSIDs();
			}
			$this->_importAC($this->_dbNameCurrent);
			$this->_importAC($this->_dbNameNext);
			$this->_generateLogicalKey();
			$this->_compareEditions();
			$this->_addLSIDs();
			$timer = self::_getTimer(time() - $start);
			$this->_info(sprintf("Total duration: %02d:%02d:%02d", $timer['H'], $timer['i'], $timer['s']));
		}
		catch(Exception $e) {
			$this->_error($e->getMessage(), $e);
		}
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
	 * Set the suffix for LSIDs in the new Col Edition (everything after the last colon). Required.
	 * @param string $suffix
	 */
	public function setLSIDSuffix($suffix)
	{
		$this->_lsidSuffix = $suffix;
	}


	/**
	 * Set name of the staging area database, formerly called the "Cumulative Taxon List". Required.
	 * @param string $dbName
	 */
	public function setDbNameStage($dbName)
	{
		$this->_dbNameStage = $dbName;
	}


	/**
	 * Set name of the database containing the data for current edition of the CoL. Required.
	 * @param string $dbName
	 */
	public function setDbNameCurrent($dbName)
	{
		$this->_dbNameCurrent = $dbName;
	}


	/**
	 * Set name of the database containing the data for next edition of the CoL. Required.
	 * @param string $dbName
	 */
	public function setDbNameNext($dbName)
	{
		$this->_dbNameNext = $dbName;
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
	 * Whether or not to first erase all LSIDs in the new AC.
	 * @param boolean $bool
	 */
	public function setResetLSIDs($bool)
	{
		$this->_resetLSIDs = $bool;
	}


	/**
	 * Set maxmimum number of records to fetch (debug option). Optional.
	 * Null or zero means no maximum.
	 * @param number $i
	 */
	public function setReadLimit($i)
	{
		if((int) $i > 0) {
			$this->_readLimitClause = ' LIMIT ' . $i;
		}
		else {
			$this->_readLimitClause = '';
		}
	}


	public function addEventListener(TaxonMatcherEventListener $listener)
	{
		$this->_listeners[] = $listener;
	}


	private function _validateInput()
	{
		if($this->_dbHost === null) {
			throw new InvalidInputException('Not set: database host');
		}
		if($this->_dbUser === null) {
			throw new InvalidInputException('Not set: database user');
		}
		if($this->_dbPassword === null) {
			$this->_warning('Not set: database password');
		}
		if($this->_dbNameStage === null) {
			throw new InvalidInputException('Not set: database name of staging area');
		}
		if($this->_dbNameCurrent === null) {
			throw new InvalidInputException('Not set: database name of current edition of CoL');
		}
		if($this->_dbNameNext === null) {
			throw new InvalidInputException('Not set: database name of upcoming edition of CoL');
		}
	}


	private function _importAC($dbName)
	{

		$edition = $this->_getEdition($dbName);

		$this->_info("Importing data from database $dbName");

		if($this->_hasLSIDs($dbName)) {
			$sqlExpression = sprintf('SUBSTRING(AC.lsid,%s,%s)', self::$UUID_START_POS, self::$UUID_LENGTH);
		}
		else {
			$sqlExpression = "''";
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

	}


	private function _importSpecies($dbName)
	{
		$this->_info('Importing species and lower taxa');
		$edition = $this->_getEdition($dbName);
		$whereClause = $this->_taxonNameFilter === null ? "" : "WHERE genus LIKE '{$this->_taxonNameFilter}'";
		$this->_disableKeys('Taxon');
		$sql = <<<SQL
			INSERT INTO `{$this->_dbNameStage}`.Taxon(
							edition,
							edRecordId,
							databaseId,
							code,
							rank,
							nameCodes,
							sciNames,
							otherData)
					SELECT
							{$edition},
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
		$this->_info('Importing LSIDs for species and lower taxa');


		$sql = <<<SQL
			UPDATE `{$this->_dbNameStage}`.Taxon T
			  LEFT JOIN `{$dbName}`.taxa AC ON (T.code = AC.name_code)
			   SET T.lsid = {$sqlExpression}
			 WHERE AC.name_code IS NOT NULL
SQL;


		/*
		 $sql = <<<SQL
		UPDATE `{$this->_dbNameStage}`.Taxon T, `{$dbName}`.taxa AC
		SET T.lsid = {$sqlExpression}
		WHERE (T.code = AC.name_code)
		SQL;
		*/
		$this->_exec($sql);
	}


	private function _importCommonNames($dbName)
	{
		$this->_info('Importing common names');
		$edition = $this->_getEdition($dbName);
		$sql = <<<SQL
			INSERT INTO `{$this->_dbNameStage}`.CommonName(
						edition,
						code,
						commonNames)
			SELECT
						T.edition,
						T.code,
		                GROUP_CONCAT(CONCAT_WS('/', common_name, `language`, country) ORDER BY common_name, `language`, country SEPARATOR ', ') AS commonNames
              FROM `{$this->_dbNameStage}`.Taxon T, `{$dbName}`.common_names C
             WHERE T.edition = {$edition}
               AND T.code = C.name_code
             GROUP BY C.name_code
             {$this->_readLimitClause}
SQL;
             $this->_exec($sql);
	}


	private function _copyCommonNamesToTaxonTable($dbName)
	{
		$this->_info('Copying common names to Taxon table');
		$edition = $this->_getEdition($dbName);
		$sql = <<<SQL
			UPDATE `{$this->_dbNameStage}`.Taxon T
			     , `{$this->_dbNameStage}`.CommonName C
			   SET T.commonNames = C.commonNames
			 WHERE T.code = C.code AND T.edition = $edition
SQL;
		$this->_exec($sql);
	}


	private function _importDistributionData($dbName)
	{
		$this->_info('Importing distribution data');
		$edition = $this->_getEdition($dbName);
		$sql = <<<SQL
			UPDATE `{$this->_dbNameStage}`.Taxon T
			     , `{$dbName}`.distribution D
			   SET T.distribution = D.distribution
			 WHERE T.code = D.name_code
			   AND T.edition = $edition
SQL;
		$this->_exec($sql);
	}


	private function _importGenera($dbName,$sqlExpression)
	{
		$this->_info('Importing genera');
		$edition = $this->_getEdition($dbName);
		$whereClause = $this->_taxonNameFilter === null? "" : "AND AC.name LIKE '{$this->_taxonNameFilter}'";
		$sql = <<<SQL
			INSERT INTO `{$this->_dbNameStage}`.Taxon (
							edition,
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT
							{$edition},
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
		$this->_info('Importing families');
		$edition = $this->_getEdition($dbName);
		$whereClause = $this->_taxonNameFilter === null? "" : "AND family LIKE '{$this->_taxonNameFilter}'";
		$sql = <<<SQL
			INSERT INTO `{$this->_dbNameStage}`.Taxon (
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
						    {$edition},
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
		$this->_info('Importing super families');
		$edition = $this->_getEdition($dbName);
		$whereClause = $this->_taxonNameFilter === null? "" : "AND superfamily LIKE '{$this->_taxonNameFilter}'";
		$sql = <<<SQL
			INSERT INTO `{$this->_dbNameStage}`.Taxon (
							edition,
							edRecordId,
							databaseId,
							code, lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT
						    {$edition},
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
					 {$this->_readLimitClause}
SQL;
					 $this->_exec($sql);
	}


	private function _importOrders($dbName, $sqlExpression)
	{
		$this->_info('Importing orders');
		$edition = $this->_getEdition($dbName);
		$whereClause = $this->_taxonNameFilter === null? "" : "AND `order` LIKE '{$this->_taxonNameFilter}'";
		$sql = <<<SQL
			INSERT INTO `{$this->_dbNameStage}`.Taxon (
							edition,
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT  {$edition},
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
					 {$this->_readLimitClause}
SQL;
					 $this->_exec($sql);
	}


	private function _importClasses($dbName, $sqlExpression)
	{
		$this->_info('Importing classes');
		$edition = $this->_getEdition($dbName);
		$whereClause = $this->_taxonNameFilter === null? "" : "AND `class` LIKE '{$this->_taxonNameFilter}'";
		$sql = <<<SQL
			INSERT INTO `{$this->_dbNameStage}`.Taxon (
							edition,
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT  {$edition},
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
					 {$this->_readLimitClause}
SQL;
					 $this->_exec($sql);
	}


	private function _importPhyla($dbName, $sqlExpression)
	{
		$this->_info('Importing phyla');
		$edition = $this->_getEdition($dbName);
		$whereClause = $this->_taxonNameFilter === null? "" : "AND `class` LIKE '{$this->_taxonNameFilter}'";
		$sql = <<<SQL
			INSERT INTO `{$this->_dbNameStage}`.Taxon (
							edition,
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT  {$edition},
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
					 {$this->_readLimitClause}
SQL;
					 $this->_exec($sql);
	}


	private function _importKingdoms($dbName, $sqlExpression)
	{
		$this->_info('Importing kingdoms');
		$edition = $this->_getEdition($dbName);
		$whereClause = $this->_taxonNameFilter === null? "" : "AND `class` LIKE '{$this->_taxonNameFilter}'";
		$sql = <<<SQL
			INSERT INTO `{$this->_dbNameStage}`.Taxon (
							edition,
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT  {$edition},
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
					 {$this->_readLimitClause}
SQL;
					 $this->_exec($sql);
	}


	private function _generateLogicalKey()
	{
		$this->_info('Generating logical keys for taxa');
		$sql = <<<SQL
				UPDATE `{$this->_dbNameStage}`.Taxon
				   SET allData = UNHEX(MD5(IFNULL(REPLACE(
				   							CONCAT_WS(';', TRIM(sciNames), TRIM(commonNames), TRIM(distribution), TRIM(otherData)),
											'  ',
											' '),
										'')))
SQL;
		$this->_exec($sql);
	}


	private function _compareEditions()
	{

		$this->_info('Computing LSIDs in staging area');

		$this->_exec("ALTER TABLE `{$this->_dbNameStage}`.Taxon ENABLE KEYS");
		$this->_exec("OPTIMIZE TABLE `{$this->_dbNameStage}`.Taxon");
		$this->_exec("ALTER TABLE `{$this->_dbNameStage}`.Taxon ADD INDEX (allData,edition)");


		/*
		 $this->_exec("
		 		UPDATE `{$this->_dbNameStage}`.Taxon T1, `{$this->_dbNameStage}`.Taxon T2
		 		SET T2.lsid = T1.lsid
		 		WHERE T1.allData = T2.allData
		 		AND T1.edition = 0
		 		AND T2.edition = 1
		 		", true);
		*/


		$this->_exec("
				UPDATE `{$this->_dbNameStage}`.Taxon T1
				LEFT JOIN `{$this->_dbNameStage}`.Taxon T0 ON(T1.allData = T0.allData AND T1.edition = 1 AND T0.edition = 0)
				SET T1.lsid = T0.lsid
				WHERE T1.edition = 1
				AND T0.allData IS NOT NULL
				", true);



		$this->_exec("
				UPDATE `{$this->_dbNameStage}`.Taxon
				SET lsid = UUID()
				WHERE edition = 1
				AND lsid = ''
				");
	}


	private function _resetLSIDs()
	{
		$this->_info('Resetting LSIDs in new CoL edition');
		$this->_exec("UPDATE `{$this->_dbNameNext}`.taxa SET lsid = NULL");
	}


	private function _addLSIDs()
	{

		$this->_info('Copying LSIDs from staging area to new CoL edition');

		$prefix = self::$PREFIX_LSID;

		$sql = <<<SQL
			UPDATE `{$this->_dbNameNext}`.taxa A, `{$this->_dbNameStage}`.Taxon T
			   SET A.lsid = T.lsid
			 WHERE T.edition = 1 AND A.is_accepted_name = 1
			   AND T.code = A.name_code
SQL;
		$this->_exec($sql);

		$sql = <<<SQL
			UPDATE `{$this->_dbNameNext}`.taxa A, `{$this->_dbNameStage}`.Taxon T
			   SET A.lsid = T.lsid
			 WHERE T.edition = 1 AND A.is_accepted_name = 1
			   AND T.edRecordId = A.record_id
SQL;
		$this->_exec($sql);

		$prefix = self::$PREFIX_LSID;
		$sql = <<<SQL
			UPDATE `{$this->_dbNameNext}`.taxa
			   SET lsid = CONCAT('$prefix', lsid, ':{$this->_lsidSuffix}')
			 WHERE is_accepted_name = 1
SQL;
		$this->_exec($sql);

	}


	/**
	 * @param string $dbName Must be either $this->_dbNameCurrent or $this->_dbNameImport2
	 * @return boolean
	 */
	private function _hasLSIDs($dbName)
	{
		return (0 != $this->_fetchOne("SELECT COUNT(*) FROM `{$dbName}`.Taxa"));
	}


	/**
	 * Get the name of the edition based on the name of the database.
	 * @param string $dbName
	 * @return string
	 */
	private function _getEdition($dbName)
	{
		return $dbName === $this->_dbNameCurrent ? 0 : 1;
	}


	private function _countTaxaWithLsids()
	{
		return $this->_fetchOne('SELECT COUNT(*) FROM Taxon WHERE LENGTH(lsid) = 36');
	}


	/**
	 * Fetches the 1st column from the 1st row in the result set.
	 * @param string $sql
	 */
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
		$this->_debug('Disabling keys on table ' . $table);
		$this->_exec("ALTER TABLE `{$this->_dbNameStage}`.$table DISABLE KEYS");
	}


	/**
	 * @param string $table
	 * @throws TaxonMatcherException
	 */
	private function _enableKeys($table)
	{
		$this->_debug('Enabling keys on table ' . $table);
		$this->_exec("ALTER TABLE `{$this->_dbNameStage}`.$table ENABLE KEYS");
	}


	/**
	 * @throws TaxonMatcherException
	 */
	private function _initializeStagingArea()
	{
		$this->_info('Initializing staging area');
		$this->_exec("CREATE DATABASE IF NOT EXISTS `{$this->_dbNameStage}`");
		$this->_exec("DROP TABLE IF EXISTS `{$this->_dbNameStage}`.Taxon");
		$this->_createTaxonTable();
		$this->_exec("DROP TABLE IF EXISTS `{$this->_dbNameStage}`.CommonName");
		$this->_createCommonNameTable();
	}


	/**
	 * @throws TaxonMatcherException
	 */
	private function _createTaxonTable()
	{
		$sql = <<<SQL
			CREATE TABLE IF NOT EXISTS `{$this->_dbNameStage}`.Taxon(
				edition      TINYINT NOT NULL DEFAULT 0,
				edRecordId   INT(10) UNSIGNED NOT NULL,
		 		databaseId   INT(10) UNSIGNED NOT NULL,
		 		code         VARCHAR(137) NOT NULL DEFAULT '',
		 		lsid         VARCHAR(36) NOT NULL DEFAULT '',
		 		rank         VARCHAR(12) NOT NULL DEFAULT '',
				nameCodes    VARCHAR(1024) NOT NULL DEFAULT '',
		 		sciNames     TEXT NOT NULL DEFAULT '',
		 		commonNames  TEXT NOT NULL DEFAULT '',
		 		distribution VARCHAR(2048) NOT NULL DEFAULT '',
		 		otherData    VARCHAR(512) NOT NULL DEFAULT '',
		 		allData      BINARY(16) NOT NULL DEFAULT '',
		 		INDEX        (edition),
		 		INDEX        (edRecordId,edition),
		 		INDEX        (code,edition),
		 		INDEX        (lsid)
			) ENGINE=MYISAM
SQL;
		$this->_exec($sql);
	}


	private function _createCommonNameTable()
	{
		$sql = <<<SQL
		CREATE TABLE `{$this->_dbNameStage}`.CommonName(
				edition     VARCHAR(15) NOT NULL DEFAULT '',
				code        VARCHAR(137) NOT NULL DEFAULT '',
				commonNames TEXT,
				INDEX (code)
		) ENGINE=MYISAM
SQL;
		$this->_exec($sql);
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
		$this->_debug('Executing SQL (query): ' . $sql);
		$statement = $this->_pdo->query($sql);
		if($statement === false) {
			$error = $this->_pdo->errorInfo();
			throw new TaxonMatcherException($error[2]);
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
	private function _exec($sql, $buffered = false)
	{
		$this->_debug('Executing SQL (exec): ' . $sql);
		$options = array(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => $buffered);
		$statement = $this->_pdo->prepare($sql, $options);
		if($statement->execute() === false) {
			$error = $this->_pdo->errorInfo();
			throw new TaxonMatcherException($error[2]);
		}
		$this->_debug('Records inserted/updated/deleted: ' . $statement->rowCount());
		return $statement->rowCount();
	}


	/**
	 * Get a connection to the database containing the three databases that we are
	 * working with (Cumulative Taxon List, current CoL, next CoL).
	 *
	 * @throws PDOException
	 */
	private function _connect()
	{
		$this->_info('Connecting to database server');
		$dsn = 'mysql:host=' . $this->_dbHost;
		try {
			$this->_pdo = new PDO($dsn, $this->_dbUser, $this->_dbPassword);
		}
		catch(PDOException $e) {
			throw new TaxonMatcherException('Cannot connect to database server using the specified connection parameters', 0, $e);
		}
		$this->_exec("SET NAMES UTF8");
		$this->_exec("SET GROUP_CONCAT_MAX_LEN = 15000");
	}


	private function _error($message, Exception $exception = null)
	{
		foreach($this->_listeners as $listener) {
			$listener->onMessage(TaxonMatcherEventListener::MSG_ERROR, $message, $exception);
		}
	}


	private function _warning($message)
	{
		foreach($this->_listeners as $listener) {
			$listener->onMessage(TaxonMatcherEventListener::MSG_WARNING, $message);
		}
	}


	private function _info($message)
	{
		foreach($this->_listeners as $listener) {
			$listener->onMessage(TaxonMatcherEventListener::MSG_INFO, $message);
		}
	}


	private function _output($message)
	{
		foreach($this->_listeners as $listener) {
			$listener->onMessage(TaxonMatcherEventListener::MSG_OUTPUT, $message);
		}
	}


	private function _debug($message)
	{
		foreach($this->_listeners as $listener) {
			$listener->onMessage(TaxonMatcherEventListener::MSG_DEBUG, $message);
		}
	}

	private function _getTimer($seconds)
	{
		// extract hours
		$hours = floor($seconds / (60 * 60));

		// extract minutes
		$divisor_for_minutes = $seconds % (60 * 60);
		$minutes = floor($divisor_for_minutes / 60);

		// extract the remaining seconds
		$divisor_for_seconds = $divisor_for_minutes % 60;
		$seconds = ceil($divisor_for_seconds);

		return array(
				'H' => (int) $hours,
				'i' => (int) $minutes,
				's' => (int) $seconds,
		);
	}
}