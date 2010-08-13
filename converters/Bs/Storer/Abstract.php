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
    
    protected function recordExists($id_field, $table, array $where)
    {
    	$query = 'SELECT '.$id_field.' FROM `'.$table.'` WHERE ';
    	foreach ($where as $field => $value) {
    		$query .= '`'.$field.'` = :'.$field.' AND ';
    	}
		$stmt = $this->_dbh->prepare(substr($query, 0, -5));
		if ($stmt->execute($where) && $stmt->rowCount() == 1) {
            return $stmt->fetchColumn(0);
		}
	    return false;
    }
}