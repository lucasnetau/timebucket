<?php declare(strict_types=1);

/*
 * This file is part of the TimeBucket package.
 *
 * (c) James Lucas <james@lucas.net.au>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EdgeTelemetrics\TimeBucket;

use Countable;
use Iterator;

/**
 * Interface TimeOrderedStorageInterface
 * @package EdgeTelemetrics\TimeBucket
 */
interface TimeOrderedStorageInterface extends Iterator, Countable {
    public function insert($value, $priority) : void;

    public function current() : mixed;
    public function compare($priority1, $priority2) : int;
    public function extract() : mixed;
    public function top() : mixed;

    /**
     * @return bool
     */
    public function isEmpty();

    public function priorityCount() : int;

    /** Perform any tasks on parent object prior to clone being called */
    public function beforeClone() : void;
}