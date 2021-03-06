<?php
	
namespace Battis\BatchAction;

/**
 * An action that can be run by the BatchManager, generating a Result
 * 
 * @author Seth Battis <seth@battis.net>
 */
abstract class Action {
	
	/**
	 * @var Action[] List of prerequisite Actions that must act before this Action
	 *		can act
	 */
	private $prerequisites = array();
	
	/** @var string[] List of tags for filtering purposes */
	private $tags = array();
	
	/**
	 * @var boolean Is this Action currently acting (but waiting on a
	 *		prerequisite)?
	 */
	private $acting = false;
	
	/** @var boolean Has this action already been executed (ever)? */
	private $acted = false;
	
	/** @var string[] List of runs in which this action was executed */
	private $history = array();
	
	/**
	 * Construct a new Action
	 * 
	 * @param Action|Action[] $prerequisites A prerequisite Action or Actions that
	 *		must be run before this Action can act (default: empty list)
	 * @param string|string[] $tags A tag or tags used for filtering purposes. All
	 *		non-string tag values will be treated as strings (default: empty list)
	 */
	public function __construct($prerequisites = array(), $tags = array()) {
		
		/* save any prerequisite Actions */
		if (is_array($prerequisites)) {
			foreach ($prerequisites as $key => $action) {
				if (is_a($action, Action::class)) {
					$this->prerequisites[$key] = $action;
				}
			}
		} elseif (is_a($prerequisites, Action::class)) {
			$this->prerequisites[] = $prerequisites;
		}
		
		/* save tags as strings */
		if (is_array($tags)){
			foreach ($tags as $tag) {
				$this->tags[] = (string) $tag;
			}
		} else {
			$this->tags[] = (string) $tags;
		}
	}

	/**
	 * Add a prerequisite Action for this Action
	 * 
	 * Prerequisites can only be added once (subsequent additions of existing
	 * prerequisites will be ignored)
	 *
	 * @param Action $action
	 * 
	 * @return boolean `true` if the prerequisite was added, `false` otherwise
	 */
	public function addPrerequisite(Action $action) {
		if (!in_array($action, $this->prerequisites)) {
			$this->prerequisites[] = $action;
			return true;
		}
		return false;
	}
	
	/**
	 * Remove a prerequisite Action
	 * 
	 * @param Action $action
	 *
	 * @return boolean `true` if the action was previously a prerequisite and was
	 *		removed, `false` otherwise
	 */
	public function removePrerequisite(Action $action) {
		if ($key = array_search($action, $this->prerequisites)) {
			unset($this->prerequisites[$key]);
			return true;
		}
		return false;
	}
	
	/**
	 * Add a tag for filtering
	 *
	 * Tags can only be added once (subsequent additions of existing tags will be
	 * ignored)
	 * 
	 * @param string $tag Non-string values will be treated as strings
	 *
	 * @return boolean `true` if the tag was added, `false` otherwise
	 */
	public function addTag($tag) {
		if (!in_array((string) $tag, $this->tags)) {
			$this->tags[] = (string) $tag;
			return true;
		}
		return false;
	}
	
	/**
	 * Remove a tag
	 * 
	 * @param string $tag Non-string values will be treated as strings
	 *
	 * @return boolean `true` if the tag was previously a tag and was removed,
	 *		`false` otherwise
	 */
	public function removeTag($tag) {
		if ($key = array_search((string) $tag, $this->tags)) {
			unset($this->tags[$key]);
			return true;
		}
		return false;
	}
	
	/**
	 * Convert a list of arguments into individual method calls
	 * 
	 * @param array $args 
	 * @param string $function Callback function
	 *
	 * @return boolean `true` if all method calls returned `true`, `false`
	 *		otherwise
	 */
	private function processAllArgs($args, $function) {
		$result = true;
		for (reset($args); $arg = current($args); next($args)) {
			if (is_array($arg)) {
				array_push($args, $arg);
			} else {
				$result &= $this->$function($arg);
			}
		}
		return $result;
	}
	
	/**
	 * Add prerequisite Actions
	 * 
	 * `boolean Action::addPrerequisites( Action $a [, Action $b [, etc.]] )`
	 *
	 * @return boolean `true` if all prerequisites added, `false` otherwise
	 */
	public function addPrerequisites() {
		$this->processAllArgs(func_get_args(), 'addPrerequisite');
	}
	
	/**
	 * Add prerequisite Actions
	 * 
	 * `boolean Action::removePrerequisites( Action $a [, Action $b [, etc.]] )`
	 *
	 * @return boolean `true` if all prerequisites removed, `false` otherwise
	 */
	public function removePrerequisites() {
		$this->processAllArgs(func_get_args(), 'removePrerequisite');
	}
	
	/**
	 * Add tags
	 * 
	 * `boolean Action::addTags( string $a [, string $b [, etc.]] )`
	 *
	 * @return boolean `true` if all tags added, `false` otherwise
	 */
	public function addTags() {
		$this->processAllArgs(func_get_args(), 'addTag');
	}
	
	/**
	 * Remove tags
	 * 
	 * `boolean Action::removeTags( string $a [, string $b [, etc.]] )`
	 *
	 * @return boolean `true` if all tags removed, `false` otherwise
	 */
	public function removeTags() {
		$this->processAllArgs(func_get_args(), 'removeTag');
	}
	
	/**
	 * Is this action currently executing?
	 *
	 * This should only be true if the action is waiting on a prerequisite to
	 * execute
	 * 
	 * @return boolean
	 */
	public function isActing() {
		return $this->acting;
	}
	
	/**
	 * Has the action been executed in the past?
	 *
	 * If a run identifier is provided, has this action been executed for that
	 * specific run?
	 * 
	 * @param string|boolean $id Optional run identifier (default: false)
	 *
	 * @return boolean
	 */
	public function hasActed($id = false) {
		if ($id === false) {
			return $this->acted;
		} elseif ($this->acted && in_array($id, $this->runs)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Test prerequisites for this action
	 *
	 * If prerequistes have not yet been executed, attempt to execute them.
	 * 
	 * @param array $environment The BatchManager execution environment of shared
	 *		variables between actions
	 * @param string $id Unique identifier of this specific run of the BatchManager
	 * @param bool $force Whether or not to force this action to run, even if it has
	 *		been run in the past. (If it has been run previously in this run, it will
	 *		not be run again). (default: `false`)
	 *
	 * @return Result|Result[]
	 */
	private function testPrerequisites(array &$environment, $id, $force = false) {
		$results = array();
		foreach($this->prerequisites as $prerequisite) {
			if ($force || !$prerequisite->hasActed($id)) {
				$results[] = $prerequisite->run($environment, $id, $force);
			}
		}
		return $results;
	}

	/**
	 * Test the sandbox environment variables
	 *
	 * An overrideable function for extensions of Action to implement if specific
	 * configurations of the BatchAdmin execution sandbox variables are required as
	 * a prerequisite for this Action to run.
	 * 
	 * @param array $environment The BatchManager execution environment of shared
	 *		variables between actions
	 *
	 * @return boolean
	 */
	public function testSandbox(array &$environment) {
		return true;
	}
	
	/**
	 * Run this action
	 * 
	 * @param array $environment The BatchManager execution environment of shared
	 *		variables between actions
	 * @param string $id Unique identifier of this specific run of the BatchManager
	 * @param bool $force Whether or not to force this action to run, even if it has
	 *		been run in the past. (If it has been run previously in this run, it will
	 *		not be run again). (default: `false`)
	 *
	 * @return Result
	 */
	public function run(array &$environment, $id, $force = false) {
		if ((!$force && $this->hasActed()) || ($force && $this->hasActed($id))) {
			return new Result(
				get_class($this) . ' already run',
				'This action has already run and was not run again.'
			);
		} else {
			if (!$this->isActing()) {
				$this->acting = true;
				$results = array();
				$result = new Result(
					get_class($this),
					get_class($this) . ' incomplete',
					'This action has not run to completion.',
					Result::DANGER
				);
				try {
					$results = $this->testPrerequisites($environment, $id, $force);
					if (!$this->testSandbox($environment)) {
						throw new Action_Exception(
							get_class($this) . 'requires a prerequisite configuration of the sandbox execution environment that failed',
							Action_Exception::FAILED_PREREQUISITE
						);
					}
				} catch (Action_Exception $e) {
					throw new Action_Exception(
						get_class($this) . ' requires a prerequisite that failed: ' . $e->getMessage() . ' (Action_Exception ' . $e->getCode() . ')',
						Action_Exception::FAILED_PREREQUISITE
					);
				}
				$result = $this->act($environment);
				if (Result::completed($result)) {
					$this->acting = false;
					$this->acted = true;
					$this->history[] = $id;
				} else {
					throw new Action_Exception(
						get_class($this) . ' failed to act',
						Action_Exception::ACTION_FAILED
					);
				}
			} else {
				throw new Action_Exception(
					get_class($this) . ' created a circular prerequisite dependency',
					Action_Exception::CIRCULAR_DEPENDENCY
				);
			}
			$results[] = $result;
			return $results;
		}
	}
	
	/**
	 * What this Action does
	 *
	 * Overrideable method for extensions of Action to implement.
	 * 
	 * @param array $environment The BatchManager execution environment of shared
	 *		variables between actions
	 *
	 * @return Result An encapsulated summary (including human-readable feedback)
	 *		of the action.
	 */
	abstract public function act(array &$environment);
}