<?php
require_once 'Storer/Interface.php';

/**
 * Storer
 * 
 * Dynamically loads the appropriate class. In the script that runs the 
 * conversion, only Class has to be given rather than Ac_Storer_Class
 * 
 * @author Nœria Torrescasana Aloy
 */
class Bs_Storer
{
    protected $_dbh;
    protected $_logger;
    protected $_indicator;
    
    /**
     * Tables cannot be cleared one-by-one as with Spicecache database. 
     * Order of truncation in clearDb() method is determined by order 
     * in $dbTables array below.
     */
    private static $dbTables = array(
        'distribution_free_text', 'region_free_text', 'taxon_detail', 
        'scrutiny', 'specialist', 'reference_to_synonym', 
        'synonym_name_element', 'synonym', 'author_string', 
        'taxon_name_element', 'scientific_name_element', 'uri_to_taxon', 
        'reference_to_taxon', 'reference_to_common_name', 'common_name', 
        'common_name_element', 'reference', 'uri_to_source_database', 
        'uri_to_taxon', 'uri', 'taxon', 'source_database'
    );
    
    public function __construct(PDO $dbh, Zend_Log $logger, Indicator $indicator)
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
     * @return class $loader loader class
     */
    private function _getStorer($name, $isClass = false)
    {
        if($isClass) {
            $class = $name;
            $parts = explode('_', $name);
            $name = current(array_reverse($parts));
        }
        $class = 'Bs_Storer_' . $name;
        
        if(!include_once('Storer/' . $name . '.php')) {
            throw new Exception('Storer class file not found');
        }
        if(!class_exists($class)) {
            throw new Exception('Storer class undefined');
        }
        $storer = new $class($this->_dbh, $this->_logger);
        if(!$storer instanceof Bs_Storer_Interface) {
            unset($storer);
            throw new Exception('Invalid storer instance');
        }
        return $storer;
    }
    
    /*
    public function clear($what) {
        return $this->_getStorer($what)->clear();
    }
    */
    
    public function store(Model $object)
    {
    	$storer = $this->_getStorer(get_class($object), true);   	
        $res = $storer->store($object);
        $this->_indicator->iterate();
        return $res;
    }
    
    public function storeAll(array $arr)
    {
    	if(empty($arr)) {
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
     * this function is used to clear the entire database. Emptying individual 
     * tables is practically impossible because of the strict foreign key 
     * contraints in the Base Scheme. Also resets AUTO_INCREMENT values and
     * clear custom entries from the the taxonomic_rank table.
     */
    public function clearDb()
    {
        foreach (self::$dbTables as $table) {
            $stmt = $this->_dbh->prepare('TRUNCATE `'.$table.'`');
            $stmt->execute();
            $stmt = $this->_dbh->prepare(
                'ALTER TABLE `'.$table.'` AUTO_INCREMENT = 1'
            );
            $stmt->execute();
        }
        $stmt = $this->_dbh->prepare(
            'DELETE FROM `taxonomic_rank` WHERE `standard` = ?'
        );
        $stmt->execute(array(0));
        unset($stmt);
    }
}