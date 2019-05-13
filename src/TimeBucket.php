<?php declare(strict_types=1);

namespace EdgeTelemetrics\TimeBucket;

use SplPriorityQueue;
use Countable;
use IteratorAggregate;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;
use Generator;

class TimeBucket implements Countable, IteratorAggregate {

    /**
     * @var SplPriorityQueue
     */
    protected $innerQueue;

    const SLICE_FORMATS = array(
        "year" => "Y",
        "month" => "Y-m",
        "quarter" => "Y-Q{q}",
        "week" => "Y-W",
        "date" => "Y-m-d",
        "day" => "Y-m-d",
        "hour" => "Y-m-d H",
        "minute" => "Y-m-d H:i",
        "second" => "Y-m-d H:i:s",
        "dayofmonth" => "d",
        "dayofweek" => "w",
        "hourofday" => "H",
        "monthofyear" => "m",
        );

    protected $sliceFormat;

    /**
     * TimeBucket constructor.
     * @param string $slice The number of slices
     */
    public function __construct(string $slice = 'second')
    {
        $this->sliceFormat = array_key_exists($slice, static::SLICE_FORMATS) ? static::SLICE_FORMATS[$slice] : static::SLICE_FORMATS['second'];
        $this->innerQueue = new TimeOrderedQueue();
        $this->innerQueue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
    }

    public function count()
    {
        return count($this->innerQueue);
    }

    public function sliceCount()
    {
        $iter = $this->getIterator();
        $iter->setExtractFlags(SplPriorityQueue::EXTR_PRIORITY);

        if ($iter->isEmpty()) {
            return 0;
        }

        return count(array_unique(array_column(iterator_to_array($iter), 0)));
    }

    public function isEmpty()
    {
        return $this->innerQueue->isEmpty();
    }

    public function insert($datum, $priority)
    {
        if (is_int($priority))
        {
            /** Integer is processed as a UNIX timestamp */
            $time = (new DateTimeImmutable())->setTimestamp($priority);
        }
        elseif($priority instanceof DateTimeInterface)
        {
            $time = $priority;
        }
        else
        {
            $time = new DateTimeImmutable($priority);
        }

        $priority = $time->format($this->sliceFormat);
        $this->innerQueue->insert($datum, $priority);
    }

    public function getIterator()
    {
        return clone $this->innerQueue;
    }


    /**
     * Gets the top timeslice
     * @return Generator|void
     */
    public function getTimeSlices()
    {
        if ($this->innerQueue->isEmpty()) {
            return;
        }

        $iter = $this->getIterator();
        $iter->setExtractFlags(SplPriorityQueue::EXTR_BOTH);

        $curPriority = null;
        while (!$iter->isEmpty())
        {
            $item = $iter->extract();
            $itemPriority = $item['priority'][0];
            if (null == $curPriority)
            {
                $curPriority = $itemPriority;
                $items = [];
            }

            if ($curPriority == $itemPriority)
            {
                $items[] = $item['data'];
            }
            else
            {
                yield $curPriority => $items;
                $curPriority = $itemPriority;
                $items = [$item['data']];
            }
        }
        yield $curPriority => $items;
    }

    /**
     * Return the next timeslice in the bucket. Does not remove items from the bucket (ie peek)
     * @return array|bool
     */
    public function nextTimeSlice()
    {
        if ($this->innerQueue->isEmpty()) {
            return false;
        }

        $iter = $this->getIterator();
        $iter->setExtractFlags(SplPriorityQueue::EXTR_BOTH);

        $curPriority = null;
        $items = [];
        while (!$iter->isEmpty()) {
            $item = $iter->extract();
            $itemPriority = $item['priority'][0];
            if (null == $curPriority) {
                $curPriority = $itemPriority;
            }

            if ($curPriority == $itemPriority) {
                $items[] = $item['data'];
            } else {
                break;
            }
        }
        return [$curPriority => $items];
    }

    /**
     * Extract the next timeslice from the bucket. Pops the items from the bucket (ie pop)
     * @return array
     */
    public function extractTimeSlice()
    {
        if ($this->innerQueue->isEmpty()) {
            throw new RuntimeException("Cannot extract time slice from empty Time Bucket");
        }

        $iter = $this->innerQueue;
        $iter->setExtractFlags(SplPriorityQueue::EXTR_BOTH);

        $curPriority = null;
        $items = [];
        while (!$iter->isEmpty())
        {
            $item = $iter->top();
            $itemPriority = $item['priority'][0];
            if (null == $curPriority)
            {
                $curPriority = $itemPriority;
           }

            if ($curPriority == $itemPriority)
            {
                $item =  $iter->extract();
                $items[] = $item['data'];
            }
            else
            {
                break;
            }
        }
        return [$curPriority => $items];
    }

}