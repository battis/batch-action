<?php

/* help ourselves to the Composer autoloader... */
require_once str_replace('/vendor/battis/batch-action', '', __DIR__) . '/../../vendor/autoload.php';

use Battis\BatchAction\BatchManager;
use Battis\BatchAction\Sandbox\ConfigXMLReplaceableData;
use Battis\BatchAction\Actions\ImportConfigXMLAction;
use Battis\BatchAction\Actions\ImportMySQLSchemaAction;
use Battis\BatchAction\Actions\HttpAuthDirectoryAction;

try {
	$installer = new BatchManager();
	$config = new ImportConfigXMLAction(
		__DIR__ . '/secrets.xml'
	);
	$sql = new ImportMySQLSchemaAction(
		new ConfigXMLReplaceableData(
			'/config/secrets.xml',
			'/secrets/mysql'
		),
		__DIR__ . '/schema.sql',
		$config
	);
	$auth = new HttpAuthDirectoryAction(__DIR__, 'seth');
	$installer->addAction($sql);
	$installer->addAction($auth);
	$run = $installer->run();
	echo '<pre>';
	var_dump($run);
	echo '</pre>';
} catch (Exception $e) {
	echo '<dl><dt>';
	printf("%s\n", get_class($e));
	echo '</dt><dd><pre>';
	var_dump($e->getMessage());
	echo '</pre></dd></dl>';
}