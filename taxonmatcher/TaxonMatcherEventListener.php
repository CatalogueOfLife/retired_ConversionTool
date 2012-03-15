<?php

interface TaxonMatcherEventListener {

	const MESSAGE_TYPE_ERROR = 0;
	const MESSAGE_TYPE_WARNING = 1;
	const MESSAGE_TYPE_INFO = 2;
	const MESSAGE_TYPE_OUTPUT = 3;
	const MESSAGE_TYPE_DEBUG = 4;
	
	public function onMessage($messageType, $message, Exception $exception = null);

}