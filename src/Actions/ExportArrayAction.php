<?php
	
namespace Battis\BatchAction\Actions;

use Battis\BatchAction\Action;
use Battis\BatchAction\Action_Exception;
use Battis\BatchAction\Result;
use Battis\BatchAction\Sandbox\SandboxReplaceableData;

/**
 * Export a portion of the BatchManager execution environment's sandbox variables as an associative array.
 * 
 * @author Seth Battis <seth@battis.net>
 */
class ExportArrayAction extends Action {
	
	/** @var SandboxReplaceableData Path to the sandbox data to be exported */
	protected $path;
	
	/**
	 * {@inheritDoc}
	 * 
	 * {@inheritDoc}
	 *
	 * @param string|SandboxReplaceableData $path The path to the data within
	 *		the sandbox (e.g. `'/config/secrets.xml'`)
	 * @param array $prerequisites {@inheritDoc}
	 * @param array $tags {@inheritDoc}
	 */
	public function __construct($path, $prerequisites = array(), $tags = array()) {
		parent::__construct($prerequisites, $tags);
		$this->setPath($path);
	}
	
	/**
	 * Overrideable method to process `$path` parameter of `__construct()`
	 * 
	 * @param string|SandboxReplaceableData $path The path to the data within
	 *		the sandbox (e.g. `'/config/secrets.xml'`)
	 *
	 * @return SandboxReplaceableData
	 */
	protected function setPath($path) {
		if (is_string($path)) {
			$this->path = new SandboxReplaceableData($path);
		} elseif (is_a($path, SandboxReplaceableData::class)) {
			$this->path = $path;
		} else {
			throw new Action_Exception(
				'Expected a sandbox path string or an instance of `SandboxReplaceableData`, received `' . get_class($path) . '` instead.',
				Action_Exception::PARAMETER_MISMATCH
			);
		}
		
		return $this->path;
	}
	
	
	/**
	 * {@inheritDoc}
	 *
	 * {@inheritDoc} The data requested for export is returned in the data element
	 *		of the `Result`.
	 * 
	 * @param array $environment {@inheritDoc}
	 *
	 * @return Result {@inheritDoc}
	 */
	public function act(array &$environment) {
		return new Result(
			get_class($this),
			'Stored Configuration in Array',
			'Saved the configuration `' . $this->path->getPath() . '` as an associative array.',
			Result::SUCCESS,
			true,
			$this->path->getData($environment)
		);
	}
}