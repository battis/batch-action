<?php
	
namespace Battis\BatchAction\Sandbox;

use Battis\BatchAction\Sandbox_Exception;
use DOMDocument;
use DOMXPath;
use DOMNode;

/**
 * {@inheritDoc} Extended to treat the data stored in the Sandbox as a
 * `DOMDocument`, and to extract further data from that DOM via XPath, converting
 * the DOM into an associative array.
 * 
 * @author Seth Battis <seth@battis.net>
 */
class ConfigXMLReplaceableData extends SandboxReplaceableData {
	
	/** @var string XPath query string */
	private $xpath;
	
	/**
	 * {@inheritDoc}
	 * 
	 * @param string $path {@inheritDoc}
	 * @param string $xpath A valid XPath query string to locate the data within the XML configuration
	 * @param mixed $data {@inheritDoc}
	 * @return void
	 */
	public function __construct($path, $xpath, $data = null) {
		parent::__construct($path, $data);
		
		if (!empty((string) $xpath)) {
			$this->xpath = $xpath;
		} else {
			throw new Sandbox_Exception(
				'Expected a non-empty XPath query string, received ' . (is_string($xpath) ? ' empty string' : ' `' . get_class($xpath) . '`') . ' instead.',
				Sandbox_Exception::PARAMETER_MISMATCH
			);
		}
	}
		
	/**
	 * {@inheritDoc}
	 *
	 * {@inheritDoc} If the XPath query yields the root of a subtree of nodes in
	 * the DOM, that subtree will be converted into an associative array. For
	 * example, given an XPath of `'mysql'` and the following XML:
	 *
	 * ```XML
	 * <secrets>
	 *		<mysql>foobar</mysql>
	 *		<mysql>
	 *			<host>localhost</host>
	 *			<username>root</host>
	 *			<password>s00perS3kr3t</password>
	 *			<database>test</database>
	 *		</mysql>
	 * </secrets>
	 * ```
	 *
	 * will return:
	 *
	 * ```
	 * array(2) {
	 *		[0]=>
	 *		string(6) "foobar"
	 *		[1]=>
	 *		array(4) {
	 *			["host"]=>
	 *			string(9) "localhost"
	 *			["username"]=>
	 *			string(4) "root"
	 *			["password"]=>
	 *			string(12) "s00perS3kr3t"
	 *			["database"]=>
	 *			string(4) "test"
	 *		}
	 * }
	 * ```
	 *
	 * **Nota bene:** this will return a **list** of data, rather than a singular
	 * object (unless no data is found, in which case the placeholder data will be
	 * returned).
	 * 
	 * @param array $environment {@inheritDoc}
	 *
	 * @return mixed {@inheritDoc}
	 */
	public function getData(array &$environment = null) {
		$dom = parent::getData($environment);
		if ($dom instanceof DOMDocument) {
			$xpath = new DOMXPath($dom);
			$nodes = $xpath->query($this->xpath);
			if ($nodes->length > 0) {
				$result = array();
				foreach ($nodes as $node) {
					$array = json_decode(
						json_encode(
							simplexml_load_string(
								$dom->saveXML($node)
							)
						),
						true
					);
					if (sizeof($array) == 1 && array_key_exists(0, $array)) {
						$result[] = $array[0];
					} else {
						$result[] = $array;
					}
				}
				return $result;
			} else {
				return $this->data;
			}
		} else {
			throw new Sandbox_Exception(
				'Sandbox data at `/' . implode('/', $this->path) . '` must be an instance of `DOMDocument`, found `' . get_class($xml) . '` instead.',
				Sandbox_Exception::PARAMETER_MISMATCH
			);
		}
	}
}