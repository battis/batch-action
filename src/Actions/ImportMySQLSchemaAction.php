<?php
	
namespace Battis\BatchAction\Actions;

use Battis\BatchAction\Action;
use Battis\BatchAction\Action_Exception;
use Battis\BatchAction\Result;
use mysqli;

/**
 * Import a MySQL schema into a MySQL database.
 * 
 * @author Seth Battis <seth@battis.net>
 */
class ImportMySQLSchemaAction extends Action {

	/** @var string The schema to be imported */
	protected $schema = null;
	
	/** @var \mysqli MySQL connection information */
	protected $sql;
	
	/**
	 * {@inheritDoc}
	 *
	 * {@inheritDoc}
	 * 
	 * @param \mysqli $mysqli MySQL connection information
	 * @param string $schema A schema file path string or a literal MySQL schema
	 *		query string.
	 * @param array $prerequisites {@inheritDoc}
	 * @param array $tags {@inheritDoc}
	 */
	public function __construct($mysqli, $schema, $prerequisites = array(), $tags = array()) {
		parent::__construct($prerequisites, $tags);
		$this->setMySQLi($mysqli);
		$this->setSchema($schema);
	}
	
	/**
	 * Overrideable method to process `$mysqli` parameter of `__construct()`
	 * 
	 * @param \mysqli $mysqli MySQL connection information
	 *
	 * @return \mysqli
	 */
	protected function setMySQLi($mysqli) {
		if ($mysqli instanceof mysqli) {
			$this->sql = $mysqli;
		} else {
			throw new Action_Exception(
				"Expected an instance of `mysqli`, received `" . get_class($mysqli) . '` instead.',
				Action_Exception::PARAMETER_MISMATCH
			);
		}
		
		return $this->sql;
	}
	
	/**
	 * Overrideable method to process `$schema` parameter of `__construct()`
	 * 
	 * @param string $schema A schema file path string or a literal MySQL schema
	 *		query string.
	 *
	 * @return string
	 */
	protected function setSchema($schema) {
		if (is_string($schema) && !empty($schema)) {
			$this->schema = $schema;
		} else {
			throw new Action_Exception(
				'Expected a non-empty file path string or schema string, received `' . get_class($schema) . '` instead.',
				Action_Exception::PARAMETER_MISMATCH
			);
		}
		
		return $this->schema;
	}

	/**
	 * {@inheritDoc}
	 *
	 * {@inheritDoc}
	 * 
	 * @param array $environment {@inheritDoc}
	 *
	 * @return Result {@inheritDoc}
	 */
	public function act(array &$environment) {
		$this->importMySQLSchema($this->sql, $this->schema);
	}
	
	/**
	 * Load a schema into a database
	 * 
	 * @param \mysqli $mysqli MySQL connection information
	 * @param string $schemaStringOrFilePath A schema file path string or a literal
	 *		MySQL schema query string.
	 *
	 * @return Result
	 */
	protected function importMySQLSchema(\mysqli $sql, $schemaStringOrFilePath) {
		/* load schema into a string for processing */
		$schema = $schemaStringOrFilePath;
		if (realpath($schemaStringOrFilePath)) {
			$schema = file_get_contents($schemaStringOrFilePath);
		}

		/* run queries one at a time */		
		$queries = explode(';', $schema);
		foreach ($queries as $query) {
			if (!empty($query)) {
				if ($sql->query($query) === false) {
					throw new Action_Exception(
						__METHOD__ . " MySQL error: " . $sql->error,
						Action_Exception::ACTION_FAILED);
				}
			}
		}
		
		return new Result(
			get_class($this),
			'Schema loaded',
			(realpath($schemaStringOrFilePath) ?
				"Schema file `$schemaStringOrFilePath`" :
				'Schema'
			) . " loaded into {$sql->host_info}",
			Result::SUCCESS
		);
	}
}