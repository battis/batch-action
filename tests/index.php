<?php
require_once (__DIR__ . '/../vendor/autoload.php');

use Battis\BatchAction\BatchManager;
use Battis\BatchAction\Batch;
use Battis\BatchAction\Filter;

date_default_timezone_set('America/New_York');
try {
	$installer = BatchManager::getInstance();
	$installer->run(true);
	$installer->run(true, (new Filter())->add(Batch::SCRIPT()));
	$installer->run(true, (new Filter())->add(Batch::SCRIPT(), 1));
	$installer->run(true, 
		(new Filter())->add(Batch::SCRIPT(), 1)->add(Batch::DATABASE()));
} catch (Battis\BatchAction\Exception $e) {
	echo '<p>' . $e->getMessage() . '</p>';
}

?>
<pre><?= file_get_contents('BatchManager.log') ?></pre>