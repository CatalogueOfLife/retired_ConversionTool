<?php
/**
 * Abstract implementation of the TaxonMatcherEventListener. Subclasses won't
 * have to deal with enabling, disabling and checking message types.
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
	 * 	$listener->enableMessages(TaxonMatcherEventListener::MSG_DEBUG|TaxonMatcherEventListener::MSG_WARNING)
	 * </pre>
	 *
	 */
	public function enableMessages()
	{
		foreach(func_get_args() as $arg)
		{
			if(!$this->_isMessageEnabled($arg))
			{
				$this->_enabledMessageTypes = ($this->_enabledMessageTypes | $arg);
			}
		}
	}

	/**
	 * Sets the display flag for one or more message types to OFF.
	 * E.g. to disable debug messages and warnings:
	 *
	 * <pre>
	 * 	$listener->disableMessages(TaxonMatcherEventListener::MSG_DEBUG|TaxonMatcherEventListener::MSG_WARNING)
	 * </pre>
	 */
	public function disableMessages()
	{
		foreach(func_get_args() as $arg)
		{
			if($this->_isMessageEnabled($arg))
			{
				$this->_enabledMessageTypes = ($this->_enabledMessageTypes & ~$types);
			}
		}
	}

	protected static function _getMessageTypeAsString($messageType)
	{
		switch($messageType) {
			case TaxonMatcherEventListener::MSG_ERROR : return 'ERROR';
			case TaxonMatcherEventListener::MSG_WARNING : return 'WARNING';
			case TaxonMatcherEventListener::MSG_INFO : return 'INFO';
			case TaxonMatcherEventListener::MSG_OUTPUT : return 'OUTPUT';
			case TaxonMatcherEventListener::MSG_DEBUG : return 'DEBUG';
		}
	}

	protected function _isMessageEnabled($messageType)
	{
		return ($this->_enabledMessageTypes & $messageType) === $messageType;
	}



	private static $_phpErrorConstants = null;

	// Returns a map of PHP error constant values (integers) to
	// error constant values (e.g. 'E_WARNING').
	private static function _getPHPErrorConstants() {
		if(self::$_phpErrorConstants === null) {
			self::$_phpErrorConstants = get_defined_constants();
			foreach (self::$_phpErrorConstants as $name => $value) {
				// assume all and only error constant names start with 'E_'
				if (substr($name, 0, 2) === 'E_') {
					self::$_phpErrorConstants[$value] = $name;
				}
				unset(self::$_phpErrorConstants[$name]);
			}
		}
		return self::$_phpErrorConstants;
	}
	
	
	// Could make a more sophisticated mapping when the need arises.
	private static function _mapPHPErrorToTaxonMatcherError($phpError)
	{
		switch($phpError) {
			case E_ERROR: return TaxonMatcherEventListener::MSG_ERROR;
			case E_WARNING: return TaxonMatcherEventListener::MSG_WARNING;
			default: return TaxonMatcherEventListener::MSG_INFO;
		}
	}

}