<?php
header('Content-Type','text/plain');

include 'TaxonMatcher.php';

$ttm = new TestTaxonMatcher();
$ttm->runTests();


class TestTaxonMatcher {

	private $_errCount = 0;

	public function __construct()
	{
			
	}

	public function runTests()
	{
		$class = new ReflectionClass(get_class($this));
		$methods = $class->getMethods();
		foreach($methods as $method) {
			if(substr($method->getName(), 0, 5) === 'test_') {
				$method->invoke($this);
			}
		}
		if($this->_errCount !== 0) {
			echo "\n\n=====  S O M E   T E S T S   F A I L E D !  =====";
		}
		else {
			echo "\n\n=====  A L L   T E S T S   S U C C E S S F U L  =====";
		}
	}


	public function test_initialize()
	{
		$tm = new TaxonMatcher();
		$tm->setDbUser('root');
	}

	public function test__showResult()
	{
		try {
			echo "\n\nTesting: _showResult";

			// Let's connect to a base schema database on
			// dev.etibioinformatics.nl to test this.
			$pdo = new PDO('mysql:host=localhost;dbname=information_schema','root','');
			$stmt = $pdo->query('SELECT * FROM TABLES LIMIT 120');

			$class = new ReflectionClass('TaxonMatcher');
			$obj = $class->newInstance();
			$method = $class->getMethod('_showResult');
			$method->setAccessible(true);
			$method->invoke($obj, $stmt);
			
			echo "\nSUCCESS!";
			
		}
		catch(Exception $e) {
			++$this->_errCount;
			echo "\nFAILED: {$e->getMessage()}";
		}
	}

	public function test__getPDOForCumulativeTaxonList()
	{
		echo "\n\nTesting: _getPDOForCumulativeTaxonList";
		$class = new ReflectionClass('TaxonMatcher');
		$obj = $class->newInstance();
		$class->getMethod('setDbUser')->invoke($obj, 'root');
		$class->getMethod('setDbNameCTL')->invoke($obj, 'taxon_matcher_ctl_01');
		$method = $class->getMethod('_getPDOForCumulativeTaxonList');
		$method->setAccessible(true);
		try {
			$pdo = $method->invoke($obj);
			echo "\nSUCCESS!";
		}
		catch(Exception $e) {
			++$this->_errCount;
			echo "\nFAILED: {$e->getMessage()}";
		}
	}

	public function test__getPDO()
	{
		echo "\n\nTesting: _getPDO";
		$class = new ReflectionClass('TaxonMatcher');
		$obj = $class->newInstance();
		$class->getMethod('setDbUser')->invoke($obj, 'root');
		$method = $class->getMethod('_getPDO');
		$method->setAccessible(true);
		try {
			$pdo = $method->invoke($obj);
			echo "\nSUCCESS!";
		}
		catch(Exception $e) {
			++$this->_errCount;
			echo "\nFAILED: should be able to connect with just user root";
		}
	}





}