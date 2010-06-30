<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'converters/Sc/Model/Taxon.php';

class Sc_Loader_Taxon extends Sc_Loader_Abstract implements Sc_Loader_Interface
{
    public function count()
    {
    	$stmt = $this->_dbh->prepare(
            'SELECT COUNT(1)
            FROM `Type1Cache` t1
            LEFT JOIN `StandardDataCache` t2 ON t1.TaxonCode = t2.taxonID'
        );
        $stmt->execute();
        $res = $stmt->fetchColumn(0);
        $stmt = $this->_dbh->prepare(
            'SELECT COUNT(DISTINCT t1.synonymCode) 
            FROM `SynonymWithRefs` t1
            INNER JOIN `StandardDataCache` t2 ON t1.avcNameCode = t2.taxonCode'
        );
        $stmt->execute(); 
        $res += $stmt->fetchColumn(0);
        unset($stmt);
        return $res;
    }
    
    public function load($offset, $limit)
    {
        $stmt = $this->_dbh->prepare(
            self::SQL_TYPE1CACHE . 
            ' UNION ' . 
            self::SQL_SYNONYMWITHREFS .  
            ' LIMIT :offset, :limit'
        );
         
        $stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $taxa = array();
        
        while($taxon = $stmt->fetchObject('Sc_Model_Taxon')) {
            $taxon->databaseId = Dictionary::get('dbs', $taxon->databaseName);
            $specialistId = Dictionary::get(
                'specialists', $taxon->specialistName
            );
            if($specialistId) {
                $taxon->specialistId = $specialistId;
            }
            $rStmt = $this->_dbh->prepare(
                'SELECT author, year, title, details AS source
                FROM Reference WHERE ' .
                    (in_array($taxon->nameStatusId, array(1,4)) ?
                        'avcNameCode' : 'synonymWithRefsCode') . ' = ?' 
            );
            $rStmt->execute(array($taxon->taxonCode));
            $taxon->references = 
                $rStmt->fetchAll(PDO::FETCH_CLASS, 'Reference');
            unset($rStmt);
            $taxa[] = $taxon;
        }
        unset($stmt);
        return $taxa;
    }
    
    const SQL_SYNONYMWITHREFS = "        
        (SELECT
            t1.synonymCode AS nameCode,
            t2.datalink AS webSite,
            t1.genus,
            t1.specificepithet AS species,
            t1.infraspecificepithet AS infraspecies,
            t1.infraspecificmarker AS infraspeciesMarker,
            t1.authority AS author,
            t1.avcNameCode AS acceptedNameCode,
            t2.`comment`,
            t2.taxonCode,
            IF (t2.scrutinyyear > 0,
               CONCAT(
                   t2.scrutinyday, '-',
                   t2.scrutinymonth, '-',
                   t2.scrutinyyear
                   ), NULL)
               AS scrutinyDate,
            CASE t1.synonymStatus
                WHEN 'accepted' THEN 1
                WHEN 'provisional' THEN 4
                WHEN 'synonym' THEN 5
                WHEN 'ambiguous' THEN 2
                WHEN 'misapplied' THEN 3
                END AS nameStatusId,
            '' AS databaseName,
            t2.scrutinyperson AS specialistName,
            t2.family AS familyName,
            IF (t1.synonymStatus = 'accepted' OR t1.synonymStatus = 'provisional', 1, 0)
                AS isAcceptedName
            FROM `SynonymWithRefs` t1
            INNER JOIN `StandardDataCache` t2 ON t1.avcNameCode = t2.taxonCode
            GROUP BY nameCode)        
    "; 
    const SQL_TYPE1CACHE = "
        (SELECT
            t1.taxoncode AS nameCode,
            t2.datalink AS webSite,
            t1.genus,
            t1.specificepithet AS species,
            t1.infraspecificepithet AS infraspecies,
            t1.infraspecificmarker AS infraspeciesMarker,
            t1.authority AS author,
            t1.taxoncode AS acceptedNameCode,
            t2.`comment`,
            t2.taxonCode,
            IF (t2.scrutinyyear > 0,
               CONCAT(
                   t2.scrutinyday, '-',
                   t2.scrutinymonth, '-',
                   t2.scrutinyyear
                   ), NULL)
               AS scrutinyDate,
            CASE t1.status
                WHEN 'accepted' THEN 1
                WHEN 'provisional' THEN 4
                WHEN 'synonym' THEN 5
                WHEN 'ambiguous' THEN 2
                WHEN 'misapplied' THEN 3
                END AS nameStatusId,
            t1.source AS databaseName,
            t2.scrutinyperson AS specialistName,
            t2.family AS familyName,
            IF (t1.status = 'accepted' OR t1.status = 'provisional', 1, 0)
                AS isAcceptedName
            FROM `Type1Cache` t1
            LEFT JOIN `StandardDataCache` t2 ON t1.TaxonCode = t2.taxonID)";
}