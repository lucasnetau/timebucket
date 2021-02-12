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

use Iterator;
use Countable;
use SplPriorityQueue;

use function uksort;
use function array_key_exists;
use function array_key_last;
use function current;
use function next;

/**
 * Class TimeOrderedArray
 * @package EdgeTelemetrics\TimeBucket
 *
 * Implements a Priority Queue equivalent to the SplPriorityQueue but by using sets of sorted arrays.
 *
 * For large number of items this implementation uses 10% of memory as SplPriorityQueue and is ~ 50% faster
 */
class TimeOrderedArray implements Iterator, Countable, TimeBucketImplementationInterface {

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
     * Top priority contained in the queue
     *
     * @var ?int|string
     */
    protected $top = null;

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
        if ($priority1 === $priority2) return 0;

        return $priority1 > $priority2 ? -1 : 1;
    }

    /**
     * Insert a new element into the queue.
     *
     * @param mixed $value    Element to insert
     * @param string|int   $priority Priority can be any key acceptable to a PHP array (so int|string)
     */
    public function insert($value, $priority)
    {
        if (!array_key_exists($priority, $this->priorities))
        {
            $this->values[] = [];
            $newIndex = array_key_last($this->values);
            $this->priorities[$priority] = $newIndex;
            $this->prioritiesUnsorted = true;
            if (null === $this->top || 1 == $this->compare($priority, $this->top)) {
                $this->top = $priority;
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
    public function extract()
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
    public function top()
    {
        if ($this->isEmpty()) {
            return false;
        }
        return$this->current();
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
    public function current()
    {
        $priority = $this->top;
        $value = current($this->values[$this->priorities[$priority]]);
        switch ($this->mode) {
            case SplPriorityQueue::EXTR_BOTH :
                return ['data' => $value, 'priority' => $priority];
                break;
            case SplPriorityQueue::EXTR_PRIORITY :
                return $priority;
                break;

            case SplPriorityQueue::EXTR_DATA :
                return $value;
                break;
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
    public function next()
    {
        if (false === next($this->values[$this->priorities[$this->top]])) {
            unset($this->values[$this->priorities[$this->top]]);
            unset($this->priorities[$this->top]);
            /** We delay sorting of priorities until we start reading them. */
            if ($this->prioritiesUnsorted)
            {
                uksort($this->priorities, array($this, 'compare'));
                $this->prioritiesUnsorted = false;
            }
            $this->top = empty($this->priorities) ? null : array_key_last($this->priorities);
            /** Re-initialise the arrays to allow the GC to cleanup */
            if (empty($this->priorities))
            {
                $this->priorities = [];
                $this->values = [];
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
    public function rewind()
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
    public function setExtractFlags(int $flag)
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
}