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

use Countable;
use DateTimeZone;
use Exception;

class MultiTimeBucket implements Countable {

    protected array $buckets = [];

    protected string $timeslice;

    protected DateTimeZone $timezone;

    /**
     * MultiTimeBucket constructor.
     * @param string $timeslice The slice type for the bucket
     * @param string|DateTimeZone Timezone for the bucket
     * @param array|null $series List of
     */
    public function __construct(string $timeslice = 'second', $timezone = 'UTC', ?array $series = null) {
        $this->timeslice = $timeslice;
        if ($timezone instanceof DateTimeZone) {
            $this->timezone = $timezone;
        } else {
            $this->timezone = new DateTimeZone($timezone);
        }
        if (null !== $series) {
            foreach($series as $index) {
                $this->buckets[$index] = new TimeBucket($this->timeslice, $this->timezone);
            }
        }
    }

    /**
     * @return int Number of bucket (unique series)
     */
    public function count() : int
    {
        return count($this->buckets);
    }

    /**
     * @param $series
     * @param $datum
     * @param $priority
     * @throws Exception
     */
    public function insert($series, $datum, $priority) {
        if (!array_key_exists($series, $this->buckets)) {
            $this->buckets[$series] = new TimeBucket($this->timeslice, $this->timezone);
        }
        $this->buckets[$series]->insert($datum, $priority);
    }

    /**
     * @return bool
     */
    public function isEmpty() : bool {
        /** @var TimeBucket $bucket */
        foreach($this->buckets as $bucket) {
            if (false === $bucket->isEmpty()) {
                return false;
            }
        }
        return true;
    }

    public function getTimeSlices(): Generator {

    }

    /** Peeking */
    public function nextTimeSlice() {

    }

    public function extractTimeSlice(): array {

    }
}
