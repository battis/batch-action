<?php
	
namespace Battis\BatchAction;

/**
 * A sandbox to store information about a particular run of BatchManager
 * (primarily for debugging purposes).
 *
 * @author Seth Battis <seth@battis.net>
 */
class Sandbox {
	
	/** @var string Unique identifier for this run */
	private $id;
	
	/** @var \DateTime Timestamp of the completion of this run */
	private $timestamp;
	
	/** @var Action[] Sequence of Actions at the time of this run */
	private $actions = array();
	
	/** @var Filter The filter applied to this run */
	private $filter;
	
	/** @var boolean Whether or not the run was forced */
	private $force;
	
	/** @var mixed[] The sandbox variables generated by this run */
	private $environment;
	
	/** @var Result[] The results of the actions */
	private $results = array();
	
	/**
	 * Construct a new Sandbox
	 * 
	 * @param string $id Unique identifier for this run
	 * @param Action[] $actions The actions sequence
	 * @param Filter $filter The filter applied to those actions
	 * @param boolean $force Whether or not this was a forced run
	 * @param mixed[] $environment The sandbox of variables used by those actions
	 */
	public function __construct(string $id, array $actions, Filter $filter, $force = false, array &$environment) {
		$this->id = $id;
		$this->timestamp = new DateTime();
		foreach($actions as $action) {
			$this->actions[] = $action;
		}
		$this->filter = $filter;
		$this->force = $force;
		$this->environment =& $environment;
	}
	
	/**
	 * Add an action result to the sandbox
	 * 
	 * @param Result $result
	 * @return void
	 */
	public function addResult(Result $result) {
		$this->results[] = $result;
	}
	
	/**
	 * Get the current list of action results
	 * 
	 * @return Result[]
	 */
	public function getResults() {
		return $this->results;
	}
}