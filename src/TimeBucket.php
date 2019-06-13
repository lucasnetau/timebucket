<?php declare(strict_types=1);

namespace EdgeTelemetrics\TimeBucket;

use SplPriorityQueue;
use Countable;
use IteratorAggregate;
use DateTimeImmutable;
use DateTimeInterface;
use DateTime;
use DateTimeZone;
use RuntimeException;
use Exception;
use Generator;
use Serializable;
use JsonSerializable;

use function array_key_exists;
use function array_unique;
use function iterator_to_array;
use function count;
use function serialize;
use function unserialize;

class TimeBucket implements Countable, IteratorAggregate, Serializable, JsonSerializable {

    /**
     * @var TimeOrderedQueue
     */
    protected $innerQueue;

    /**
     * Pre-defined formats to segment DateTime
     */
    const SLICE_FORMATS = [
        "year" => "Y",
        "month" => "Y-m",
        "quarter" => "Y-Q{q}",
        "week" => "Y-W",
        "date" => "Y-m-d",
        "day" => "Y-m-d",
        "hour" => "Y-m-d H:00:00",
        "minute" => "Y-m-d H:i:00",
        "second" => "Y-m-d H:i:s",
        "dayofmonth" => "d",
        "dayofweek" => "w",
        "hourofday" => "H",
        "monthofyear" => "m",
        ];

    /**
     * @var string Date format to segment DateTime into slices
     */
    protected $sliceFormat;

    /**
     * @var DateTimeZone Timezone for the bucket
     */
    protected $timezone;

    /**
     * TimeBucket constructor.
     * @param string $slice The number of slices
     * @param string Timezone for the bucket
     */
    public function __construct(string $slice = 'second', $timezone = 'UTC')
    {
        $this->sliceFormat = array_key_exists($slice, static::SLICE_FORMATS) ? static::SLICE_FORMATS[$slice] : static::SLICE_FORMATS['second'];
        $this->innerQueue = new TimeOrderedArray();
        $this->innerQueue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $this->timezone = new DateTimeZone($timezone);
    }

    /**
     * @return int Number of items in the bucket
     */
    public function count()
    {
        return count($this->innerQueue);
    }

    /**
     * @return int Number of unique timeslices in bucket
     */
    public function sliceCount()
    {
        $iter = $this->getIterator(); //Perform this action on a copy of the queue to ensure we don't modify it
        $iter->setExtractFlags(SplPriorityQueue::EXTR_PRIORITY);
        return $iter->isEmpty() ? 0 : count(array_unique(iterator_to_array($iter)));
    }

    /**
     * @return bool Is bucket empty
     */
    public function isEmpty()
    {
        return $this->innerQueue->isEmpty();
    }

    /**
     * Insert data into the bucket
     * @param $datum
     * @param int|string|DateTimeInterface $priority Time linked to the data, can be Unix timestamp (int), DateTime String (string) or DateTimeInterface
     * @throws Exception
     */
    public function insert($datum, $priority)
    {
        if (is_int($priority))
        {
            /** Integer is processed as a UNIX timestamp */
            $time = (new DateTimeImmutable())->setTimestamp($priority);
        }
        elseif($priority instanceof DateTimeInterface)
        {
            if ($priority instanceof DateTime)
            {
                /** @var DateTime $priority */
                $time = DateTimeImmutable::createFromMutable($priority);
            }
            else
            {
                $time = $priority;
            }
        }
        else
        {
            $time = new DateTimeImmutable($priority);
        }

        $time = $time->setTimezone($this->timezone);
        $priority = $time->format($this->sliceFormat);
        $this->innerQueue->insert($datum, $priority);
    }

    /**
     * @return TimeOrderedQueue
     */
    public function getIterator()
    {
        return clone $this->innerQueue;
    }

    /**
     * Returns the timeslices in the bucket. Does not modify the timebucket
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
        $items = [];
        while (!$iter->isEmpty())
        {
            $item = $iter->extract();
            $itemPriority = $item['priority'];
            if (null == $curPriority)
            {
                $curPriority = $itemPriority;
            }

            if ($curPriority == $itemPriority)
            {
                $items[] = $item['data'];
            }
            else
            {
                yield ['time' => $curPriority, 'data' => $this->unique($items)];
                $curPriority = $itemPriority;
                $items = [$item['data']];
            }
        }
        yield ['time' => $curPriority, 'data' => $this->unique($items)];
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
            $itemPriority = $item['priority'];
            if (null == $curPriority) {
                $curPriority = $itemPriority;
            }

            if ($curPriority == $itemPriority) {
                $items[] = $item['data'];
            } else {
                break;
            }
        }
        return ['time' => $curPriority, 'data' => $this->unique($items)];
    }

    /**
     * Get the number of items in the next timeslice.
     * @return int
     */
    public function nextTimeSliceCount()
    {
        return $this->isEmpty() ? 0 : count($this->nextTimeSlice()['data']);
    }

    /**
     * Extract the next timeslice from the bucket. Pops the items from the bucket.
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
            $itemPriority = $item['priority'];
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
        return ['time' => $curPriority, 'data' => $this->unique($items)];
    }

    public function unique(array $items) : array
    {
        /** Very basic dedupe function. SORT_REGULAR does a == comparison */
        return array_unique($items, SORT_REGULAR);
    }


    /**
     * Round minutes to the nearest interval of a DateTime object.
     *
     * @param DateTimeImmutable $dateTime
     * @param int $minuteInterval
     * @return DateTimeImmutable
     */
    public function roundToNearestMinuteInterval(DateTimeImmutable $dateTime, $minuteInterval = 10) : DateTimeImmutable
    {
        return $dateTime->setTime(
            (int)$dateTime->format('H'),
            (int)round($dateTime->format('i') / $minuteInterval) * $minuteInterval,
            0
        );
    }

    public function jsonSerialize()
    {
        return ['data' => iterator_to_array($this->getTimeSlices()),  'sliceFormat' => $this->sliceFormat, 'timezone' => $this->timezone];
    }

    public function serialize()
    {
        return serialize($this->jsonSerialize());
    }

    public function unserialize($serialized)
    {
        $data = unserialize($serialized);

        $this->__construct();
        $this->timezone = $data['timezone'];
        $this->sliceFormat = $data['sliceFormat'];

        foreach($data['data'] as ['time' => $priority, 'data' => $items])
        {
            foreach ($items as $item) {
                /**
                 * Insert items directly into the queue bypassing the class insert().
                 * This is required to handle insertion of priorities that use sliceFormats like hourofday which resolve to an int.
                 */
                $this->innerQueue->insert($item, $priority);
            }
        }
    }
}