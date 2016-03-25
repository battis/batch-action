<?php
	
namespace Battis\BatchAction;

use DOMDocument;
use DOMXPath;

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
	 * @var \DOMXPath Location of the MySQL configuration information in imported
	 *		XML configuration (if present)
	 */
	private $xpath;

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
	 * @param string $schema Either a literal MySQL schema query, the path to a
	 *		file containing a MySQL schema query, or `null`. If `null`, then `$mysqli`
	 *		_must_ be an instance of `SandboxReplaceableData` and there must be a
	 *		literal schema string or a path to a schema file stored in the
	 *		`ImportMySQLSchemaAction::SCHEMA` key for each MySQL configuration
	 *		(default: `null`)
	 * @param \mysqli|SandboxReplaceableData $mysqli Either a `mysqli` connection
	 *		instance or a `SandboxReplaceableData` reference to a pre-loaded
	 *		configuration.
	 * @param string $xpath If either `$schema` is `null` or `$mysqli` is an
	 *		instance of `SandboxReplaceableData`, then `$xpath` must be a valid XPath
	 *		string to list all MySQL configurations in the configuration (default:
	 *		`null`)
	 * @param array $prerequisites {@inheritDoc}
	 * @param array $tags {@inheritDoc}
	 */
	public function __construct(string $schema = null, $mysqli, string $xpath = null, $prerequisites = array(), $tags = array()) {
		
		parent::__construct($prerequisites, $tags);
		
		if (is_string($schema)) {
			$this->$schema = $schema;
		} elseif (empty($schema) && !($mysqli instanceof SandboxReplaceableData)) {
			throw new Action_Exception(
				'Empty schema value requires that MySQL be configured through sanxbox',
				Action_Exception::PARAMETER_MISMATCH
			);
		} else {
			throw new Action_Exception(
				'Expected a string, file path or null for schema, received `' . get_class($schema) . '` instead.',
				Action_Exception::PARAMETER_MISMATCH
			);
		}
		
		if ($mysqli instanceof \mysqli || ($mysqli instanceof SandboxReplaceableData && $mysqli->getType() === 'mysqli')) {
			$this->sql = $mysqli;
			if ($mysqli instanceof SandboxReplaceableData && !empty($xpath)) {
				$this->xpath = $xpath;
			} else {
				throw new Action_Exception(
					'Expected an XPath string, but received no data',
					Action_Exception::PARAMETER_MISMATCH
				);
			}
		} else {
			throw new Action_Exception(
				'Expected an instance of `mysqli` or an instance of `SandboxReplaceableData` of type `mysqli`, received `' . get_class($mysqli) . '` instead.',
				Action_Exception::PARAMETER_MISMATCH
			);
		}
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
		if ($sql instanceof SandboxReplaceableData) {
			$sql->setSandbox($environment);
			$xpath = new DOMXPath($sql->getData());
			foreach ($xpath->query($this->xpath) as $node) {
				$sql = new mysqli(
					$xpath->query("/{self::HOST}", $node)->item(0)->textContent,
					$xpath->query("/{self::USER}", $node)->item(0)->textContent,
					$xpath->query("/{self::PASSWORD}", $node)->item(0)->textContent,
					$xpath->query("/{self::DATABASE}", $node)->item(0)->textContent
				);
				if (empty($this->schema)) {
					$schema = $xpath("/{self::SCHEMA}", $node)->item(0)->textContent;
				}
				$messages[] = static::loadSchema($sql, $schema);
			}
		} else {
			$messages[] = static::loadSchema($sql, $schema);
		}
		
		return new Result(
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
	private static function loadSchema(\mysqli $sql, string $schemaStringOrFilePath) {
		/* load schema into a string for processing */
		$schema = $schemaStringOrFilePath;
		if (realpath($schemaStringOrFilePath)) {
			$schema = file_get_contents($schemaStringOrFilePath);
		}

		/* run queries one at a time */		
		$queries = explode(';', $schema);
		foreach ($queries as $query) {
			if (!$sql->query($query)) {
				throw new Action_Exception(
					__METHOD__ . " MySQL error: " . $sql->error,
					Action_Exception::ACTION_FAILED);
			}
		}
		
		return (realpath($schemaStringOrFilePath) ? "Schema file `$schemaStringOrFilePath`" : 'Schema') . ' loaded into ' . $sql->host_info();
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
		if ($this->sql instanceof \mysqli) {
			return true;
		} elseif ($this->sql instanceof SandboxReplaceableData) {
			$sql = $this->sql;
			$sql->setSandbox($environment);
			$xpath = new DOMXPath($sql->getData());
			foreach($xpath->query($this->xpath) as $node) {
				if (
					$xpath->query("/{self::HOST}", $node)->length == 0 ||
					$xpath->query("/{self::USER}", $node)->length == 0 ||
					$xpath->query("/{self::PASSWORD}", $node)->length == 0 ||
					$xpath->query("/{self::DATABASE}", $node)->length == 0 ||
					(empty($this->schema) && $xpath->query("/{self::SCHEMA}", $node)->length == 0)
				) {
					return false;
				}
			}
			return true;
		}
		
		return false;
	}
}