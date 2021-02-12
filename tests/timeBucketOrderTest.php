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

// Load Composer
require '../vendor/autoload.php';

class Measurement {
    public $time;
    public $value;
    public $sensor_id;

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

echo "Memory usage before initialising TimeBucket : " . round(memory_get_usage(false) / 1024) . "KB" . PHP_EOL;
$bucket = new TimeBucket('minute');
echo "Memory usage after initialising TimeBucket : " . round(memory_get_usage(false) / 1024) . "KB" . PHP_EOL;

for($i = 0; $i < $random_count; $i++)
{
    $timestamp = mt_rand(time()-86400,time()+86400);
    //$time = (new DateTimeImmutable())->setTimestamp($timestamp);
    $time = DateTimeImmutable::createFromFormat('U', (string)$timestamp);
    $measurement = ['time' => $time->format('c'), 'value' => mt_rand(0,14), 'sensor_id' => $i];
    $measurement = new Measurement($measurement);
    $bucket->insert( $measurement, $time );
}
echo "Memory after filling TimeBucket : " . round(memory_get_usage(false) / 1024) . "KB" . PHP_EOL;

$time_end = microtime(true);
echo "Generated $random_count time points in " .  ($time_end - $time_start) . " seconds" . PHP_EOL;

//echo json_encode($bucket);

echo count($bucket) . " datapoints" . PHP_EOL;
echo count(iterator_to_array($bucket->getTimeSlices(), true)) . " timeslices" . PHP_EOL;
echo $bucket->sliceCount() . " slice count" . PHP_EOL;
echo $bucket->nextTimeSliceCount() . " data points in next timeslice" . PHP_EOL;

echo '**** Validate serialize/unserialize()' . PHP_EOL;
echo "Before Serialize - SliceCount: " . $bucket->sliceCount() . ", DataPoints: " . count($bucket) . ", NextSliceCount: " . $bucket->nextTimeSliceCount() . PHP_EOL;
$serialize = serialize($bucket);
unset($bucket);
$newBucket = unserialize($serialize);
echo "After Unserialize- SliceCount: " . $newBucket->sliceCount() . ", DataPoints: " . count($newBucket) . ", NextSliceCount: " . $newBucket->nextTimeSliceCount() . PHP_EOL;
$bucket = $newBucket;

//Validate next timeslice
echo '**** Validate nextTimeSlice()' . PHP_EOL;
['time' => $time, 'data' => $data] = $bucket->nextTimeSlice();
echo $time . PHP_EOL;
echo count($data) . PHP_EOL;

echo '**** Validate getTimeSlices()' . PHP_EOL;
$totalDatapoints = 0;
foreach($bucket->getTimeSlices() as ['time' => $time, 'data' => $data])
{
    $totalDatapoints += count($data);
   // echo 'Slice ' . $time . " contains " . count($data) . " datapoints" . PHP_EOL;
}
echo 'Bucket contains ' . $totalDatapoints . " datapoints." . PHP_EOL;

//Validate next timeslice
echo '**** Validate extractTimeSlice()' . PHP_EOL;
echo "Before Extract - SliceCount: " . $bucket->sliceCount() . ", DataPoints: " . count($bucket) . ", NextSliceCount: " . $bucket->nextTimeSliceCount() . PHP_EOL;
['time' => $time, 'data' => $data] = $bucket->extractTimeSlice();
echo $time . PHP_EOL;
echo count($data) . PHP_EOL;
echo "After Extract  - SliceCount: " . $bucket->sliceCount() . ", DataPoints: " . count($bucket) . ", NextSliceCount: " . $bucket->nextTimeSliceCount() . PHP_EOL;

echo "Memory before emptying TimeBucket : " . round(memory_get_usage(false) / 1024) . "KB" . PHP_EOL;

echo '**** Validate extractTimeSlice() to empty' . PHP_EOL;
while (!$bucket->isEmpty()) {
    ['time' => $time, 'data' => $data] = $bucket->extractTimeSlice();
   // echo 'key: ' . print_r($time, true) . PHP_EOL;
   // echo 'value: ' . print_r($data, true) . PHP_EOL;
}

echo "Memory after emptying TimeBucket : " . round(memory_get_usage(false) / 1024) . "KB" . PHP_EOL;

$time_end = microtime(true);
echo "Completed tests in " .  ($time_end - $time_start) . " seconds" . PHP_EOL;