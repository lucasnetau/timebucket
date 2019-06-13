<?php declare(strict_types=1);

namespace EdgeTelemetrics\TimeBucket;

use Iterator;
use Countable;
use SplPriorityQueue;

/**
 * Class TimeOrderedArray
 * @package EdgeTelemetrics\TimeBucket
 *
 * Implements a Priority Queue equivalent to the SplPriorityQueue but by using sets of sorted arrays.
 *
 * For large number of items this implementation uses 10% of memory as SplPriorityQueue and is ~ 50% faster
 */
class TimeOrderedArray implements Iterator, Countable {

    /**
     * Queue elements (keyed by priority)
     *
     * @var array
     */
    protected $values = [];

    /**
     * Sorted array of priorities
     *
     * @var array
     */
    protected $priorities = [];

    /**
     * Top priority contained in the queue
     *
     * @var int
     */
    protected $top;

    /**
     * Total elements contained in the queue
     *
     * @var int
     */
    protected $total = 0;

    /**
     * Counter for current index in the queue
     *
     * @var int
     */
    protected $index = 0;

    /**
     * @var int Extraction mode - Same as SplPriorityQueue extraction mode
     */
    protected $mode = SplPriorityQueue::EXTR_DATA;

    /**
     * Compare function for sorting of priorities. This provides a min Priority Queue
     * @param $priority1
     * @param $priority2
     * @return int
     */
    public function compare($priority1, $priority2)
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
        $this->values[$priority][] = $value;
        if (!isset($this->priorities[$priority])) {
            $this->priorities[$priority] = $priority;
            uasort($this->priorities, array($this, 'compare'));
            $this->top = array_key_last($this->priorities);
        }
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
        if (!$this->valid()) {
            return false;
        }
        $value = $this->current();
        return $value;
    }

    /**
     * Number of elements contained in the queue.
     *
     * @return int
     */
    public function count()
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
        $value = current($this->values[$priority]);
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
    public function key()
    {
        return $this->index;
    }

    /**
     * Next element on the queue.
     */
    public function next()
    {
        if (false === next($this->values[$this->top])) {
            unset($this->priorities[$this->top]);
            unset($this->values[$this->top]);
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
    public function valid()
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
    public function isEmpty()
    {
        return !$this->valid();
    }

    /**
     * Set the extraction flag for the queue. Priority / Data / Both
     * @param $flag
     */
    public function setExtractFlags($flag)
    {
        $this->mode = $flag;
    }

    /**
     * Get the current extraction flag for the queue
     * @return int
     */
    public function getExtractFlags()
    {
       return $this->mode;
    }
}