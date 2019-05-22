<?php declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', "on");

use \EdgeTelemetrics\TimeBucket\TimeBucket;

// Load Composer
require '../vendor/autoload.php';

$random_count = 4000;
$dates = [];

$time_start = microtime(true);

$bucket = new TimeBucket('hour');

for($i = 0; $i < $random_count; $i++)
{
    $timestamp = mt_rand(time()-86400,time()+86400);
    $bucket->insert( "test $i", (new DateTimeImmutable())->setTimestamp($timestamp));
}

//echo json_encode($bucket);

echo count($bucket) . " datapoints" . PHP_EOL;
echo count(iterator_to_array($bucket->getTimeSlices(), true)) . " timeslices" . PHP_EOL;
echo $bucket->sliceCount() . " slice count" . PHP_EOL;
echo $bucket->nextTimeSliceCount() . " data ponts in next timeslice" . PHP_EOL;

$time_end = microtime(true);
echo "Generated $random_count time points in " .  ($time_end - $time_start) . " seconds" . PHP_EOL;

echo '**** Validate serialize/unserialize()' . PHP_EOL;
echo "Before Serialize - SliceCount: " . $bucket->sliceCount() . ", DataPoints: " . count($bucket) . ", NextSliceCount: " . $bucket->nextTimeSliceCount() . PHP_EOL;
$serialize = serialize($bucket);
$newBucket = unserialize($serialize);
echo "After Unserialize- SliceCount: " . $newBucket->sliceCount() . ", DataPoints: " . count($newBucket) . ", NextSliceCount: " . $newBucket->nextTimeSliceCount() . PHP_EOL;

//Validate next timeslice
echo '**** Validate nextTimeSlice()' . PHP_EOL;
['time' => $time, 'data' => $data] = $bucket->nextTimeSlice();
echo $time . PHP_EOL;
echo count($data) . PHP_EOL;

echo '**** Validate getTimeSlices()' . PHP_EOL;
foreach($bucket->getTimeSlices() as ['time' => $time, 'data' => $data])
{
    echo 'Slice ' . $time . " contains " . count($data) . " slices" . PHP_EOL;
}
