<?php
require_once 'Storer/Interface.php';

class Bs_Storer
{
    protected $_dbh;
    protected $_logger;
    protected $_indicator;
    
    // Tables cannot be cleared one-by-one as with Spicecache database
    // Order of truncation is determined by order in $dbTables array below
    private static $dbTables = array(
        'distribution_free_text', 'region_free_text', 'taxon_detail', 
        'scrutiny', 'specialist', 'reference_to_synonym', 'synonym_name_element', 
        'synonym', 'author_string', 'taxon_name_element', 
        'scientific_name_element', 'uri_to_taxon', 'reference_to_taxon',
        'reference_to_common_name', 'common_name', 'common_name_element', 
        'reference', 'uri_to_source_database', 
        'uri_to_taxon', 'uri', 'taxon', 
        'source_database'
    );
    
    public function __construct(PDO $dbh, Zend_Log $logger, Indicator $indicator)
    {
        $this->_dbh = $dbh;
        $this->_logger = $logger;
        $this->_indicator = $indicator;
    }
    
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
    
    public function clear($what) {
        return $this->_getStorer($what)->clear();
    }
    
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
}