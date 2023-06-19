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

use SplPriorityQueue;
use function array_unique;
use function count;
use function iterator_to_array;

class TimeOrderedQueue extends SplPriorityQueue implements TimeOrderedStorageInterface {
    /**
     * @var int
     */
    protected int $serial = PHP_INT_MIN;

    public function insert($value, $priority) : void
    {
        if (! is_array($priority)) {
            $priority = [$priority, $this->serial++];
        }
        parent::insert($value, $priority);
    }

    public function compare($priority1, $priority2) : int
    {
        if ($priority1 === $priority2) return 0;

        return $priority1 > $priority2 ? -1 : 1;
    }

    public function fixPriority($extract)
    {
        switch ($this->getExtractFlags()) {
            case self::EXTR_PRIORITY :
                $extract = $extract[0];
                break;
            case self::EXTR_BOTH :
                $extract['priority'] = $extract['priority'][0];
                break;
            case self::EXTR_DATA :
                break;
        }
        return $extract;
    }

    public function current() : mixed
    {
        $extract = parent::current();
        return $this->fixPriority($extract);
    }

    public function extract() : mixed
    {
        $extract = parent::extract();
        return $this->fixPriority($extract);
    }

    public function top() : mixed
    {
        $extract = parent::top();
        return $this->fixPriority($extract);
    }

    public function priorityCount(): int
    {
        if ($this->isEmpty()) {
            return 0;
        }
        $iter = clone $this;
        $iter->setExtractFlags(SplPriorityQueue::EXTR_PRIORITY);
        return count(array_unique(iterator_to_array($iter)));
    }

    public function peekSetCount() : int {
        if ($this->isEmpty()) {
            return 0;
        }

        $iter = clone $this;
        $iter->setExtractFlags(SplPriorityQueue::EXTR_PRIORITY);

        $curPriority = null;
        $count = 0;
        while (!$iter->isEmpty()) {
            $itemPriority = $iter->extract();
            if (null === $curPriority) {
                $curPriority = $itemPriority;
            } elseif ($curPriority !== $itemPriority) {
                break;
            }
            ++$count;
        }
        return $count;
    }
}