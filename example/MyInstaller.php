<?php

/* help ourselves to the Composer autoloader... */
require_once preg_replace('%(.*)/vendor/battis/batch-action%', '$1/vendor/autoload.php', dirname(__DIR__));

class MyInstaller extends Battis\BatchManager {

	/**
	 * Example DATABASE batch step
	 *
	 * @param int $step
	 *        	Which DATABASE step is this in sequence (if there are more
	 *        	than one)?
	 */
	protected function DATABASE($step) {

		$this->prerequisite(Batch::SCRIPT(), 0);
		$this->log->log(
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
	 * @param int $step
	 *        	Which FILES step is this in sequence (if there are more than
	 *        	one)?
	 */
	protected function FILES($step) {

		$this->log->log(
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
				$this->log->log(__FUNCTION__ . "($step) Loading secrets.xml...");
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

$installer = MyInstaller::getInstance();
$installer->run();

?>