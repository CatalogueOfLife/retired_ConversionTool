<?php
require_once 'library/Encoding.php';

/**
 * Abstract storer
 *
 * @author Nuria Torrescasana Aloy, Ruud Altenburg
 */
abstract class Bs_Storer_Abstract
{
    protected $_dbh;
    protected $_logger;

    public function __construct (PDO $dbh, Zend_Log $logger)
    {
        $this->_dbh = $dbh;
        $this->_logger = $logger;
    }
    
    public function fixUtf8 ($string) {
    	$encoding = new ForceUTF8\Encoding();
    	return $encoding::fixUTF8($string);
    }
    
    public function stripNonPrintableCharacters ($string) 
    {
    	return filter_var($string, FILTER_UNSAFE_RAW, 
    		FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH);
    }

    /**
     * Test if a single record exists in storer database
     *
     * Returns the value of $return_column or false if record does not exist.
     *
     * @param mixed $return_column value in this field will be returned
     * @param string $table table to be searched
     * @param array $where associative array with pairs column => value
     * @return int|str|false value of $return_column or false if record does not exist
     */
    protected function _recordExists ($return_column, $table, array $where)
    {
        $query = 'SELECT `' . $return_column . '` FROM `' . $table . '` WHERE ';
        foreach ($where as $column => $value) {
            $query .= ' (`' . $column . '` = :' . $column;
            if ($value == null) {
                $query .= ' OR `' . $column . '` IS NULL';
            }
            $query .= ') AND ';
        }
        $stmt = $this->_dbh->prepare(substr($query, 0, -5));
        try {
            if ($stmt->execute($where) && $stmt->rowCount() == 1) {
                return $stmt->fetchColumn(0);
            }
        } catch (PDOException $e) {
            $this->_handleException("Could not write conversion_error", $e);
        }
        return false;
    }

    /**
     * Converts HTML entities to UTF8
     *
     * @param string $string
     * @return string converted string
     */
    public function convertHtmlToUtf ($string)
    {
        return html_entity_decode($string, ENT_COMPAT, 'UTF-8');
    }

    /**
     * Returns the last value from an array
     *
     * @param array $array
     * @return mixed last value of array
     */
    public function getLastKeyArray ($array)
    {
        end($array);
        $key = key($array);
        reset($array);
        return $key;
    }

    /**
     * Print object (or array) using print_r()
     */
    public function printObject ($object)
    {
        echo '<pre>';
        print_r($object);
        echo '</pre>';
    }

    /**
     * Parse date from Annual Checklist to yyyy-mm-dd
     *
     * Strips off specific strings used in Annual Checklist 2010 and tries to
     * format to valid date. Returns NULL if string cannot be parsed.
     *
     * @param string $date
     * @return date|NULL correctly formatted MySQL date or NULL if parsing is not possible
     */
    public function parseAcDate ($date)
    {
        $date = trim($date);
        // First strip off FishBase string if it is present
        if (strpos($date, 'FishBase') !== false) {
            $date = str_replace('Data last modified by FishBase ', '', $date);
            // Also strip off time in some of these...
            if (strlen($date) > 11) {
                $parts = explode($date, ' ');
                $date = $parts[0];
            }
        }
        // Only dates of the type 5-Mar-1990 or 05-Mar-1990 are considered valid
        if (strpos($date, '-') !== false && in_array(strlen($date),
            array(
                10,
                11
            ))) {
            $dateToTimestamp = strtotime($date);
            return date('Y-m-d', $dateToTimestamp);
        }
        return null;
    }

    public function writeToErrorTable ($taxon_id, $name, $message, $nameCode = null)
    {
        $m = "\nRecord skipped during conversion: \n" .
            "id: $taxon_id\n" .
            "name: $name\n" .
            "name code: $nameCode\n" .
            "reason: $message\n";
        $this->_logger->err($m);
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `_conversion_errors` (`id`, `name`, `message`, `name_code`)
            VALUES (?, ?, ?, ?)');
        try {
            $stmt->execute(array(
                $taxon_id,
                $name,
                $message,
                $nameCode
            ));
        } catch (PDOException $e) {
            $this->_handleException("Could not write conversion_error", $e);
        }
    }

    protected function _handleException ($message, $e = false)
    {
        $message = "\n$message: \n" . ($e ? "Exception: \n" . $e->getMessage() : '');
        $this->_logger->err($message);
        die('<p>' . $message . '</p>');
    }
}