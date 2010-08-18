<?php
abstract class Bs_Storer_Abstract
{
    protected $_dbh;
    protected $_logger;
    
    public function __construct(PDO $dbh, Zend_Log $logger)
    {
        $this->_dbh = $dbh;
        $this->_logger = $logger;
    }
    
    protected function _recordExists($id_field, $table, array $where)
    {
    	$query = 'SELECT '.$id_field.' FROM `'.$table.'` WHERE ';
    	foreach ($where as $field => $value) {
    		$query .= '`'.$field.'` = :'.$field.' AND ';
    	}
		$stmt = $this->_dbh->prepare(substr($query, 0, -5));
		$result = $stmt->execute($where);
		if ($result && $stmt->rowCount() == 1) {
            return $stmt->fetchColumn(0);
		}
	    return false;
    }
    
    protected function _clearTables(array $tables)
    {
        foreach ($tables as $table) {
            $stmt = $this->_dbh->prepare('DELETE FROM `'.$table.'`');
            $stmt->execute();
            $stmt = $this->_dbh->prepare(
                'ALTER TABLE `'.$table.'` AUTO_INCREMENT = 1'
            );
            $stmt->execute();
        }
        unset($stmt);
    }
    
    public function printObject($object)
    {
        echo '<pre>';
        print_r($object);
        echo '</pre>';
    }
}