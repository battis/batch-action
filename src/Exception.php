<?php

/** Exception class */
namespace Battis\BatchAction;

/**
 * Exceptions thrown by BatchAction classes
 *
 * @author Seth Battis <seth@battis.net>
 */
class Exception extends \Exception {

	const INVALID_BATCH = 1;

	const INVALID_PATH = 2;

	const INVALID_PARAMETER = 3;

	const MYSQL_ERROR = 4;
	
	const INVALID_FILTER = 5;
	
	const INVALID_STEP = 6;
	
	const EXECUTION_OUT_OF_ORDER = 7;
}

?>