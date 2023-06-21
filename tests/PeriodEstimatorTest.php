<?php declare(strict_types=1);

use EdgeTelemetrics\TimeBucket\PeriodEstimator;
use EdgeTelemetrics\TimeBucket\TimeBucket;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @covers \EdgeTelemetrics\TimeBucket\PeriodEstimator
 * @covers \EdgeTelemetrics\TimeBucket\TimeBucket
 * @covers \EdgeTelemetrics\TimeBucket\TimeOrderedArray
 */
class PeriodEstimatorTest extends TestCase
{


    public function testEstimator() {
        $bucket = new TimeBucket('minute');
        $ourEpoch = new DateTimeImmutable("2021-01-02 11:00:00");

        for($i = 0; $i < 10; $i++) {
            $time = $ourEpoch->add(new DateInterval("PT" . $i*2 . "M"));
            $bucket->insert( 'test', $time);
        }

        $periodEstimator = new PeriodEstimator();
        $estimatedPeriod = $periodEstimator->estimate($bucket);

        $this->assertEquals('P0Y0M0DT0H2M0S', $estimatedPeriod->format('P%yY%mM%dDT%hH%iM%sS'));
    }

}