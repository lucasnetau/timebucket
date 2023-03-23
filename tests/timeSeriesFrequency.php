<?php declare(strict_types=1);

/*
 * This file is part of the TimeBucket package.
 *
 * (c) James Lucas <james@lucas.net.au>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

error_reporting(E_ALL);
ini_set('display_errors', "on");
ini_set('memory_limit', '2G');

use \EdgeTelemetrics\TimeBucket\TimeBucket;
use EdgeTelemetrics\TimeBucket\PeriodEstimator;

// Load Composer
require '../vendor/autoload.php';

class Measurement {
    public string $time;
    public ?float $value;
    public int $sensor_id;

    public function __construct($array)
    {
        foreach($array as $k => $v)
        {
            $this->$k = $v;
        }
    }
}

$random_count = 1000000;
$dates = [];

$time_start = microtime(true);

$bucket = new TimeBucket('minute');

$now = new DateTimeImmutable();
$ourEpoch = new DateTimeImmutable("2021-01-02 11:00:00");

for($i = 0; $i < 10; $i++) {
    $time = $ourEpoch->add(new DateInterval("PT" . $i*2 . "M"));
    $bucket->insert( new Measurement(['time' => $time->format('c'), 'value' => mt_rand(0,14), 'sensor_id' => 1]), $time);
}

$periodEstimator = new PeriodEstimator();
$estimatedPeriod = $periodEstimator->estimate($bucket);

echo "Interval: " . $estimatedPeriod->format('P%yY%mM%dDT%hH%iM%sS') . PHP_EOL;

$bucketTimeIndex = iterator_to_array($bucket->getTimeIndex());

$begin = $bucketTimeIndex[array_key_first($bucketTimeIndex)];
$end = $bucketTimeIndex[array_key_last($bucketTimeIndex)];

$period = new DatePeriod($begin, $estimatedPeriod, $end->modify('+ 1 sec'));
$periodTimeIndex = iterator_to_array($period);
$periodTimeIndex[] = new DateTimeImmutable('now');

if (count($periodTimeIndex) === count($bucketTimeIndex)) {
    $missing = [];
} else {
    $missing = array_udiff($periodTimeIndex, $bucketTimeIndex, function ($a, $b) {
        return $a <=> $b;
    });
}

foreach($missing as $missed) {
    echo PHP_EOL . "Missing: " . $missed->format('c');
}

echo PHP_EOL;
echo "Completed tests in " .  (microtime(true) - $time_start) . " seconds" . PHP_EOL;