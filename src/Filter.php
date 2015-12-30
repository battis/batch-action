<?php

/** Filter class */
namespace Battis\BatchAction;

/**
 * Filter to select particular batch actions to be run in the BatchManager
 * sequence.
 *
 * @author Seth Battis <seth@battis.net>
 *        
 */
class Filter {

	/**
	 * Denotes a wildcard match for any step value
	 */
	const WILDCARD = -1;

	/** @var array Filter data */
	private $batches = array();

	/**
	 * Add a criteria to the filter
	 *
	 * This method returns a reference to the `Filter` object, to allow for
	 * chaining. For example:
	 * ```
	 * (new Filter())->add(Batch::SCRIPT())->add(Batch::DATABASE(), 1)
	 * ```
	 *
	 * @param Batch $batch
	 *        	Batch to be matched
	 * @param int $step
	 *        	(Optional) step ID number to be matched (default:
	 *        	`Filter::WILDCARD`)
	 * @throws Exception INVALID_FILTER If an unexpected step filter value is
	 *         provided
	 */
	public function add(Batch $batch, $step = self::WILDCARD) {

		$key = $batch->getKey();
		if ($step === self::WILDCARD) {
			$this->batches[$key] = self::WILDCARD;
		} elseif (is_int($step)) {
			$this->batches[$key][] = $step;
			$this->batches[$key] = array_unique($this->batches[$key]);
		} else {
			throw new Exception(__METHOD__ . ': Expected Filter::WILDCARD or integer as step filter', Exception::INVALID_FILTER);
		}
		
		return $this;
	}

	/**
	 * Does a particular batch/step pairing match the filter?
	 *
	 * @param Batch $batch        	
	 * @param int $step        	
	 */
	public function match(Batch $batch, $step) {

		$key = $batch->getKey();
		return (isset($this->batches[$key]) && ($this->batches[$key] === self::WILDCARD || in_array(
			$step, $this->batches[$key])));
	}
}
?>