<?php

/** Step class */
namespace Battis\BatchAction;

/**
 * An enumerated class to describe install steps
 *
 * @author Seth Battis <seth@battis.net>
 *        
 */
class Batch extends \MyCLabs\Enum\Enum {

	const DATABASE = 'Database';

	const SCRIPT = 'Script';

	const FILES = 'Files';
}

?>