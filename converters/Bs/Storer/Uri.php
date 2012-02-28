<?php
require_once 'Interface.php';
require_once 'Abstract.php';

/**
 * Uri storer
 * 
 * @author N�ria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Storer_Uri extends Bs_Storer_Abstract
    implements Bs_Storer_Interface
{
    public function store(Model $uri)
    {
        if (empty($uri->resourceIdentifier)) {
            return $uri;
        }
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
        $id = $this->_recordExists(
            'id', 'uri_scheme', array('scheme' => $scheme)
        );
        if ($id) {
            Dictionary::add('uri_scheme', $scheme, $id);
            return $id;
        }
        // Assume url is web link by returning false
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