<?php
/**
 *
 * @author Ayco Holleman, ETI BioInformatics
 * @author Richard White (original PERL implementation), Cardiff University
 *
 * Simple (but probably useful) implementation of TaxonMatcherEventListener.
 * Basically just echoes any message coming out of the TaxonMatcher, prefixing
 * it with a timestamp. You have two configuration options:
 *
 * [1] You can choose to have the messge marked up as HTML, in which case the
 * EchoEventListener will embed it between <p> tags. The <p> tag will get a css
 * class of "taxon-matcher-msg-{$messageType}". @see TaxonMatcherEventListener.
 *
 * [2] You can choose to display Exception stack traces in case an exception
 * was passed to the onMessage() method. (N.B. Even if you enable DEBUG
 * messages using AbstractTaxonMatcherEventListener::enableMessages(), The
 * EchoEventListener will still not display stack traces unless you call
 * its showStackTrace() method.)
 *
 *
 * Example that runs the taxon matcher in full debug mode:
 *
 * $listener = new EchoEventListener();
 * $listener->enableMessages(TaxonMatcherEventListener::MSG_DEBUG);
 * $listener->showStackTrace();
 *
 * $matcher = new TaxonMatcher();
 * $matcher->addEventListener($listener);
 * // ....
 * $matcher->run();
 *
 */

class_exists('AbstractTaxonMatcherEventListener', false) || include 'AbstractTaxonMatcherEventListener.php';

class LogEventListener extends AbstractTaxonMatcherEventListener {

	private $_logger;

	public function setLogger($logger)
	{
		$this->_logger = $logger;
	}

	public function onMessage($messageType, $message, Exception $exception = null) {
		if($this->_isMessageEnabled($messageType) && $exception !== null) {
			$this->_logger->err("\nTaxon matcher:\n" . $message .
			    "\n" . $exception->getTraceAsString()) . "\n";
		}
	}

}