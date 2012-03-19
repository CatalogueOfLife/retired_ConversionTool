<?php

interface TaxonMatcherEventListener {

	const MSG_ERROR = 2;
	const MSG_WARNING = 4;
	const MSG_INFO = 8;
	const MSG_OUTPUT = 16;
	const MSG_DEBUG = 32;
	
	public function onMessage($messageType, $message, Exception $exception = null);

}