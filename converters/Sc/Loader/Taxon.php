<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'converters/Sc/Model/ScToDc/Taxon.php';

class Sc_Loader_Taxon extends Sc_Loader_Abstract implements Sc_Loader_Interface
{
    public function count()
    {
    	$stmt = $this->_dbh->prepare(
            'SELECT COUNT(1) FROM `StandardDataCache`'
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
            self::SQL_STANDARDDATACACHE . 
            ' UNION ' . 
            self::SQL_SYNONYMWITHREFS .  
            ' LIMIT :offset, :limit'
        );
         
        $stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $taxa = array();
        
        while($taxon = $stmt->fetchObject('Sc_Model_ScToDc_Taxon')) {
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
            t2.dataLink AS webSite,
            t1.genus,
            t1.specificEpithet AS species,
            t1.infraspecificEpithet AS infraspecies,
            t1.infraspecificMarker AS infraspeciesMarker,
            t1.authority AS author,
            t1.avcNameCode AS acceptedNameCode,
            t2.`comment`,
            t2.taxonCode,
            IF (t2.scrutinyYear > 0,
               CONCAT(
                   t2.scrutinyDay, '-',
                   t2.scrutinyMonth, '-',
                   t2.scrutinyYear
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
            INNER JOIN `StandardDataCache` t2 ON t1.avcNameCode = t2.taxonCode)        
    "; 
    const SQL_STANDARDDATACACHE = "
        (SELECT
			taxonCode AS nameCode,
			dataLink AS webSite,
			genus,
			specificEpithet AS species,
			infraspecificEpithet AS infraspecies,
			infraspecificMarker AS infraspeciesMarker,
			authority AS author,
			taxonCode AS acceptedNameCode,
			`comment`,
			taxonCode,
			IF (scrutinyyear > 0,
			   CONCAT(
			       scrutinyday, '-',
			       scrutinymonth, '-',
			       scrutinyyear
			       ), NULL)
			   AS scrutinyDate,
			CASE namestatus
			    WHEN 'accepted' THEN 1
			    WHEN 'provisional' THEN 4
			    WHEN 'synonym' THEN 5
			    WHEN 'ambiguous' THEN 2
			    WHEN 'misapplied' THEN 3
			    END AS nameStatusId,
			gsdname AS databaseName,
			scrutinyperson AS specialistName,
			family AS familyName,
			IF (namestatus = 'accepted' OR namestatus = 'provisional', 1, 0)
			    AS isAcceptedName
			FROM `StandardDataCache`)";
}