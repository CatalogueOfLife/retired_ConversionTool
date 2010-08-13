<?php
require_once 'Interface.php';
require_once 'Abstract.php';

class Bs_Storer_Uri extends Bs_Storer_Abstract
    implements Bs_Storer_Interface
{
    public function clear()
    {
    	$tables = array('uri');
    	foreach ($tables as $table) {
	        $stmt = $this->_dbh->prepare('DELETE FROM `'.$table.'`');
	        $stmt->execute();
    	}
     	unset($stmt);
    }
    
    public function store(Model $uri)
    {
    	$uri->uriSchemeId = $this->_getUriSchemeId($uri->resourceIdentifier);
        $uriId = $this->recordExists('id', 'uri', array(
            'resource_identifier' => $uri->resourceIdentifier,
            'uri_scheme_id' => $uri->uriSchemeId)
        );
        if ($uriId) {
            $uri->id = $uriId;
            return $uri;
        }
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `uri` 
            (resource_identifier, uri_scheme_id) VALUES (?, ?)'
        );
        $stmt->execute(array(
            $uri->resourceIdentifier,
            $uri->uriSchemeId)
        );
        $uri->id = $this->_dbh->lastInsertId();
        return $uri;
    }
   
    private function _getUriSchemeId($resourceIdentifier) 
    {
        $pos = strpos($resourceIdentifier, '://');
        if ($pos) {
        	$scheme = substr($resourceIdentifier, 0, $pos);
        	if ($schemeId = $this->_getUriSchemeIdByScheme($scheme)) {
                return $schemeId;
            }
        }
        // Assume url is web link when lookup fails
        return $this->_getUriSchemeIdByScheme('http');
    }
    
    private function _getUriSchemeIdByScheme($scheme) 
    {
       $stmt = $this->_dbh->prepare(
           'SELECT id FROM `uri_scheme` WHERE scheme = ?'
       );
       if ($stmt->execute(array($scheme)) && $stmt->rowCount() == 1) {
           return $stmt->fetchColumn(0);
       }
       return false;
    }
}