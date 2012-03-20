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


}