<?php
/**
 * The interface for objects that are interested in messages coming out
 * from the TaxonMatcher. The taxonmatcher-cli.php script provides a
 * good example of instantiating a TaxonMatcherEventListener implementation
 * (EchoEventListener) and attaching it to the TaxonMatcher.
 */

interface TaxonMatcherEventListener {

	const MSG_ERROR = 2;
	const MSG_WARNING = 4;
	const MSG_INFO = 8;
	/**
	 * MSG_OUTPUT is the message type of (usually formatted) program output
	 * that actually results from the program's computations, e.g. tables.
	 * Richard White's PERL version of the TaxonMatcher had this sort of
	 * messages. The PHP version as yet does not, but we still include this
	 * message type, just to be sure.
	 * 
	 * @var number
	 */
	const MSG_OUTPUT = 16;
	const MSG_DEBUG = 32;
	
	public function onMessage($messageType, $message, Exception $exception = null);

}