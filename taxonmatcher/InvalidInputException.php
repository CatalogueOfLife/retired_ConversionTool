<?php
/**
 * Exception thrown when TaxonMatcher does not have enough data to run, or when
 * the data is invalid.
 */

class_exists('TaxonMatcherException', false) || include 'TaxonMatcherException.php';

class InvalidInputException extends TaxonMatcherException {

}