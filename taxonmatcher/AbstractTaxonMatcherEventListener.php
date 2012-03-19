<?php
/**
 * 
 * Abstract implementation of the TaxonMatcherEventListener. Subclasses are spared
 * the hazzle of enabling, disabling and checking message types.
 *
 */
abstract class AbstractTaxonMatcherEventListener implements TaxonMatcherEventListener {

	private $_enabledMessageTypes;

	public function __construct()
	{
		// By default show all messages except debug messages
		$this->_enabledMessageTypes = (
				TaxonMatcherEventListener::MSG_ERROR |
				TaxonMatcherEventListener::MSG_WARNING |
				TaxonMatcherEventListener::MSG_INFO |
				TaxonMatcherEventListener::MSG_OUTPUT
		);
	}
	
	/**
	 * Sets the display flag for one or more message types to ON.
	 * E.g. to enable debug messages and warnings:
	 * 
	 * <pre>
	 * 	$myListener->displayMessages(TaxonMatcherEventListener::MSG_DEBUG|TaxonMatcherEventListener::MSG_WARNING)
	 * </pre>
	 * 
	 */
	public function enableMessages()
	{
		$types = 0;
		foreach(func_get_args() as $arg)
		{
			$types |= $arg;
		}
		$this->_enabledMessageTypes &= $types;
	}
	
	/**
	 * Sets the display flag for one or more message types to OFF.
	 * E.g. to disable debug messages and warnings:
	 * 
	 * <pre>
	 * 	$myListener->disableMessages(TaxonMatcherEventListener::MSG_DEBUG|TaxonMatcherEventListener::MSG_WARNING)
	 * </pre>
	 * 
	 */
	public function disableMessages()
	{
		$types = 0;
		foreach(func_get_args() as $arg)
		{
			$types |= $arg;
		}
		$this->_enabledMessageTypes &= ~$types;
	}

	protected function _isMessageEnabled($messageType)
	{
		return $this->_enabledMessageTypes & $messageType === $messageType;
	}
	
}