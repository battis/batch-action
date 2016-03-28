<?php
	
namespace Battis\BatchAction\Sandbox;

use Battis\BatchAction\BatchManager_Exception;

/**
 * All exceptions thrown by Sandbox
 * 
 * @author Seth Battis <seth@battis.net>
 */
class Sandbox_Exception extends BatchManager_Exception {
	
	const PARAMETER_MISMATCH = 101;
}