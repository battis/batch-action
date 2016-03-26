<?php
	
namespace Battis\BatchAction\Actions;

use Battis\BatchAction\Action;
use Battis\BatchAction\Action_Exception;
use Battis\BatchAction\Result;
use DOMDocument;

/**
 * Import a configuration XML document into the BatchAdmin execution environment
 * sandbox of variables as a DOMDocument
 * 
 * @author Seth Battis <seth@battis.net>
 */
class ImportConfigXMLAction extends Action {

	const CONFIG = 'config';
	
	/**
	 * @var string Either a literal XML configuration string or the path to an XML
	 *		file
	 */
	private $xmlStringOrFilePath = null;
	
	/**
	 * Construct an Action
	 * 
	 * @param string $xmlStringOrFilePath Either a literal XML configuration string
	 *		or the path to an XML file
	 * @param array $prerequisites (default: array())
	 * @param array $tags (default: array())
	 * @return void
	 */
	public function __construct($xmlStringOrFilePath, $prerequisites = array(), $tags = array()) {
		parent::__construct($prerequisites, $tags);
		$this->xmlStringOrFilePath = $xmlStringOrFilePath;
	}
	
	/**
	 * Import the configuration XML
	 * 
	 * The configuration is stored as a `DOMDocument` in the BatchManager execution
	 * environment: `$environment[ImportConfigXMLAction::CONFIG]`
	 *
	 * @param array $environment {@inheritDoc}
	 *
	 * @return Result
	 */
	public function act(array &$environment) {
		/* load XML into a string for processing */
		$xml = $this->xmlStringOrFilePath;
		if (realpath($this->xmlStringOrFilePath)) {
			$xml = file_get_contents($this->xmlStringOrFilePath);
		}
		
		// FIXME make sure we've got some content in $xml!
		
		$dom = DOMDocument::loadXML($xml);
		if (realpath($this->xmlStringOrFilePath)) {
			$environment[self::CONFIG][basename($this->xmlStringOrFilePath)] = $dom;
		} else {
			$environment[self::CONFIG][] = $dom;
		}
		
		return new Result(
			'Configuration loaded',
			'A configuration has been loaded' . (realpath($this->xmlStringOrFilePath) ? " from the file `{$this->xmlStringOrFilePath}`" : '') . ' into the installer sandbox'
		);
	}
}