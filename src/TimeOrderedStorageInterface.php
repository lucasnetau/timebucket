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

    public function extract() : mixed;
    public function top() : mixed;

    /**
     * @return bool
     */
    public function isEmpty();

    /** Number of unique priorities */
    public function priorityCount() : int;

    public function peekSetCount() : int;
}