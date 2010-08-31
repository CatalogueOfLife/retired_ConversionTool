<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'model/AcToBs/Reference.php';
require_once 'converters/Bs/Storer/Reference.php';

class Bs_Storer_CommonName extends Bs_Storer_Abstract
    implements Bs_Storer_Interface
{
    /* BOTH LANGUAGE AND COUNTRY NEED TO BE EXTENDED!
	Language
	
	SELECT language, count(language) as c from col2010ac.common_names where 
	language not in (select name from base_scheme.language) group by language order by c desc
	
	Country
	
	SELECT country, count(country) as c from col2010ac.common_names where 
	country not in (select name from base_scheme.country) group by country order by c desc
*/
		
    // Incomplete set of languages in Sp2010ac database that cannot be
    // mapped to predefined languages from ISO standard
    private static $languageMap = array (
        'Malay' => 'Malay (individual language)',
        'Greek' => 'Modern Greek (1453-)',
        'Swahili' => 'Swahili (individual language)'
    );
    
    // Incomplete set of countries in Sp2010ac database that cannot be
    // mapped to predefined countries from ISO standard
    private static $countryMap = array (
        'China Main' => 'China',
        'UK' => 'United Kingdom',
        'Czech Rep' => 'Czech Republic',
        'Russian Fed' => 'Russia'
    );
    
    public function store(Model $commonName)
    {
        // First translate language and country if necessary
        if (array_key_exists($commonName->language, self::$languageMap)) {
            $commonName->language = self::$languageMap[$commonName->language];
        }
    	$this->_getLanguageIso($commonName);
        if (array_key_exists($commonName->country, self::$countryMap)) {
            $commonName->country = self::$countryMap[$commonName->country];
        }
        $this->_getCountryIso($commonName);
        $this->_setCommonNameReference($commonName);
        $this->_setCommonNameElement($commonName);
        $this->_setCommonName($commonName);
        $this->_setReferenceToCommonName($commonName);
        return $commonName;
    }
    
    private function _getLanguageIso(Model $commonName) {
        if ($iso = Dictionary::get('languages', $commonName->language)) {
            $commonName->languageIso = $iso;
            return $commonName;
        }
        $iso = $this->_recordExists('iso', 'language', 
            array('name' => $commonName->language));
        if ($iso) {
            Dictionary::add('languages', $commonName->language, $iso);
            $commonName->languageIso = $iso;
            return $commonName;
        }
        return NULL;
    }

    private function _getCountryIso(Model $commonName) {
        if ($iso = Dictionary::get('countries', $commonName->country)) {
            $commonName->countryIso = $iso;
            return $commonName;
        }
        $iso = $this->_recordExists('iso', 'country', 
            array('name' => $commonName->country));
        if ($iso) {
            Dictionary::add('countries', $commonName->country, $iso);
            $commonName->countryIso = $iso;
            return $commonName;
        }
        return NULL;
    }
    
    private function _setCommonNameReference(Model $commonName) {
        $reference = new Reference();
        $reference->title = $commonName->referenceTitle;
        $reference->authors = $commonName->referenceAuthors;
        $reference->year = $commonName->referenceYear;
        $reference->text = $commonName->referenceText;
        $storer = new Bs_Storer_Reference($this->_dbh, $this->_logger);
        $storer->store($reference);
        
        $commonName->referenceId = $reference->id;
        unset($reference, $storer);
        return $commonName;
    }
    
    private function _setCommonNameElement(Model $commonName) 
    {
        $commonNameElementId = $this->_recordExists('id', 'common_name_element', 
            array('name' => $commonName->commonNameElement)
        );
        if ($commonNameElementId) {
            $commonName->commonNameElementId = $commonNameElementId;
        } else {
            $stmt = $this->_dbh->prepare(
                'INSERT INTO `common_name_element` (`name`) VALUE (?)'
            );
            $stmt->execute(array($commonName->commonNameElement));
            $commonName->commonNameElementId = $this->_dbh->lastInsertId();
        }
        return $commonName;
    }

    private function _setCommonName(Model $commonName) 
    {
        $commonNameId = $this->_recordExists('id', 'common_name', 
            array(
                'taxon_id' => $commonName->taxonId,
                'common_name_element_id' => $commonName->commonNameElementId,
                'language_iso' => $commonName->languageIso,
                'country_iso' => $commonName->countryIso)
        );
        if ($commonNameId) {
            $commonName->id = $commonNameId;
        } else {
            $stmt = $this->_dbh->prepare(
                'INSERT INTO `common_name` (`taxon_id`, `common_name_element_id`, '.
                '`language_iso`, `country_iso`) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute(array(
                $commonName->taxonId,
                $commonName->commonNameElementId,
                $commonName->languageIso,
                $commonName->countryIso)
            );
            $commonName->id = $this->_dbh->lastInsertId();
        }
        return $commonName;
    }
    
    private function _setReferenceToCommonName(Model $commonName) 
    {
        $refToCN = $this->_recordExists('reference_id', 
            'reference_to_common_name', 
            array(
                'reference_id' => $commonName->referenceId,
                'common_name_id' => $commonName->id)
        );
        // Do nothing if record exists
        if ($refToCN) {
            return $commonName;
        } else {
	    	$stmt = $this->_dbh->prepare(
	            'INSERT INTO `reference_to_common_name` (`reference_id`, '.
	            '`common_name_id`) VALUES (?, ?)'
	        );
	        $stmt->execute(array($commonName->referenceId, $commonName->id));
        }
        return $commonName;
    }
}