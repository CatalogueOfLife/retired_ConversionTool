<?php
/**
 * @author Ayco Holleman, ETI BioInformatics
 * @author Richard White (original PERL implementation), Cardiff University
 *
 * Stand-alone version of the taxon matcher that must be run from the
 * command line using the PHP command line interpreter (CLI). There is
 * an ultra-thin wrapper for this script, go.bat, in case you are on
 * a Windows machine.
 *
 * USAGE:
 *
 * php taxonmatcher-cli.php [path/to/ini/file]
 *
 * If you omit the ini file argument, ./TaxonMatcher.ini will be assumed.
 */


header('Content-Type', 'text/plain');

interface_exists('TaxonMatcherEventListener', false) || include '../TaxonMatcherEventListener.php';

class_exists('TaxonMatcher', false) || include '../TaxonMatcher.php';
class_exists('EchoEventListener', false) || include '../EchoEventListener.php';



// Used to read boolean settings from TaxonMatcher.ini
function isTrue($val) {
	return in_array(strtolower($val), array('1','true','on','yes'));
}



$iniFile = (($argc === 1) ? 'TaxonMatcher.ini' : $argv[1]);
if(! is_file($iniFile)) {
	die('No such file (or not readable): ' . $iniFile);
}

$config = parse_ini_file($iniFile);
if($config === false) {
	die('Error parsing ini file');
}

$taxonMatcher = new TaxonMatcher();

// Configure the TaxonMatcher.
$taxonMatcher->setDbHost($config['dbHost']);
$taxonMatcher->setDbUser($config['dbUser']);
$taxonMatcher->setDbPassword($config['dbPassword']);
$taxonMatcher->setDbNameCurrent($config['dbNameCurrent']);
$taxonMatcher->setDbNameNext($config['dbNameNext']);
$taxonMatcher->setDbNameStage($config['dbNameStage']);
$taxonMatcher->setLSIDSuffix($config['lsidSuffix']);
$taxonMatcher->setReadLimit($config['readLimit']);
if(isTrue($config['resetLSIDs'])) {
	$taxonMatcher->setResetLSIDs(true);
}
if(isTrue($config['dropStagingArea'])) {
	$taxonMatcher->setDropStagingArea(true);
}



// Let's be interested in what the TaxonMatcher does.

$listener = new EchoEventListener();

// Configure the listener.
if(isTrue($config['debug'])) {
	$listener->enableMessages(TaxonMatcherEventListener::MSG_DEBUG);
}
if(isTrue($config['showStackTrace'])) {
	$listener->showStackTrace();
}

$taxonMatcher->addEventListener($listener);


// Go ...
try {
	$taxonMatcher->run();
}
catch(Exception $e) {
	// No need to print it out, because that will already
	// have been done by the EchoEventListener.
}