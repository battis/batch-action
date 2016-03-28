<?php
	
namespace Battis\BatchAction\Actions;

use Battis\BatchAction\Action;
use Battis\BatchAction\Action_Exception;
use Battis\BatchAction\Result;
use Battis\BatchAction\Sandbox\SandboxReplaceableData;
use mysqli;

/**
 * {@inheritDoc} Extended to allow for configuration based on previously-loaded
 * configuration information in the BatchManager execution environment sandbox.
 * 
 * @author Seth Battis <seth@battis.net>
 */
class ImportMySQLSchemaConfigurableAction extends ImportMySQLSchemaAction {

	// TODO make these configurable
	const HOST = 'host';
	const USER = 'username';
	const PASSWORD = 'password';
	const DATABASE = 'database';
	const SCHEMA = 'schema';
		
	/**
	 * {@inheritDoc}
	 *
	 * {@inheritDoc}
	 * 
	 * @param \mysqli|SandboxReplaceableData $mysqli {@inheritDoc}
	 *
	 * @return \mysqli|SandboxReplaceableData {@inheritDoc}
	 */
	protected function setMySQLi($mysqli) {
		if (is_a($mysqli, SandboxReplaceableData::class)) {
			$this->sql = $mysqli;
		} else {
			return parent::setMySQLi($mysqli);
		}
	}
	
	/**
	 * {@inheritDoc}
	 *
	 * {@inheritDoc}
	 * 
	 * @param string $schema {@inheritDoc}
	 *
	 * @return string {@inheritDoc}
	 */
	protected function setSchema($schema) {
		if (empty($schema) && is_a($this->sql, SandboxReplaceableData::class)) {
			$this->schema = null;			
		} else {
			return parent::setSchema($schema);
		}
	}
	
	/**
	 * {@inheritDoc}
	 *
	 * {@inheritDoc}
	 * 
	 * @param array $environment {@inheritDoc}
	 *
	 * @return boolean
	 */
	public function testSandbox(array &$environment) {
		if ($this->sql instanceof mysqli) {
			return true;
		} elseif (is_a($this->sql, SandboxReplaceableData::class)) {
			$connections = $this->sql->getData($environment);
			foreach($connections as $connection) {
				if (
					empty($connection[self::HOST]) ||
					empty($connection[self::USER]) ||
					empty($connection[self::PASSWORD]) ||
					empty($connection[self::DATABASE]) ||
					(empty($this->schema) && empty($connection[self::SCHEMA]))
				) {
					return false;
				}
			}
			return true;
		}
		
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * {@inheritDoc}
	 * 
	 * @param array $environment {@inheritDoc}
	 *
	 * @return Result|Result[] {@inheritDoc}
	 */
	public function act(array &$environment) {
		if (is_a($this->sql, SandboxReplaceableData::class)) {
			$connections = $this->sql->getData($environment);
			$results[] = array();
			// FIXME this assumes CxmlRD list, rather than a simple SRD... how to reconcile?
			foreach ($connections as $connection) {
				$sql = new mysqli(
					$connection[self::HOST],
					$connection[self::USER],
					$connection[self::PASSWORD],
					$connection[self::DATABASE]
				);
				if ($sql->connect_error !== null) {
					throw new Action_Exception(
						"MySQL connection error {$sql->connect_errno} connecting to host `{$connection[self::HOST]}` as `{$connection['self::USER']}`: {$sql->connect_error}",
						Action_Exception::ACTION_FAILED 
					);
				}

				$results[] = $this->ImportMySQLSchema(
					$sql,
					(empty($this->schema) ?
						$connection[self::SCHEMA] :
						$this->schema
					)
				);
			}
			return $results;
		} else {
			return parent::act($environment);
		}
	}
}