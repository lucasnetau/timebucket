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

use Closure;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Generator;
use Throwable;

class TimeBuffer {

    protected TimeBucket $bucket;

    protected Closure $bufferReadyCheck;

    protected Closure $sliceHandler;

    /**
     * @param string $slice
     * @param DateTimeZone|string $timezone
     * @param callable $bufferReadyCheck(string $time, array $data, TimeBucket $bucket)
     * @param callable $sliceHandler(string $time, array $data)
     * @throws Exception
     */
    public function __construct(string $slice, DateTimeZone|string $timezone, callable $bufferReadyCheck, callable $sliceHandler) {
        $this->bucket = new TimeBucket($slice, $timezone);
        $this->bufferReadyCheck = $bufferReadyCheck;
        $this->sliceHandler = $sliceHandler;
    }

    /**
     * @return void
     * @throws Throwable
     */
    private function maybeProcessAllReadySlices(): void {
        while (!$this->bucket->isEmpty()) {
            $next = $this->bucket->nextTimeSlice();
            if ($next === false) {
                break;
            }
            if (!($this->bufferReadyCheck)($next['time'], $next['data'], $this->bucket)) {
                break; // No more ready slices
            }
            $extracted = $this->bucket->extractTimeSlice();
            ($this->sliceHandler)($extracted['time'], $extracted['data']);
        }
    }

    /**
     * @param $datum
     * @param DateTimeInterface|int|string $priority
     * @return void
     * @throws Throwable The caller should wrap insert in try/catch if the sliceHandler throws unexpectedly
     */
    public function insert($datum, DateTimeInterface|int|string $priority) : void {
        $this->bucket->insert($datum, $priority);

        //Guaranteed that at least one slice exists now, check if ready for processing
        $this->maybeProcessAllReadySlices();
    }

    /**
     * Flush all remaining slices from the buffer.
     * This should be called when you're done with the buffer.
     * @return Generator List of all flushed slices
     */
    public function flush(): Generator {
        while (!$this->bucket->isEmpty()) {
            yield $this->bucket->extractTimeSlice();
        }
    }

    /**
     * Explicitly drop the current/first slice in the buffer,
     * this may be called by the user if too many slices are buffered and the ready callback doesn't pass for the first slice
     * @return void
     */
    public function discardCurrentSlice(): void {
        if (!$this->isEmpty()) {
            $this->bucket->extractTimeSlice();
        }
    }

    public function isEmpty(): bool {
        return $this->bucket->isEmpty();
    }

    /**
     * Get the number of items in the buffer.
     * @return int
     */
    public function count(): int {
        return $this->bucket->count();
    }

    /**
     * Get the number of unique time slices in the buffer.
     * @return int
     */
    public function sliceCount(): int {
        return $this->bucket->sliceCount();
    }

    /**
     * @internal
     * @return TimeBucket
     */
    public function getBucket(): TimeBucket {
        return $this->bucket;
    }
}