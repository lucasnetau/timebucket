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

use function krsort;
use function array_key_exists;
use function array_key_last;
use function current;
use function next;
use function count;

/**
 * Class TimeOrderedArray
 * @package EdgeTelemetrics\TimeBucket
 *
 * Implements a Priority Queue equivalent to the SplPriorityQueue but by using sets of sorted arrays.
 *
 * For large number of items this implementation uses 10% of memory as SplPriorityQueue and is ~ 50% faster
 */
class TimeOrderedArray implements TimeOrderedStorageInterface {

    /**
     * Queue elements (keyed by priority)
     *
     * @var array
     */
    protected array $values = [];

    /**
     * Sorted array of priorities
     *
     * @var array
     */
    protected array $priorities = [];

    /**
     * Flag to let us know if priorities have been sorted
     * @var bool
     */
    protected bool $prioritiesUnsorted = false;

    /**
     * Top priority contained in the queue (First out)
     *
     * @var ?int|string
     */
    protected string|int|null $top = null;

    /**
     * Total elements contained in the queue
     *
     * @var int
     */
    protected int $total = 0;

    /**
     * Counter for current index in the queue
     *
     * @var int
     */
    protected int $index = 0;

    protected int $priorityIndex = 0;

    /**
     * @var int Extraction mode - Same as SplPriorityQueue extraction mode
     */
    protected int $mode = SplPriorityQueue::EXTR_DATA;

    /**
     * Compare function for sorting of priorities. This provides a min Priority Queue
     * @param $priority1
     * @param $priority2
     * @return int
     */
    public function compare($priority1, $priority2) : int
    {
        return $priority2 <=> $priority1;
    }

    /**
     * Insert a new element into the queue.
     *
     * @param mixed $value    Element to insert
     * @param string|int   $priority Priority can be any key acceptable to a PHP array (so int|string)
     */
    public function insert($value, $priority) : void
    {
        if (!array_key_exists($priority, $this->priorities))
        {
            $this->values[] = [];
            $newIndex = $this->priorityIndex++;
            if ($this->top === null) {
                $this->priorities[$priority] = $newIndex;
                $this->prioritiesUnsorted = false;
                $this->top = $priority;
            } else {
                $this->priorities[$priority] = $newIndex;
                if ($priority < $this->top) {
                    $this->top = $priority;
                } else {
                    $this->prioritiesUnsorted = true;
                }
            }
        }
        $this->values[$this->priorities[$priority]][] = $value;
        ++$this->total;
    }

    /**
     * Extracts a node from the current position of the queue.
     *
     * @return mixed
     */
    public function extract() : mixed
    {
        if (!$this->valid()) {
            return false;
        }
        $value = $this->current();
        $this->next();
        return $value;
    }

    /**
     * Returns the node from the current position of the queue.
     *
     * @return mixed
     */
    public function top() : mixed
    {
        if (null === $this->top) {
            return false;
        }
        return $this->current();
    }

    /**
     * Number of elements contained in the queue.
     *
     * @return int
     */
    public function count() : int
    {
        return $this->total;
    }

    /**
     * Current element.
     *
     * @return mixed
     */
    public function current() : mixed
    {
        $priority = $this->top;
        $value = current($this->values[$this->priorities[$priority]]);
        switch ($this->mode) {
            case SplPriorityQueue::EXTR_BOTH :
                return ['data' => $value, 'priority' => $priority];
            case SplPriorityQueue::EXTR_PRIORITY :
                return $priority;
            case SplPriorityQueue::EXTR_DATA :
                return $value;
        }
        return false;
    }

    /**
     * Current element index.
     *
     * @return int
     */
    public function key() : int
    {
        return $this->index;
    }

    /**
     * Next element on the queue.
     */
    public function next() : void
    {
        $top = $this->top;
        $priority = $this->priorities[$top];
        if (false === next($this->values[$priority])) {
            unset($this->values[$priority]);
            unset($this->priorities[$top]);
            /** We delay sorting of priorities until we start reading them. */
            if ($this->prioritiesUnsorted)
            {
                krsort($this->priorities);
                $this->prioritiesUnsorted = false;
            }
            if (count($this->priorities) === 0) {
                $this->top = null;
                $this->priorities = [];
                $this->values = [];
                $this->priorityIndex = 0;
            } else {
                $this->top = array_key_last($this->priorities);
            }
        }
        ++$this->index;
        --$this->total;
    }

    /**
     * Checks if current position is valid
     *
     * @return bool
     */
    public function valid() : bool
    {
        return null !== $this->top;
    }

    /**
     * Rewind not valid for the queue
     */
    public function rewind() : void
    {
        // NOOP
    }

    /**
     * Check if the queue is empty
     * @return bool
     */
    public function isEmpty() : bool
    {
        return null === $this->top;
    }

    /**
     * Set the extraction flag for the queue. Priority / Data / Both
     * @param int $flag
     */
    public function setExtractFlags(int $flag): void
    {
        $this->mode = $flag;
    }

    /**
     * Get the current extraction flag for the queue
     * @return int
     */
    public function getExtractFlags() : int
    {
        return $this->mode;
    }

    public function priorityCount(): int
    {
        return count($this->priorities);
    }

    public function peekSetCount() : int {
        if (null === $this->top) {
            return 0;
        }
        return count($this->values[$this->priorities[$this->top]]);
    }
}