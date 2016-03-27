<?php
	
namespace Battis\BatchAction\Actions;

use Battis\BatchAction\Action;
use Battis\BatchAction\Action_Exception;
use Battis\BatchAction\Result;
use Battis\BatchAction\Sandbox\SandboxReplaceableData;
use mysqli;

/**
 * Import a MySQL schema (or schemas) into a MySQL database (or databases)
 * 
 * @author Seth Battis <seth@battis.net>
 */
class ImportMySQLSchemaAction extends Action {

	const SANDBOX_PATH_DELIMITER = '/';
		
	const HOST = 'host';
	const USER = 'username';
	const PASSWORD = 'password';
	const DATABASE = 'database';
	const SCHEMA = 'schema';

	/** @var string The schema to be imported */
	private $schema = null;
	
	/** @var \mysqli|SandboxReplaceableData MySQL connection information*/
	private $sql;
	
	/**
	 * Construct an Action
	 * 
	 * If any schema or MySQL connection data must be loaded from the sandbox, make
	 * sure that that data is present. Data is expected in the format:
	 *
	 * ```XML
	 * <mysql>
	 *		<host>locahlost</host>
	 *		<username>admin</username>
	 *		<password>s00p3rs3kr3t</password>
	 *		<database>test</database>
	 *		<schema>~/schemas/myschema.sql</schema>
	 * </mysql>
	 * ```
	 *
	 * where the `<mysql>` tag is specified by the `$xpath` parameter (it could be
	 * anything, it doesn't have to be `mysql`), and the remaining tags are
	 * specified by the constants `ImportMySQLSchemaAction::HOST`,
	 * `ImportMySQLSchemaAction::USER`, `ImportMySQLSchemaAction::PASSWORD`,
	 * `ImportMySQLSchemaAction::DATABASE`, `ImportMySQLSchemaAction::SCHEMA`. If a
	 * literal schema string or a path to a schema file has been provided by the
	 * `$schema` parameter, then the `<schema>` tag will be ignored.
	 *
	 * @param \mysqli|SandboxReplaceableData $mysqli Either a `mysqli` connection
	 *		instance or a `SandboxReplaceableData` reference to a pre-loaded
	 *		configuration.
	 * @param string $schema Either a literal MySQL schema query, the path to a
	 *		file containing a MySQL schema query, or `null`. If `null`, then `$mysqli`
	 *		_must_ be an instance of `SandboxReplaceableData` and there _must_ be a
	 *		literal schema string or a path to a schema file stored in the
	 *		`ImportMySQLSchemaAction::SCHEMA` key for each MySQL configuration
	 *		(default: `null`)
	 * @param Action|Action[] $prerequisites {@inheritDoc}
	 * @param string|string[] $tags {@inheritDoc}
	 */
	public function __construct($mysqli, $schema = null, $prerequisites = array(), $tags = array()) {
		
		parent::__construct($prerequisites, $tags);
		
		if ($mysqli instanceof mysqli || is_a($mysqli, SandboxReplaceableData::class)) {
			$this->sql = $mysqli;
		} else {
			throw new Action_Exception(
				"Expected an instance of `mysqli` or an instance of `{SandboxReplaceableData::class}`, received `" . get_class($mysqli) . '` instead.',
				Action_Exception::PARAMETER_MISMATCH
			);
		}

		if (is_string($schema) || is_a($schema, SandboxReplaceableData::class)) {
			$this->schema = $schema;
		} elseif (empty($schema) && !is_a($mysqli, SandboxReplaceableData::class)) {
			throw new Action_Exception(
				'Empty schema value requires that `$mysqli` be an instance of `SandboxReplaceableData`',
				Action_Exception::PARAMETER_MISMATCH
			);
		} else {
			throw new Action_Exception(
				'Expected a string, file path, instance of `SandboxReplaceableData` or null for schema, received `' . get_class($schema) . '` instead.',
				Action_Exception::PARAMETER_MISMATCH
			);
		}
	}
	
	/**
	 * Test for preloaded configuration (if necessary)
	 *
	 * @see ImportMySQLSchemaAction::__construct() __construct() describes valid configuration details.
	 * 
	 * @param array $environment {@inheritDoc}
	 * @return void
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
	 * Load MySQL schema into servers
	 *
	 * Either load one schema int to a single database, or one schema on to many
	 * databases, or many schema into many databases.
	 * 
	 * @param array $environment {@inheritDoc}
	 *
	 * @return Result
	 */
	public function act(array &$environment) {
		$sql = $this->sql;
		$schema = $this->schema;
		$messages = array();
		if (is_a($sql, SandboxReplaceableData::class)) {
			$connections = $sql->getData($environment);
			foreach ($connections as $connection) {
				$sql = new mysqli(
					$connection[self::HOST],
					$connection[self::USER],
					$connection[self::PASSWORD],
					$connection[self::DATABASE]
				);
				if ($sql->connect_error !== null) {
					throw new Action_Exception(
						'MySQL connection error ' . $sql->connect_errno . ': ' . $sql->connect_error,
						Action_Exception::ACTION_FAILED 
					);
				}
				if (empty($this->schema)) {
					$schema = $connection[self::SCHEMA];
				}
				$messages[] = static::loadSchema($sql, $schema);
			}
		} else {
			$messages[] = static::loadSchema($sql, $schema);
		}
		
		return new Result(
			get_class($this),
			'SQL schema loaded',
			implode("\n", $messages),
			Result::SUCCESS
		);
	}
	
	/**
	 * Load a schema into a database
	 * 
	 * @param \mysqli $sql A `mysqli` connection instance for the database
	 * @param string $schemaStringOrFilePath Either a literal MySQL schema query
	 *		string to the path to a file containing a MySQL schema query
	 *
	 * @return string A human-readable message describing the schema loading
	 */
	private static function loadSchema(\mysqli $sql, $schemaStringOrFilePath) {
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
		
		return (realpath($schemaStringOrFilePath) ? "Schema file `$schemaStringOrFilePath`" : 'Schema') . ' loaded into ' . $sql->host_info;
	}
}