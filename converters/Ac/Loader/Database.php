<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'model/AcToBs/Database.php';

/**
 * Count and load methods for Database
 * 
 * @author N�ria Torrescasana Aloy, Ruud Altenburg
 *
 */
class Ac_Loader_Database extends Ac_Loader_Abstract
    implements Ac_Loader_Interface
{
    /**
     * Count number of databases in Annual Checklist
     * 
     * @return int
     */
    public function count()
    {
        $stmt = $this->_dbh->prepare('SELECT COUNT(1) FROM `databases`');
        $stmt->execute();
        return $stmt->fetchColumn(0);
    }
    
    /**
     * Load all databases from Annual Checklist
     * 
     * Fetches databases all at once.
     * 
     * @return array $res array of Database objects
     */
    public function load($offset, $limit)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT `record_id` AS id, '.
            '`database_name_displayed` AS name, '.
            '`database_name` AS abbreviatedName, '.
            '`contact_person` AS contactPerson, '.
            '`taxa` AS groupNameInEnglish, '.
            '`authors_editors` AS authorsAndEditors, '.
            '`release_date` AS releaseDate, '.
            '`organization` AS organisation, '.
            '`abstract`, ' .
            '`web_site` AS uri,' .
            '`taxonomic_coverage` AS taxonomicCoverage, ' .
            '`is_new` AS isNew, ' .
            '`coverage`, `completeness`, `confidence`, ' .
            'version FROM `databases`'
        );
        $stmt->execute();
        $res = $stmt->fetchAll(PDO::FETCH_CLASS, 'Database');
        unset($stmt);
        return $res;
    }
}