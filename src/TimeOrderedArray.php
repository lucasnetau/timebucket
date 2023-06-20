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

use SplMinHeap;
use SplPriorityQueue;

use function array_key_exists;
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
     * Queue elements
     *
     * @var array
     */
    protected array $values = [];

    /**
     * Index mapping priority to $values array index
     * @var array
     */
    protected array $prioritiesIndex = [];

    /**
     * @var SplMinHeap Sorted list of priorities
     */
    protected SplMinHeap $priorityOrder;

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

    public function __construct() {
        $this->priorityOrder = new SplMinHeap();
    }

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
        if (!array_key_exists($priority, $this->prioritiesIndex))
        {
            $this->values[] = [];
            $this->prioritiesIndex[$priority] = $this->priorityIndex++;
            $this->priorityOrder->insert($priority);
            if ($this->top === null || $priority < $this->top) {
                $this->top = $priority;
            }
        }
        $this->values[$this->prioritiesIndex[$priority]][] = $value;
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
        $value = current($this->values[$this->prioritiesIndex[$priority]]);
        return match ($this->mode) {
            SplPriorityQueue::EXTR_BOTH => ['data' => $value, 'priority' => $priority],
            SplPriorityQueue::EXTR_PRIORITY => $priority,
            SplPriorityQueue::EXTR_DATA => $value,
            default => false,
        };
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
        $priority = $this->prioritiesIndex[$top];
        if (false === next($this->values[$priority])) {
            unset($this->values[$priority]);
            unset($this->prioritiesIndex[$top]);
            $this->priorityOrder->extract();
            if (count($this->prioritiesIndex) === 0) {
                $this->top = null;
                $this->prioritiesIndex = [];
                $this->values = [];
                $this->priorityIndex = 0;
            } else {
                $this->top = $this->priorityOrder->top();
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
        return count($this->prioritiesIndex);
    }

    public function peekSetCount() : int {
        if (null === $this->top) {
            return 0;
        }
        return count($this->values[$this->prioritiesIndex[$this->top]]);
    }

    public function __clone() {
        $this->priorityOrder = clone $this->priorityOrder;
    }
}