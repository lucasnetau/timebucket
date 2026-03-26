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

    /** @var string|null */
    protected ?string $intervalUnit = null;

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
        // Accept "N unit" syntax for minute, hour, day, week, month, year
        if (preg_match('#^(?P<quantity>\d+)\s+(?P<unit>minute|hour|day|week|month|year)s?$#i', $slice, $matches)) {
            $unit = strtolower($matches['unit']);
            $this->interval = (int)$matches['quantity'];
            // Map unit to a slice format that represents the *base* granularity
            $this->sliceFormat = static::SLICE_FORMATS[$unit] ?? static::SLICE_FORMATS['second'];
            $this->intervalUnit = $unit;
        } else {
            $this->sliceFormat = array_key_exists($slice, static::SLICE_FORMATS)
                ? static::SLICE_FORMATS[$slice]
                : static::SLICE_FORMATS['second'];
            $this->interval = 1;
            $this->intervalUnit = null;
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
     * @param DateTimeInterface|int|string $priority Time linked to the data, can be Unix timestamp (int), DateTime String (string) or DateTimeInterface
     * @throws Exception
     */
    public function insert($datum, DateTimeInterface|int|string $priority): void
    {
        if (is_int($priority)) {
            /** Integer is processed as a UNIX timestamp */
            $time = DateTimeImmutable::createFromFormat('U', (string)$priority);
        } elseif($priority instanceof DateTimeInterface) {
            $time = ($priority instanceof DateTime) ? DateTimeImmutable::createFromMutable($priority) : $priority;
        } else {
            $time = new DateTimeImmutable($priority);
        }

        $time = $time->setTimezone($this->timezone);
        if ($this->interval !== 1 && $this->intervalUnit !== null) {
            /** Use generic rounding based on the detected unit */
            $time = $this->roundToNearestInterval($time, $this->interval, $this->intervalUnit);
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
    public function nextTimeSlice(): bool|array
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
     * Get the number of items in the current timeslice.
     * @return int
     */
    public function currentTimeSliceCount(): int
    {
        return $this->innerQueue->peekSetCount();
    }

    /**
     * @return int
     * @deprecated "Use currentTimeSliceCount()"
     */
    public function nextTimeSliceCount(): int
    {
        return $this->currentTimeSliceCount();
    }


    /**
     * Extract the current timeslice from the bucket. Pops the items from the bucket.
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
     * Round to the nearest interval of a DateTime object.
     *
     * @param DateTimeImmutable $dateTime
     * @param int $interval
     * @param string $unit
     * @return DateTimeImmutable
     */
    public function roundToNearestInterval(DateTimeImmutable $dateTime, int $interval, string $unit): DateTimeImmutable
    {
        switch ($unit) {
            case 'minute':
                // Floor the minutes, carry overflow into the hour if needed.
                $minutes = (int)$dateTime->format('i');
                $rounded = intdiv($minutes, $interval) * $interval;
                $hourAdjustment = intdiv($rounded, 60);
                $newMinute = $rounded % 60;
                return $dateTime->setTime(
                    (int)$dateTime->format('H') + $hourAdjustment,
                    $newMinute,
                    0
                );

            case 'hour':
                // Floor the hours, carry overflow into the day.
                $hours = (int)$dateTime->format('H');
                $rounded = intdiv($hours, $interval) * $interval;
                $dayAdjustment = intdiv($rounded, 24);
                $newHour = $rounded % 24;
                return $dateTime->setTime(
                    $newHour,
                    0,
                    0
                )->modify("+{$dayAdjustment} day");

            case 'day':
                // Days are 1‑based, so we subtract 1 before flooring.
                $day = (int)$dateTime->format('d');
                $rounded = intdiv($day - 1, $interval) * $interval + 1;
                return $dateTime->setDate(
                    (int)$dateTime->format('Y'),
                    (int)$dateTime->format('m'),
                    $rounded
                )->setTime(0, 0, 0);

            case 'week':
                // ISO weeks are also 1‑based.
                $isoWeek = (int)$dateTime->format('W');
                $roundedWeek = intdiv($isoWeek - 1, $interval) * $interval + 1;

                // Compute the Monday of the rounded ISO week.
                $year = (int)$dateTime->format('o');
                $date = new DateTimeImmutable();
                $date = $date->setDate($year, 1, 4) // Jan-4 is always in ISO week-1
                    ->modify('Monday this week')
                    ->modify('+' . ($roundedWeek - 1) . ' weeks')
                    ->setTime(0, 0, 0);
                return $date->setTimezone($this->timezone);

            case 'month':
                // Months are 1‑based.
                $month = (int)$dateTime->format('n');
                $roundedMonth = intdiv($month - 1, $interval) * $interval + 1;
                $yearAdjustment = intdiv($roundedMonth - 1, 12);
                $newMonth = ($roundedMonth - 1) % 12 + 1;
                return $dateTime->setDate(
                    (int)$dateTime->format('Y') + $yearAdjustment,
                    $newMonth,
                    1
                )->setTime(0, 0, 0);

            case 'year':
                // Years start at 0, so simple floor works.
                $year = (int)$dateTime->format('Y');
                $roundedYear = intdiv($year, $interval) * $interval;
                return $dateTime->setDate($roundedYear, 1, 1)->setTime(0, 0, 0);

            default:
                // Should never happen – return the original DateTime.
                return $dateTime;
        }
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

    function __debugInfo(): ?array
    {
        $clone = clone $this;
        $data = [
            'timezone' => $clone->timezone,
            'format' => $clone->getTimeFormat(),
            'sliceCount' => $clone->sliceCount(),
            'slices' => [],
        ];

        while (!$clone->isEmpty()) {
            ['time' => $timestamp, 'data' => $measurements] = $clone->extractTimeSlice();

            $data['slices'][$timestamp] = $measurements;
        }

        return $data;
    }
}
