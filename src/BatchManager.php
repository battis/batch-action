<?php

/** BatchManager class */
namespace Battis\BatchAction;

use \Log;

/**
 * A self-running object to run categorized batches of scripts (like, for an
 * install)
 *
 * @author Seth Battis <seth@battis.net>
 */
class BatchManager {

	const NO_RESULT = '@@no_result@@';

	/** @var Batch[] action sequence */
	private $sequence;

	/**
	 *
	 * @var array Results of action sequence, stored by batch and step. To
	 *      access, for example, step 1 of the SCRIPT batch:
	 *      $this->results['SCRIPT'][1]
	 */
	private $result = false;

	/** @var Log Log file handle */
	protected $logger;

	/** @var BatchManager The singleton instance of BatchManager */
	private static $singleton;

	/**
	 * Get a singleton instance of BatchManager
	 *
	 * @param Batch[] $sequence
	 *        	(Optional) An array specifying the order of the action
	 *        	sequence (default defined in `initializeSequence()`)
	 * @see BatchManager::initializeSequence()
	 */
	public static function getInstance($sequence = null) {

		if (static::$singleton === null) {
			static::$singleton = new static($sequence);
		}
		return static::$singleton;
	}

	/**
	 * Construct an BatchManager
	 *
	 * As a singleton class, constructors are not directly accessible. Use
	 * `BatchManager::getInstance()` instead.
	 *
	 * @param Batch[] $sequence        	
	 *
	 * @uses BatchManager::initializeBatches()
	 * @uses BatchManager::initializeLogger()
	 *      
	 * @see BatchManager::getInstance() getInstance()
	 */
	protected function __construct($sequence = null) {

		$this->initializeLogger();
		$this->initializeSequence($sequence);
		$this->run();
	}

	/**
	 * As a singleton class, constructors are not directly accessible.
	 * Use `BatchManager::getInstance()` instead.
	 *
	 * @see BatchManager::getInstance() getInstance()
	 */
	private function __wakeup() {
}

	/**
	 * As a singleton class, constructors are not directly accessible.
	 * Use `BatchManager::getInstance()` instead.
	 *
	 * @see BatchManager::getInstance() getInstance()
	 */
	private function __clone() {
}

	/**
	 * Initialize the action sequence to default value
	 *
	 * Arbitrarily, the default sequence is `[ Batch::SCRIPT(),
	 * Batch::DATABASE(), Batch::FILES(), Batch::SCRIPT() ]`
	 *
	 * @param Batch[] $sequence
	 *        	(Optional) User-specified sequence passed to `getInstance()`
	 *        	(will override default)
	 */
	protected function initializeSequence($sequence = null) {

		if (is_array($sequence)) {
			$sequence = array_filter($sequence, 
				function ($elt) {
					return $elt instanceof Batch;
				});
			if (!empty($sequence)) {
				$this->sequence = $sequence;
			}
		}
		
		if (empty($this->$sequence)) {
			$this->sequence = array(
				Batch::SCRIPT(),
				Batch::DATABASE(),
				Batch::FILES(),
				Batch::SCRIPT() 
			);
		}
	}

	/**
	 * Initialize the logger for the BatchManager
	 *
	 * Called by the constructor, available to override if the default option
	 * (a log file in the root of the BatchManager directory for each subclass
	 * of BatchManager instantiated) is undesireable.
	 *
	 * @uses BatchManager::getLogFilePath()
	 */
	protected function initializeLogger() {

		$this->logger = Log::singleton('file', $this->getLogFilePath());
	}

	/**
	 * Path to the log file for this BatchManager instance
	 *
	 * Log files are stored at the root of the BatchManager directory. One log
	 * file is generated for each subclass of BatchManager instantiated.
	 *
	 * @return string
	 */
	protected function getLogFilePath() {

		return __DIR__ . '/../' . str_replace(__NAMESPACE__ . '\\', '', 
			__CLASS__) . '.log';
	}

	/**
	 * Path to the current app
	 *
	 * If installed via [Composer](http://getcomposer.org), returns the parent
	 * directory of the `vendor` directory, otherwise returns the parent
	 * directory of `src` directory.
	 *
	 * @return string
	 */
	protected function getAppPath() {

		return preg_replace('%(.*)(/vendor/battis/batch-action)?/src%', '$1', 
			__DIR__);
	}

	/**
	 * URL of the current app
	 *
	 * If installed via [Composer](http://getcomposer.org), returns the URL of
	 * the parent directory of the `vendor` directory, otherwise returns the
	 * URL of the parent directory of `src` directory.
	 *
	 * @return string|boolean URL (or `FALSE` if `$_SERVER` is unavailable, as
	 *         when run from command line)
	 *        
	 * @uses BatchManager::getAppPath()
	 */
	protected function getAppUrl() {

		if (isset($_SERVER)) {
			return (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on' ? 'http://' : 'https://') . $_SERVER['SERVER_NAME'] . preg_replace(
				"|^{$_SERVER['DOCUMENT_ROOT']}(.*)$|", '$1', 
				$this->getAppPath());
		}
		return false;
	}

	/**
	 * Run this BatchManager's action sequence
	 *
	 * @param boolean $force
	 *        	(Optional) If already run, force a run (default `FALSE`)
	 * @param Batch|Batch[]|array $filter
	 *        	(Optional) Filter batch sequence by batch and also by
	 *        	filter. Pass a single Batch to filter for only that Batch.
	 *        	Pass an array of Batches to filter for all of those Batches.
	 *        	Pass an associative array keyed with Batch(es) addressing
	 *        	integer values or arrays of integer values filtering steps.
	 *        	
	 * @throws Exception INVALID_BATCH if `$filter` is not (an) instance(s) of
	 *         Batch
	 *        
	 * @uses BatchManager::hasRun() to automagically decide if the
	 *       BatchManager has already been run
	 */
	public function run($force = false, Filter $filter = null) {

		if ($force || !$this->hasRun()) {
			$this->logger->log('Run beginning...');
			$this->result = array();
			if ($force && $this->hasRun()) {
				$this->logger->log('Forced run');
			}
			$counter = array_fill_keys(Batch::keys(), 0);
			foreach ($this->sequence as $batch) {
				$key = $batch->getKey();
				if ($filter === null || $filter->match($batch, $counter[$key])) {
					if (isset($this->result[$key][$counter[$key]])) {
						throw new Exception(__METHOD__ . ": $key({$counter[$key]}) has been run prematurely", Exception::EXECUTION_OUT_OF_ORDER);
					}
					$this->result[$key][$counter[$key]] = $this->$key(
						$counter[$key]);
				}
				$counter[$key] ++;
			}
			$this->logger->log('...run ended.');
		}
	}

	/**
	 * Has this BatchManager already been run?
	 *
	 * By default, a BatchManager will write to a log file and will treat the
	 * existence of that log file as definitive evidence that a run has
	 * already taken place. Classes that extend BatchManager are strongly
	 * encouraged to employ stronger verification techniques (for example
	 * testing that a database table exists and is properly populated).
	 *
	 * This method is used by `BatchManager::run()` to determine if a run
	 * should be started.
	 *
	 * @return boolean `TRUE` if a prior run is detected, `FALSE` otherwise
	 *        
	 * @uses BatchManager::getLogFilePath()
	 */
	public function hasRun() {

		return file_exists($this->getLogFilePath());
	}

	/**
	 * Get the result of the most recent run (if any)
	 *
	 * Be aware that this may return `FALSE` meaning that there is no recent
	 * run (or that a filter was unmatched) _or_ that the value stored for a
	 * particular result is false.
	 *
	 * @param Batch $batch
	 *        	(Optional) Filter results by a specific batch
	 * @param int $step
	 *        	(Optional, requires `$batch`) Filter results of a batch for
	 *        	a specific step
	 * @return mixed The most recent result (or `BatchManager::NO_RESULT` if
	 *         no action sequence has been run or the batch and/or step filter
	 *         is unmatched)
	 */
	public function getResult(Batch $batch = null, $step = false) {

		if (!$batch && !$step) {
			return $this->result;
		} elseif ($batch) {
			$key = $batch->getKey();
			if ($step !== false && isset($this->result[$key]) && isset(
				$this->result[$key][$step])) {
				return $this->result[$key][$step];
			} elseif (isset($this->result[$key])) {
				return $this->result[$key];
			}
		}
		
		return self::NO_RESULT;
	}

	/**
	 * Require a particular step in the sequence to be run as a prerequisite
	 * for another
	 * 
	 * @param Batch $batch        	
	 * @param int $step        	
	 * @throws Exception INVALID_STEP If the step passed is not an integer
	 * @return boolean 'TRUE' if the prerequiste step has been run already,
	 *         'FALSE' if it was run as a result of this prerequisite
	 */
	protected function prerequisite(Batch $batch, $step) {

		$key = $batch->getKey();
		if (is_int($step)) {
			if (isset($this->result[$key][$step])) {
				return true;
			} else {
				$this->logger->log("Running $key({$step}) as a prerequisite");
				$this->result[$key][$step] = $this->$key($step);
				return false;
			}
		} else {
			throw new Exception(__METHOD__ . ': Expected integer step ID', Exception::INVALID_STEP);
		}
	}

	/**
	 * Example DATABASE batch step
	 *
	 * Override for custom behavior
	 *
	 * @param int $step
	 *        	Which DATABASE step is this in sequence (if there are more
	 *        	than one)?
	 */
	protected function DATABASE($step) {

		$this->prerequisite(Batch::SCRIPT(), 0);
		$this->logger->log(
			__FUNCTION__ . "($step) Creating database tables...");
		$mysql = $this->getResult(Batch::SCRIPT(), 0)['mysql'];
		$sql = new \mysqli($mysql['host'], $mysql['username'], $mysql['password'], $mysql['database']);
		return Action::createDatabaseTables($sql, 
			$this->getAppPath() . '/schema.sql', 
			$this->getAppPath() . '/test.sql');
	}

	/**
	 * Example FILES batch step
	 *
	 * Override for custom behavior
	 *
	 * @param int $step
	 *        	Which FILES step is this in sequence (if there are more than
	 *        	one)?
	 */
	protected function FILES($step) {

		$this->logger->log(
			__FUNCTION__ . "($step) Creating admin directory...");
		if (!is_dir($this->getAppPath() . '/admin')) {
			mkdir($this->getAppPath() . '/admin', 0770);
		}
		if (!is_file($this->getAppPath() . '/admin/index.html')) {
			file_put_contents($this->getAppPath() . '/admin/index.html', 
				<<<HTML
<html>
	<head>
		<title>Auto-generated Admin Page</title>
	</head>
	<body>
		<h1>Auto-generated Admin Page</h1>
		<p>Success! If this page is password-protected (and exists), it means that the batch script ran mostly correctly!</p>
	</body>
</html>
HTML
);
		}
		return $this->getAppUrl() . '/admin';
	}

	/**
	 * Example SCRIPT batch step
	 *
	 * Override for custom behavior
	 *
	 * @param int $step
	 *        	Which SCRIPT step is this in sequence (if there are more
	 *        	than one)?
	 */
	protected function SCRIPT($step) {

		/*
		 * One possibility is that a particular batch might be called multiple
		 * times, each time with a different $step parameter (sequential,
		 * starting at 0) that can be used to route to different behaviors at
		 * each step of the batch
		 */
		switch ($step) {
			case 0:
				/* 
				 * Load configuration information from an XML file and store
				 * in $this->results for other batches/steps to access
				 */
				$this->logger->log(__FUNCTION__ . "($step) Loading secrets.xml...");
				return Action::loadXmlAsArray(
					$this->getAppPath() . '/secrets.xml');
			case 1:
				/*
				 * Set up HTTP basic web authentication on a directory (using
				 * previously loaded configuration information)
				 */
				$this->prerequisite(Batch::SCRIPT(), 0);
				$this->logger->log(
					__FUNCTION__ . "($step) Password protecting admin directory...");
				$admin = $this->getResult(Batch::SCRIPT(), 0)['admin'];
				return Action::httpAuthDir($this->getAppPath() . '/admin', 
					array(
						$admin['username'] => $admin['password'] 
					));
		}
	}
}

?>