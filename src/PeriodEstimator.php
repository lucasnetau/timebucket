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

use DateInterval;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;

/**
 * Class PeriodEstimator
 * @package EdgeTelemetrics\TimeBucket
 */
class PeriodEstimator
{
    /**
     * Calculate the DateInterval between increasing timestamps in the time bucket. This is an estimate of the period/interval of measurements
     * @param TimeBucket $bucket
     * @return DateInterval
     * @throws Exception
     */
    public function estimate(TimeBucket $bucket) : DateInterval {
        $nullFrequency = new DateInterval('PT0S');
        if ($bucket->isEmpty()) {
            return $nullFrequency;
        }

        /** Calculate time difference in seconds between items in the bucket */
        $prevIndex = null;
        $differences = [];
        foreach($bucket->getTimeIndex() as $index) {
            $index = (int)$index->format('U');
            if ($prevIndex !== null) {
                $differences[] = $index - $prevIndex;
            }
            $prevIndex = $index;
        }

        /** Calculate the median difference */
        $median = $this->median($differences);

        $epoch = new DateTimeImmutable('@0');
        return $epoch->diff($epoch->add(new DateInterval('PT' . $median . 'S')));
    }

    /**
     * Calculate the median value for the dataset
     * @param array $values
     * @return float|int
     */
    protected function median(array $values) {
        $count = count($values);
        if ($count === 0) {
            return 0;
        }
        sort($values, SORT_NUMERIC);
        $middle = floor(($count - 1) / 2);

        if ($count = 1) {
            $median = $values[0];
        } elseif ($count % 2) {
            $median = $values[$middle];
        } else {
            $lowMid = $values[$middle];
            $highMid = $values[$middle + 1];
            //Return the average of the low and high.
            $median = (($lowMid + $highMid) / 2);
        }
        return $median;
    }

    /**
     * Calculate the standard deviation for the given dataset
     * @param array $a
     * @param bool $sample
     * @return float
     */
    function stats_standard_deviation(array $a, bool $sample = false) : float {
        $n = count($a);
        if ($n === 0) {
            throw new InvalidArgumentException("The array has zero elements");
        }
        if ($sample && $n === 1) {
            throw new InvalidArgumentException("The array has only 1 element");
        }
        $mean = array_sum($a) / $n;
        $carry = 0.0;
        foreach ($a as $val) {
            $d = ((double) $val) - $mean;
            $carry += $d * $d;
        };
        if ($sample) {
            --$n;
        }
        return sqrt($carry / $n);
    }
}