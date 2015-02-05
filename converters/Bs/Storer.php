<?php
require_once 'Storer/Interface.php';

/**
 * Storer
 *
 * Dynamically loads the appropriate class. In the script that runs the
 * conversion, only Class has to be given rather than Ac_Storer_Class
 *
 * @author Nuria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Storer
{
    protected $_dbh;
    protected $_logger;
    protected $_storers = array();
    protected $_indicator;

    private static $dbTables = array(
        'author_string',
        'common_name',
        'common_name_element',
        'distribution',
        'distribution_free_text',
        'lifezone_to_taxon_detail',
        'reference',
        'reference_to_common_name',
        'reference_to_synonym',
        'reference_to_taxon',
        'region_free_text',
        'scientific_name_element',
        'scrutiny',
        'source_database',
        'specialist',
        'synonym',
        'synonym_name_element',
        'taxon',
        'taxon_detail',
        'taxon_name_element',
        'uri',
        'uri_to_source_database',
        'uri_to_taxon'
    );

    private static $dbDenormalizedTables = array(
        '_conversion_errors',
        '_new_search_name_elements',
        '_image_resource',
        '_search_all',
        '_search_all_new',
        '_search_distribution',
        '_search_family',
        '_search_scientific',
        '_source_database_details',
        '_source_database_taxonomic_coverage',
        '_species_details',
        '_taxon_tree',
        '_totals',
        '_natural_keys',
        '_source_database_to_taxon_tree_branch'
    );

    public function __construct (PDO $dbh, Zend_Log $logger, Indicator $indicator)
    {
        $this->_dbh = $dbh;
        $this->_logger = $logger;
        $this->_indicator = $indicator;
    }

    /**
     * Dynamically loads the appropriate storer class
     *
     * Takes a simplified notation of the storer class that should be used
     * and dispatches the store() method to that class
     *
     * @param string $name class name
     * @throws exception
     * @return class loader class
     */
    private function _getStorer ($name, $isClass = false)
    {
        if ($isClass) {
            $class = $name;
            $parts = explode('_', $name);
            $name = current(array_reverse($parts));
        }
        $class = 'Bs_Storer_' . $name;

        if (!include_once ('Storer/' . $name . '.php')) {
            $e = new Exception('Storer class file not found');
            $this->_logger->err($e);
            throw $e;
        }
        if (!class_exists($class)) {
            $e = new Exception('Storer class undefined');
            $this->_logger->err($e);
            throw $e;
        }
        if (isset($this->_storers[$class])) {
            $storer = $this->_storers[$class];
        }
        else {
            $storer = new $class($this->_dbh, $this->_logger);
            if (!$storer instanceof Bs_Storer_Interface) {
                $e = new Exception('Invalid storer instance');
                $this->_logger->err($e);
                unset($storer);
                throw $e;
            }
        }
        return $storer;
    }

    /*
    public function clear($what) {
        return $this->_getStorer($what)->clear();
    }
    */

    /**
     * Passes store function on to appropriate storer class
     *
     * @param class $object class defined in model or, when extended,
     * in converters/Ac/Model
     */
    public function store (Model $object)
    {
        $class = get_class($object);
        $storer = $this->_getStorer($class, true);
        $start = microtime(true);
        $res = $storer->store($object);
        $this->_indicator->iterate();
        return $res;
    }

    /**
     * Passes storeAll function on to appropriate storer class
     *
     * Not used in this conversion; see ScToDc for implementation.
     */
    public function storeAll (array $arr)
    {
        if (empty($arr)) {
            return;
        }
        $storer = $this->_getStorer(get_class($arr[0]), true);
        $res = $storer->storeAll($arr);
        unset($arr);
        $this->_indicator->iterate();
        return $res;
    }

    /**
     * Clears all entries in Base Scheme database from a previous import
     *
     * Rather than clearing individual tables as in the ScToDc conversion,
     * this function is used to clear the entire database, using the column
     * order in the static $dbTables array. Emptying individual tables is
     * practically impossible because of the strict foreign key contraints
     * in the Base Scheme. Also resets AUTO_INCREMENT values and clears
     * custom entries from the the taxonomic_rank table.
     */
    public function clearDb ()
    {
        // Empty tables and reset auto-increment values
        $this->_dbh->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::$dbTables as $table) {
            $stmt = $this->_dbh->prepare('TRUNCATE `' . $table . '`');
            $stmt->execute();
            $stmt = $this->_dbh->prepare('ALTER TABLE `' . $table . '` AUTO_INCREMENT = 1');
            $stmt->execute();
        }
        // Re-enable checks only if set as such in config
        $config = parse_ini_file('config/AcToBs.ini', true);
        if (isset($config['checks']['fk_constraints']) && $config['checks']['fk_constraints'] == 1) {
            $this->_dbh->query('SET FOREIGN_KEY_CHECKS = 1');
        }
        // Delete denormalized tables
        foreach (self::$dbDenormalizedTables as $table) {
            $stmt = $this->_dbh->prepare('DROP TABLE IF EXISTS `' . $table . '`');
            $stmt->execute();
        }
        // Delete non-standard taxonomic ranks
        $stmt = $this->_dbh->prepare(
            'DELETE FROM `taxonomic_rank` WHERE `standard` = ?');
        $stmt->execute(array(
            0
        ));
        unset($stmt);
    }

    public function recreateDb ()
    {
        $config = parse_ini_file('config/AcToBs.ini', true);
        $files = array(
            'schema' => $config['schema']['path'] . 'baseschema-schema.sql',
            'data' => $config['schema']['path'] . 'baseschema-data.sql'
        );
        $this->_dbh->query('SET FOREIGN_KEY_CHECKS = 0');
        $stmt = $this->_dbh->query('SHOW TABLES');
        $tables = $stmt->fetchAll(PDO::FETCH_NUM);
        foreach ($tables as $table) {
            $this->_dbh->query('DROP TABLE IF EXISTS `' . $table[0] . '`');
        }
        foreach ($files as $file) {
            $sql = file_get_contents($file);
            $stmt = $this->_dbh->prepare($sql);
            try {
                $stmt->execute();
            } catch (PDOException $e) {
                handleException("Cannot write sql dump", $e);
            }
        }
        $stmt->closeCursor();
        $this->_dbh->query('SET FOREIGN_KEY_CHECKS = 1');
    }
}