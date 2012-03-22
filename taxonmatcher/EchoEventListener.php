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

class EchoEventListener extends AbstractTaxonMatcherEventListener {

	private $_isPlainText = true;
	private $_showStackTrace = false;


	public function setContentTypeHTML()
	{
		$this->_isPlainText = false;
	}
	
	public function showStackTrace()
	{
		$this->_showStackTrace = true;
	}

	public function onMessage($messageType, $message, Exception $exception = null) {
		if($this->_isMessageEnabled($messageType)) {
			if($this->_isPlainText) {
				$date = date('Y-m-d H:i:s');
				$type = self::_getMessageTypeAsString($messageType);
				printf("\n%s\t%s: %s", $date, $type, $message);
				if($this->_showStackTrace && ($exception !== null)) {
					echo "\n" . $exception->getTraceAsString();
				}
			}
			else {
				echo "<p class='taxon-matcher-msg-{$messageType}'>";
				echo htmlentities($message);
				if($this->_showStackTrace && ($exception !== null)) {
					echo '<pre>' . $exception->getTraceAsString() . '</pre>';
				}
				echo "</p>";
			}
		}
	}
	
}