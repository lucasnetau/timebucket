<?php

namespace EdgeTelemetrics\TimeBucket;

use \SplPriorityQueue;

class TimeOrderedQueue extends SplPriorityQueue {
    /**
     * @var int
     */
    protected $serial = PHP_INT_MAX;

    public function insert($datum, $priority)
    {
        if (! is_array($priority)) {
            $priority = [$priority, $this->serial--];
        }
        parent::insert($datum, $priority);
    }

    public function compare($priority1, $priority2)
    {
        if ($priority1 === $priority2) return 0;

        return $priority1 > $priority2 ? -1 : 1;
    }
}