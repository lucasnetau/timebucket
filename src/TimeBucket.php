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
use function preg_match;
use function serialize;
use function unserialize;
use function is_int;
use function round;

class TimeBucket implements Countable, IteratorAggregate, Serializable, JsonSerializable {

    /**
     * @var TimeOrderedStorageInterface
     */
    protected TimeOrderedStorageInterface $innerQueue;

    /**
     * Pre-defined formats to segment DateTime
     * @ref https://www.php.net/manual/en/datetime.format.php
     */
    const SLICE_FORMATS = [
        "year" => "Y",
        "month" => "Y-m",
        "quarter" => "Y-Q{q}",
        "week" => "Y-W",
        "date" => "Y-m-d",
        "day" => "Y-m-d",
        "hour" => "Y-m-d H:00:00",
        "hourtz" => "Y-m-d\TH:00:00P",
        "minute" => "Y-m-d H:i:00",
        "minutetz" => "Y-m-d\TH:i:00P",
        "second" => "Y-m-d H:i:s",
        "secondtz" => "Y-m-d\TH:i:sP",
        "dayofmonth" => "d",
        "dayofweek" => "w",
        "hourofday" => "H",
        "monthofyear" => "m",
        "unixtime" => "U",
    ];

    /**
     * @var string Date format to segment DateTime into slices
     */
    protected string $sliceFormat;

    /** @var int Interval to group time into slices */
    protected int $interval = 1;

    /**
     * @var DateTimeZone Timezone for the bucket
     */
    protected DateTimeZone $timezone;

    /**
     * TimeBucket constructor.
     * @param string $slice The slice type for the bucket
     * @param DateTimeZone|string $timezone Timezone for the bucket
     * @throws Exception
     */
    public function __construct(string $slice = 'second', DateTimeZone|string $timezone = 'UTC')
    {
        if (preg_match('#(?P<quantity>\d+)\s+(?P<unit>minute)#', $slice, $matches)) {
            $this->sliceFormat = static::SLICE_FORMATS[$matches['unit']];
            $this->interval = (int)$matches['quantity'];
        } else {
            $this->sliceFormat = array_key_exists($slice, static::SLICE_FORMATS) ? static::SLICE_FORMATS[$slice] : static::SLICE_FORMATS['second'];
        }
        $this->innerQueue = new TimeOrderedArray();
        $this->innerQueue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $this->timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
    }

    /**
     * @return int Number of items in the bucket
     */
    public function count() : int
    {
        return count($this->innerQueue);
    }

    /**
     * @return int Number of unique timeslices in bucket
     */
    public function sliceCount() : int
    {
        return $this->innerQueue->priorityCount();
    }

    /**
     * @phpstan-impure
     * @return bool Is bucket empty
     */
    public function isEmpty(): bool
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
            $time = DateTimeImmutable::createFromFormat('U', (string)$priority);
        }
        elseif($priority instanceof DateTimeInterface)
        {
            $time = ($priority instanceof DateTime) ? DateTimeImmutable::createFromMutable($priority) : $priority;
        }
        else
        {
            $time = new DateTimeImmutable($priority);
        }

        $time = $time->setTimezone($this->timezone);
        if ($this->interval !== 1) {
            //If we have an interval more than one slice we round the values into the slice
            $time = $this->roundToNearestMinuteInterval($time, $this->interval);
        }
        $priority = $time->format($this->sliceFormat);
        $this->innerQueue->insert($datum, $priority);
    }

    /**
     * @return TimeOrderedStorageInterface
     */
    public function getIterator() : TimeOrderedStorageInterface
    {
        return clone $this->innerQueue;
    }

    /**
     * Returns the timeslices in the bucket. Does not modify the timebucket
     * @return Generator{time: string, data: array}
     */
    public function getTimeSlices(): Generator
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
            $curPriority ??= $itemPriority;

            if ($curPriority === $itemPriority)
            {
                //Group same priority items together
                $items[] = $item['data'];
            }
            else
            {
                //Yield the previous time slice
                yield ['time' => $curPriority, 'data' => $this->unique($items)];
                $curPriority = $itemPriority;
                $items = [$item['data']];
            }
        }
        //Yield the final time slice
        yield ['time' => $curPriority, 'data' => $this->unique($items)];
    }

    /**
     * Return the next timeslice in the bucket. Does not remove items from the bucket (ie peek)
     * @return array{time: string, data: array}|bool
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
            $curPriority ??= $itemPriority;

            if ($curPriority !== $itemPriority) {
                break;
            }

            $items[] = $item['data'];
        }
        return ['time' => $curPriority, 'data' => $this->unique($items)];
    }

    /**
     * Get the number of items in the next timeslice.
     * @return int
     */
    public function nextTimeSliceCount(): int
    {
        return $this->innerQueue->peekSetCount();
    }

    /**
     * Extract the next timeslice from the bucket. Pops the items from the bucket.
     * @return array{time: string, data: array}
     */
    public function extractTimeSlice(): array
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
            $curPriority ??= $itemPriority;

            if ($curPriority !== $itemPriority) {
                break;
            }

            $item =  $iter->extract();
            $items[] = $item['data'];
        }
        return ['time' => $curPriority, 'data' => $this->unique($items)];
    }

    /**
     * A very basic deduplication function for returning values from a timeslice
     * @param array $items
     * @return array
     */
    public function unique(array $items) : array
    {
        /** SORT_REGULAR does a == comparison (loose) */
        return array_unique($items, SORT_REGULAR);
    }

    /**
     * Return the defined slice format for the bucket
     * @return string
     */
    public function getTimeFormat() : string {
        return $this->sliceFormat;
    }

    /**
     * @return Generator
     * @throws Exception
     */
    public function getTimeIndex() : Generator {
        if ($this->innerQueue->isEmpty()) {
            return;
        }

        /** Calculate time difference in seconds between items in the bucket */
        $iter = $this->getIterator();
        $iter->setExtractFlags(SplPriorityQueue::EXTR_PRIORITY);

        $curPriority = null;
        while (!$iter->isEmpty()) {
            $priority = $iter->extract();
            $itemPriority = DateTimeImmutable::createFromFormat($this->sliceFormat, $priority, $this->timezone);
            if (null === $curPriority) {
                $curPriority = $itemPriority;
                yield $itemPriority;
            } else {
                if ($itemPriority > $curPriority) {
                    $curPriority = $itemPriority;
                    yield $itemPriority;
                }
            }
        }
    }

    /**
     * Round minutes to the nearest interval of a DateTime object.
     *
     * @param DateTimeImmutable $dateTime
     * @param int $minuteInterval
     * @return DateTimeImmutable
     */
    public function roundToNearestMinuteInterval(DateTimeImmutable $dateTime, int $minuteInterval = 10) : DateTimeImmutable
    {
        return $dateTime->setTime(
            (int)$dateTime->format('H'),
            (int)round($dateTime->format('i') / $minuteInterval) * $minuteInterval,
            0
        );
    }

    /**
     * @return DateTimeZone
     */
    public function getTimezone() : DateTimeZone {
        return $this->timezone;
    }

    /**
     * @codeCoverageIgnore
     */
    public function jsonSerialize(): array
    {
        return [
            'data' => iterator_to_array($this->getTimeSlices()),
            'sliceFormat' => $this->sliceFormat,
            'interval' => $this->interval,
            'timezone' => $this->timezone,
        ];
    }

    /**
     * @return array
     * @codeCoverageIgnore
     */
    public function __serialize(): array
    {
        return $this->jsonSerialize();
    }

    /**
     * @deprecated Legacy interface
     * @codeCoverageIgnore
     */
    public function serialize(): ?string
    {
        return serialize($this->jsonSerialize());
    }

    /**
     * @param array $data
     * @return void
     * @codeCoverageIgnore
     */
    public function __unserialize(array $data) {
        $this->__construct();
        $this->timezone = $data['timezone'] ?? new DateTimeZone('UTC');
        $this->sliceFormat = $data['sliceFormat'];
        $this->interval = $data['interval'] ?? 1;

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

    /**
     * @deprecated Legacy interface
     * @codeCoverageIgnore
     */
    public function unserialize($data)
    {
        $data = unserialize($data);

        $this->__unserialize($data);
    }

    /**
     * Support deep cloning of TimeBuckets to ensure the inner queue is cloned
     */
    function __clone()
    {
        $this->innerQueue = clone $this->innerQueue;
    }
}
