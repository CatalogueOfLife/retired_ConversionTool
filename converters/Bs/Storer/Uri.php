<?php
require_once 'Interface.php';
require_once 'Abstract.php';

class Bs_Storer_Uri extends Bs_Storer_Abstract
    implements Bs_Storer_Interface
{
    public function clear()
    {
    	$this->_clearTables(array('uri'));
    }
    
    public function store(Model $uri)
    {
    	if ($uri->uriSchemeId == '') {
    	    $uri->uriSchemeId = $this->_getUriSchemeId(
    	        $uri->resourceIdentifier
    	    );
    	}
        $uriId = $this->_recordExists('id', 'uri', array(
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
   
    public function getUriSchemeIdByScheme($scheme) 
    {
        if ($id = Dictionary::get('uri_scheme', $scheme)) {
            return $id;
        }
        $stmt = $this->_dbh->prepare(
           'SELECT id FROM `uri_scheme` WHERE scheme = ?'
        );
        $result = $stmt->execute(array($scheme));
        if ($result && $stmt->rowCount() == 1) {
            $id = $stmt->fetchColumn(0);
            Dictionary::add('uri_scheme', $scheme, $id);
            return $id;
        }
        throw new Exception('Could not get scheme id for '.$scheme);
        return false;
    }
    
    private function _getUriSchemeId($resourceIdentifier) 
    {
        $pos = strpos($resourceIdentifier, '://');
        if ($pos) {
        	$scheme = substr($resourceIdentifier, 0, $pos);
        	if ($schemeId = $this->getUriSchemeIdByScheme($scheme)) {
                return $schemeId;
            }
        }
        // Assume url is web link when lookup fails
        return $this->getUriSchemeIdByScheme('http');
    }
    
}