<?php

/** Step class */
namespace Battis\BatchAction;

use MyCLabs\Enum\Enum;

/**
 * An enumerated class to describe install steps
 *
 * @author Seth Battis <seth@battis.net>
 *        
 */
class Batch extends Enum {

	const DATABASE = 'Database';

	const SCRIPT = 'Script';

	const FILES = 'Files';
}

?>