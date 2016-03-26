<?php
	
namespace Battis\BatchAction;

/**
 * A filter that can be applied at run-time to a BatchManager to select Actions
 * to be run.
 *
 * @author Seth Battis <seth@battis.net>
 */
interface Filter {
	
	/**
	 * Filter an action according our criteria 
	 * 
	 * @param Action $action
	 * @return boolean `true` if the Action meets our criteria, `false` otherwise
	 */
	public function filter(Action $action);
}