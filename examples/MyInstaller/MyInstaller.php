<pre><?php

/* help ourselves to the Composer autoloader... */
require_once str_replace('/vendor/battis/batch-action', '', __DIR__) . '/../../vendor/autoload.php';

use Battis\BatchAction\BatchManager;
use Battis\BatchAction\SandboxReplaceableData;
use Battis\BatchAction\Actions\ImportConfigXMLAction;
use Battis\BatchAction\Actions\ImportMySQLSchemaAction;

$installer = new BatchManager();
$config = new ImportConfigXMLAction(__DIR__ . '/secrets.xml');
$sql = new ImportMySQLSchemaAction(__DIR__ . '/schema.sql', new SandboxReplaceableData('mysqli', '/config/secrets.xml'), '/secrets/mysql', $config);
$installer->addAction($sql);
try {
	$run = $installer->run();		
} catch (Exception $e) {
	var_dump($e->getMessage());
}

?></pre>