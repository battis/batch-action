<?php
	
namespace Battis\BatchAction;

/**
 * A container for data that might be replaced by data stored in the
 * BatchManager execution environment sandbox.
 *
 * @author Seth Battis <seth@battis.net>
 */
class SandboxReplaceableData {
	
	const PATH_DELIMITER = '/';
	
	/** @var string Expected type of `$data` */
	private $class;
	
	/** @var mixed Data that can be replaced from the sandbox */
	private $data = null;
	
	/** @var array Reference to the execution environment sandbox*/
	private $environment = null;
	
	/** @var array The path to the data in the sandbox */
	private $path = array();
	
	/**
	 * Construct the container
	 * 
	 * @param string $class The type of data expected
	 * @param mixed $dataOrSandboxPath Either the data itself (matching the `$type`
	 *		specified, for example if `$type == 'mysqli'` then `$data` must be an
	 *		instance of `mysqli`), or a path from the root of the sandbox environment
	 *		to the data desired (e.g. `'/config/mysql'`)
	 */
	public function __construct($class, $dataOrSandboxPath) {
		$this->class = (string) $class;
		if ($dataOrSandboxPath instanceof $class) {
			$this->data = $dataOrSandboxPath;
		} elseif (is_string($dataOrSandboxPath)) {
			$this->path = explode(self::PATH_DELIMITER, preg_replace('%/(.*)%', '$1', $dataOrSandboxPath));
		} else {
			throw new Sandbox_Exception(
				"Expected either an instance of `$type` or a path in the sandbox, received `" . get_class($dataOrSandboxPath) . '` instead.',
				Sandbox_Exception::PARAMETER_MISMATCH
			);
		}
	}
	
	/**
	 * Attach this container to a sandbox environment
	 * 
	 * @param array $environment The BatchManager execution environment of shared
	 *		variables between actions
	 * @return void
	 */
	public function setSandbox(array &$environment) {
		$this->environment =& $environment;
	}
	
	/**
	 * Get the expected type of this data
	 * 
	 * @return string
	 */
	public function getClass() {
		return $this->class;
	}
	
	/**
	 * Get the data
	 *
	 * If the container has literal data, that will be returned. Otherwise, the
	 * container must have a reference to a sandbox environment and a will return
	 * any data present at the specified path.
	 * 
	 * @return mixed `null` if no data is present
	 */
	public function getData() {
		if ($this->data !== null) {
			return $data;
		} elseif (is_array($this->environment)) {
			$loc =& $this->environment;
			foreach ($this->path as $key) {
				if (isset($loc[$key])) {
					$loc =& $loc[$key];
				} else {
					return null;
				}
			}
			return $loc;
		}
		
		return null;
	}
}