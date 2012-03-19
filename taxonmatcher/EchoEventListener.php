<?php
/**
 * 
 * Simple (but probably useful) implementation of TaxonMatcherEventListener.
 * Basically just echoes any message coming out of the TaxonMatcher, prefixing
 * it with a timestamp. You can choose whether to make up the message as
 * HTML, using <p> tags. The <p> tag will then get a css class of
 * "taxon-matcher-message-type-{$messageType}". Example:
 * 
 * $listener = new EchoEventListener();
 * $listener->setContentTypeHTML(true);
 * $matcher = new TaxonMatcher();
 * $matcher->addEventListener($listener);
 * // ....
 * $matcher->run();
 * 
 */
class EchoEventListener extends AbstractTaxonMatcherEventListener {

	private $_isPlainText = true;


	public function setContentTypeHTML($isHTML=true)
	{
		$this->_isPlainText = ! $isHTML;
	}

	public function onMessage($messageType, $message, Exception $exception = null) {
		if($this->_isMessageEnabled($messageType)) {
			if($this->_isPlainText) {
				echo "\n" . date('Y-m-d H:i:s') . "\t" . $message;
				if($exception !== null) {
					echo "\n" . $exception->getTraceAsString();
				}
			}
			else {
				echo "<p class='taxon-matcher-message-type-{$messageType}'>";
				echo htmlentities($message);
				if($exception !== null) {
					echo '<pre>' . $exception->getTraceAsString() . '</pre>';
				}
				echo "</p>";
			}
		}
	}
}