<?php
/**
 *
 * @author Ayco Holleman, ETI BioInformatics
 * @author Richard White (original PERL implementation), Cardiff University
 *
 * <p>
 * The central class in the PHP taxon matcher library. Its most important method is
 * the run() method, which matches taxa between current and upcoming CoL editions,
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

	private $_resetLSIDs = false;
	private $_dropStagingArea = false;
	private $_taxonNameFilter;
	private $_readLimitClause = '';

	/**
	 * An array of TaxonMatcherEventListener objects.
	 * @var array
	 */
	private $_listeners = array();

	/**
	 * @var PDO
	 */
	private $_pdo;






	public function __construct()
	{
		// ...
	}


	/**
	 * Runs the taxon matching process.
	 */
	public function run()
	{
		try {
			$start = time();
			$this->_validateInput();
			$this->_connect();

			$this->_initializeStagingArea();

			// Erase LSIDs in new AC, when requested
			if($this->_resetLSIDs) {
				$this->_resetLSIDs();
			}

			// import data from old AC into staging area
			$this->_importAC($this->_dbNameCurrent);

			// import data from new AC into staging area
			$this->_importAC($this->_dbNameNext);

			$this->_compareEditions();
			$this->_addLSIDs();

			if($this->_dropStagingArea) {
				$this->_dropStagingArea();
			}

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
	 * Whether or not to first erase all LSIDs in the new AC. Optional. Default
	 * TRUE.
	 * @param boolean $bool
	 */
	public function setResetLSIDs($bool)
	{
		$this->_resetLSIDs = $bool;
	}


	/**
	 * Whether or not to drop the staging area database at the end of the process.
	 * Optional. Default TRUE.
	 * @param boolean $bool
	 */
	public function setDropStagingArea($bool)
	{
		$this->_dropStagingArea = $bool;
	}


	/**
	 * Set maxmimum number of records to fetch (debug option). Optional.
	 * Null or zero means no maximum. Note that setting readLimit to $i does
	 * not mean that LSIDs will be computed for $i taxa. It just means that
	 * a LIMIT clause will be appended to <i>every</i> SELECT statement.
	 *
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
		if(!$this->_dbHost) {
			throw new InvalidInputException('Not set: database host');
		}
		if(!$this->_dbUser) {
			throw new InvalidInputException('Not set: database user');
		}
		if(!$this->_dbPassword) {
			$this->_warning('Not set: database password');
		}
		if(!$this->_dbNameStage) {
			throw new InvalidInputException('Not set: database name of staging area');
		}
		if(!$this->_dbNameCurrent) {
			throw new InvalidInputException('Not set: database name of current edition of CoL');
		}
		if(!$this->_dbNameNext) {
			throw new InvalidInputException('Not set: database name of upcoming edition of CoL');
		}
		if(!$this->_lsidSuffix) {
			throw new InvalidInputException('Not set: LSID suffix');
		}
	}


	private function _importAC($dbName)
	{

		$this->_info("Importing data from database $dbName");

		$this->_importSpecies($dbName);
		$this->_importLSIDsForSpecies($dbName);
		$this->_importCommonNames($dbName);
		$this->_copyCommonNamesToTaxonTable($dbName);
		$this->_importDistributionData($dbName);
		$this->_importGenera($dbName);
		$this->_importFamilies($dbName);
		$this->_importSuperFamilies($dbName);
		$this->_importOrders($dbName);
		$this->_importClasses($dbName);
		$this->_importPhyla($dbName);
		$this->_importKingdoms($dbName);

		$this->_generateLogicalKeys($dbName);

		$this->_prepareForMatching($dbName);

	}


	private function _importSpecies($dbName)
	{
		$this->_info('Importing species and lower taxa');


		$edition = $this->_getEdition($dbName);
		$table = $this->_getStagingTable($dbName);

		$this->_exec("ALTER TABLE {$table} DISABLE KEYS");

		$whereClause = $this->_taxonNameFilter === null ? "" : "WHERE genus LIKE '{$this->_taxonNameFilter}'";

		if($edition === 0 || $this->_resetLSIDs) {
			$sql = <<<SQL
			INSERT INTO {$table} (
							edRecordId,
							databaseId,
							code,
							rank,
							nameCodes,
							sciNames,
							otherData)
					SELECT
							0,
							S.database_id,
							S.accepted_name_code,
							'(sp/infra)',
			                GROUP_CONCAT(S.name_code ORDER BY S.sp2000_status_id SEPARATOR '; '),
			                GROUP_CONCAT(CONCAT_WS(' ', genus, species, NULLIF(infraspecies_marker, ''), infraspecies, author)
			                                       ORDER BY S.sp2000_status_id, genus, species, infraspecies, author
			                                       SEPARATOR ', '),
			                S.accepted_name_code
					 FROM `{$dbName}`.scientific_names S
					 $whereClause
					GROUP BY S.accepted_name_code
					{$this->_readLimitClause}
SQL;
		}
		else {
			$sql = <<<SQL
			INSERT INTO {$table} (
							edRecordId,
							databaseId,
							code,
							rank,
							nameCodes,
							sciNames,
							otherData)
					SELECT
							0,
							S.database_id,
							S.accepted_name_code,
							'(sp/infra)',
			                GROUP_CONCAT(S.name_code ORDER BY S.sp2000_status_id SEPARATOR '; '),
			                GROUP_CONCAT(CONCAT_WS(' ', genus, species, NULLIF(infraspecies_marker, ''), infraspecies, author)
			                                       ORDER BY S.sp2000_status_id, genus, species, infraspecies, author
			                                       SEPARATOR ', '),
			                S.accepted_name_code
					 FROM `{$dbName}`.scientific_names S
					 LEFT JOIN `{$dbName}`.taxa AC ON (T.code = AC.name_code)
					WHERE AC.name_code IS NOT NULL
					  AND AC.lsid IS NULL
					  $whereClause
					GROUP BY S.accepted_name_code
					{$this->_readLimitClause}

SQL;
		}
		$this->_exec($sql);

		$this->_exec("ALTER TABLE {$table} ENABLE KEYS");

	}


	private function _importLSIDsForSpecies($dbName)
	{
		$this->_info('Importing LSIDs for species and lower taxa');
		$edition = $this->_getEdition($dbName);
		$table = $this->_getStagingTable($dbName);
		$sqlExpression = sprintf('SUBSTRING(AC.lsid,%s,%s)', self::$UUID_START_POS, self::$UUID_LENGTH);
		$whereClause = $edition === 0? "" : " AND AC.lsid IS NOT NULL";
		$sql = <<<SQL
			UPDATE {$table} T
			  LEFT JOIN `{$dbName}`.taxa AC ON (T.code = AC.name_code)
			   SET T.lsid = {$sqlExpression}
			 WHERE AC.name_code IS NOT NULL
			 $whereClause
SQL;
		$this->_exec($sql);
	}


	private function _importCommonNames($dbName)
	{
		$this->_info('Importing common names');
		$this->_exec("TRUNCATE TABLE `{$this->_dbNameStage}`.CommonName");
		$table = $this->_getStagingTable($dbName);
		$sql = <<<SQL
			INSERT INTO `{$this->_dbNameStage}`.CommonName(
						code,
						commonNames)
			SELECT
						T.code,
		                GROUP_CONCAT(CONCAT_WS('/', common_name, `language`, country) ORDER BY common_name, `language`, country SEPARATOR ', ') AS commonNames
              FROM {$table} T, `{$dbName}`.common_names C
             WHERE T.code = C.name_code
             GROUP BY C.name_code
             {$this->_readLimitClause}
SQL;
             $this->_exec($sql);
	}


	private function _copyCommonNamesToTaxonTable($dbName)
	{
		$this->_info('Copying common names to Taxon table');
		$table = $this->_getStagingTable($dbName);
		$sql = <<<SQL
			UPDATE {$table} T
			     , `{$this->_dbNameStage}`.CommonName C
			   SET T.commonNames = C.commonNames
			 WHERE T.code = C.code
SQL;
		$this->_exec($sql);
	}


	private function _importDistributionData($dbName)
	{
		$this->_info('Importing distribution data');
		$table = $this->_getStagingTable($dbName);
		$sql = <<<SQL
			UPDATE {$table} T
			     , `{$dbName}`.distribution D
			   SET T.distribution = D.distribution
			 WHERE T.code = D.name_code
SQL;
		$this->_exec($sql);
	}


	private function _importGenera($dbName)
	{
		$this->_info('Importing genera');

		$edition = $this->_getEdition($dbName);
		$table = $this->_getStagingTable($dbName);

		$whereClause = $this->_taxonNameFilter === null? "" : "AND AC.name LIKE '{$this->_taxonNameFilter}'";

		if($edition === 0) {
			// this is the old CoL edition, so all taxa will have an LSID
			$sqlExpression = sprintf('SUBSTRING(AC.lsid,%s,%s)', self::$UUID_START_POS, self::$UUID_LENGTH);
		}
		else {
			// (subtle but correct, will document later)
			$sqlExpression = "''";
			if(!$this->_resetLSIDs) {
				$whereClause .= " AND AC.lsid IS NULL";
			}
		}

		$sql = <<<SQL
			INSERT INTO {$table} (
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT
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


	private function _importFamilies($dbName)
	{
		$this->_info('Importing families');

		$edition = $this->_getEdition($dbName);
		$table = $this->_getStagingTable($dbName);

		$whereClause = $this->_taxonNameFilter === null? "" : "AND family LIKE '{$this->_taxonNameFilter}'";
		if($edition === 0) {
			$sqlExpression = sprintf('SUBSTRING(AC.lsid,%s,%s)', self::$UUID_START_POS, self::$UUID_LENGTH);
		}
		else {
			$sqlExpression = "''";
			if(!$this->_resetLSIDs) {
				$whereClause .= " AND AC.lsid IS NULL";
			}
		}

		$sql = <<<SQL
			INSERT INTO {$table} (
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames,
							otherData)
					SELECT
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


	private function _importSuperFamilies($dbName)
	{
		$this->_info('Importing super families');

		$edition = $this->_getEdition($dbName);
		$table = $this->_getStagingTable($dbName);

		$whereClause = $this->_taxonNameFilter === null? "" : "AND superfamily LIKE '{$this->_taxonNameFilter}'";
		if($edition === 0) {
			$sqlExpression = sprintf('SUBSTRING(AC.lsid,%s,%s)', self::$UUID_START_POS, self::$UUID_LENGTH);
		}
		else {
			$sqlExpression = "''";
			if(!$this->_resetLSIDs) {
				$whereClause .= " AND AC.lsid IS NULL";
			}
		}

		$sql = <<<SQL
			INSERT INTO {$table} (
							edRecordId,
							databaseId,
							code, lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT
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


	private function _importOrders($dbName)
	{
		$this->_info('Importing orders');

		$edition = $this->_getEdition($dbName);
		$table = $this->_getStagingTable($dbName);

		$whereClause = $this->_taxonNameFilter === null? "" : "AND `order` LIKE '{$this->_taxonNameFilter}'";
		if($edition === 0) {
			$sqlExpression = sprintf('SUBSTRING(AC.lsid,%s,%s)', self::$UUID_START_POS, self::$UUID_LENGTH);
		}
		else {
			$sqlExpression = "''";
			if(!$this->_resetLSIDs) {
				$whereClause .= " AND AC.lsid IS NULL";
			}
		}

		$sql = <<<SQL
			INSERT INTO {$table} (
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT
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


	private function _importClasses($dbName)
	{
		$this->_info('Importing classes');

		$sqlExpression = sprintf('SUBSTRING(AC.lsid,%s,%s)', self::$UUID_START_POS, self::$UUID_LENGTH);

		$edition = $this->_getEdition($dbName);
		$table = $this->_getStagingTable($dbName);

		$whereClause = $this->_taxonNameFilter === null? "" : "AND `class` LIKE '{$this->_taxonNameFilter}'";
		if($edition === 0) {
			$sqlExpression = sprintf('SUBSTRING(AC.lsid,%s,%s)', self::$UUID_START_POS, self::$UUID_LENGTH);
		}
		else {
			$sqlExpression = "''";
			if(!$this->_resetLSIDs) {
				$whereClause .= " AND AC.lsid IS NULL";
			}
		}


		$sql = <<<SQL
			INSERT INTO {$table} (
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT
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


	private function _importPhyla($dbName)
	{
		$this->_info('Importing phyla');

		$edition = $this->_getEdition($dbName);
		$table = $this->_getStagingTable($dbName);

		$whereClause = $this->_taxonNameFilter === null? "" : "AND `class` LIKE '{$this->_taxonNameFilter}'";
		if($edition === 0) {
			$sqlExpression = sprintf('SUBSTRING(AC.lsid,%s,%s)', self::$UUID_START_POS, self::$UUID_LENGTH);
		}
		else {
			$sqlExpression = "''";
			if(!$this->_resetLSIDs) {
				$whereClause .= " AND AC.lsid IS NULL";
			}
		}

		$sql = <<<SQL
			INSERT INTO {$table} (
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT
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


	private function _importKingdoms($dbName)
	{
		$this->_info('Importing kingdoms');

		$edition = $this->_getEdition($dbName);
		$table = $this->_getStagingTable($dbName);

		$whereClause = $this->_taxonNameFilter === null? "" : "AND `class` LIKE '{$this->_taxonNameFilter}'";
		if($edition === 0) {
			$sqlExpression = sprintf('SUBSTRING(AC.lsid,%s,%s)', self::$UUID_START_POS, self::$UUID_LENGTH);
		}
		else {
			$sqlExpression = "''";
			if(!$this->_resetLSIDs) {
				$whereClause .= " AND AC.lsid IS NULL";
			}
		}

		$sql = <<<SQL
			INSERT INTO {$table} (
							edRecordId,
							databaseId,
							code,
							lsid,
							rank,
							nameCodes,
							sciNames)
					SELECT
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


	private function _generateLogicalKeys($dbName)
	{
		$this->_info('Generating logical keys for taxa');
		$table = $this->_getStagingTable($dbName);
		$sql = <<<SQL
				UPDATE {$table}
				   SET allData = UNHEX(MD5(IFNULL(REPLACE(
				   							CONCAT_WS(';', TRIM(sciNames), TRIM(commonNames), TRIM(distribution), TRIM(otherData)),
											'  ',
											' '),
										'')))
SQL;
		$this->_exec($sql);
	}


	private function _prepareForMatching($dbName)
	{
		$this->_info('Prepare for matching');
		$table = $this->_getStagingTable($dbName);
		$this->_exec("OPTIMIZE TABLE {$table}");
		$this->_exec("ALTER TABLE {$table} ADD INDEX (allData)");
	}


	private function _compareEditions()
	{

		$this->_info('Copying LSIDs from matching taxa');

		$this->_exec("
				UPDATE `{$this->_dbNameStage}`.TaxonNext T1
				LEFT JOIN `{$this->_dbNameStage}`.TaxonCurrent T0 ON(T1.allData = T0.allData)
				SET T1.lsid = T0.lsid
				WHERE T0.allData IS NOT NULL
				", true);

	}


	private function _resetLSIDs()
	{
		$this->_info('Resetting LSIDs in new CoL edition');
		//$this->_exec("ALTER TABLE `{$this->_dbNameNext}`.taxa DROP INDEX lsid");
		$this->_exec("UPDATE `{$this->_dbNameNext}`.taxa SET lsid = NULL");
		//$this->_exec("ALTER TABLE `{$this->_dbNameNext}`.taxa ADD INDEX (lsid)");
	}


	private function _addLSIDs()
	{


		$prefix = self::$PREFIX_LSID;

		// If the LSIDs have been reset to NULL, then LSID assignment
		// must always take place, otherwise, only those records which
		// do not have an LSID yet must get an LSID.
		$whereClause = $this->_resetLSIDs? '' : 'AND A.lsid IS NULL';


		$this->_info('Copying LSIDs from staging area to new CoL edition');

		$this->_exec("DELETE FROM `{$this->_dbNameStage}`.TaxonNext WHERE lsid = ''");

		$sql = <<<SQL
				UPDATE `{$this->_dbNameNext}`.taxa A
				LEFT JOIN `{$this->_dbNameStage}`.TaxonNext T ON (A.name_code = T.code)
				SET A.lsid = CONCAT('$prefix' , T.lsid , ':{$this->_lsidSuffix}' )
				WHERE T.code IS NOT NULL
				AND A.is_accepted_name = 1
				$whereClause
SQL;
		$this->_exec($sql);


		$sql = <<<SQL
				UPDATE `{$this->_dbNameNext}`.taxa A
				LEFT JOIN `{$this->_dbNameStage}`.TaxonNext T ON (A.record_id = T.edRecordId)
				SET A.lsid = CONCAT('$prefix' , T.lsid , ':{$this->_lsidSuffix}' )
				WHERE T.edRecordId IS NOT NULL
				AND A.is_accepted_name = 1
				$whereClause
SQL;
		$this->_exec($sql);


		$this->_info('Assigning new LSIDs to new (unmatched) taxa');
		$sql = <<<SQL
				UPDATE `{$this->_dbNameNext}`.taxa
				SET lsid = CONCAT('$prefix' , UUID() , ':{$this->_lsidSuffix}' )
				WHERE lsid IS NULL
SQL;
		$this->_exec($sql);

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


	/**
	 * Get the name of the staging area table based on the name of the database
	 * @param string $dbName
	 * @return string
	 */
	private function _getStagingTable($dbName)
	{
		if($dbName === $this->_dbNameCurrent) {
			return "`{$this->_dbNameStage}`.TaxonCurrent";
		}
		return "`{$this->_dbNameStage}`.TaxonNext";
	}


	/**
	 * @throws TaxonMatcherException
	 */
	private function _initializeStagingArea()
	{
		$this->_info('Initializing staging area');
		$this->_exec("CREATE DATABASE IF NOT EXISTS `{$this->_dbNameStage}`");
		$this->_createTaxonCurrentTable();
		$this->_createTaxonNextTable();
		$this->_createCommonNameTable();
	}

	private function _dropStagingArea()
	{
		$this->_info('Destroying staging area');
		$this->_exec("DROP DATABASE IF EXISTS `{$this->_dbNameStage}`");
	}


	/**
	 * @throws TaxonMatcherException
	 */
	private function _createTaxonCurrentTable()
	{
		$tableName = " `{$this->_dbNameStage}`.TaxonCurrent";
		$this->_exec("DROP TABLE IF EXISTS {$tableName}");
		$sql = <<<SQL
			CREATE TABLE IF NOT EXISTS {$tableName} (
				edRecordId   INT(10) UNSIGNED NOT NULL,
		 		databaseId   INT(10) UNSIGNED NOT NULL,
		 		code         VARCHAR(137) NOT NULL DEFAULT '',
		 		lsid         VARCHAR(36) NOT NULL DEFAULT '',
		 		rank         VARCHAR(12) NOT NULL DEFAULT '',
				nameCodes    VARCHAR(4000) NOT NULL DEFAULT '',
		 		sciNames     TEXT,
		 		commonNames  TEXT,
		 		distribution TEXT,
		 		otherData    VARCHAR(512) NOT NULL DEFAULT '',
		 		allData      BINARY(16) NOT NULL DEFAULT '',
		 		INDEX        (edRecordId),
		 		INDEX        (code)
		 	) ENGINE=MYISAM
SQL;
		$this->_exec($sql);
	}


	/**
	 * @throws TaxonMatcherException
	 */
	private function _createTaxonNextTable()
	{
		$tableName = " `{$this->_dbNameStage}`.TaxonNext";
		$this->_exec("DROP TABLE IF EXISTS {$tableName}");
		$sql = <<<SQL
			CREATE TABLE IF NOT EXISTS {$tableName} (
				edRecordId   INT(10) UNSIGNED NOT NULL,
		 		databaseId   INT(10) UNSIGNED NOT NULL,
		 		code         VARCHAR(137) NOT NULL DEFAULT '',
		 		lsid         VARCHAR(36) NOT NULL DEFAULT '',
		 		rank         VARCHAR(12) NOT NULL DEFAULT '',
				nameCodes    VARCHAR(4000) NOT NULL DEFAULT '',
		 		sciNames     TEXT,
		 		commonNames  TEXT,
		 		distribution TEXT,
		 		otherData    VARCHAR(512) NOT NULL DEFAULT '',
		 		allData      BINARY(16) NOT NULL DEFAULT '',
		 		INDEX        (edRecordId),
		 		INDEX        (code)
		 	) ENGINE=MYISAM
SQL;
		$this->_exec($sql);
	}


	private function _createCommonNameTable()
	{
		$sql = <<<SQL
		CREATE TABLE IF NOT EXISTS `{$this->_dbNameStage}`.CommonName(
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
			$error = $statement->errorInfo();
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
			$error = $statement->errorInfo();
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