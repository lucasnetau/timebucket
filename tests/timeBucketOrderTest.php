<?php declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', "on");

use \EdgeTelemetrics\TimeBucket\TimeBucket;

// Load Composer
require '../vendor/autoload.php';

$random_count = 4000;
$dates = [];

$time_start = microtime(true);

$bucket = new TimeBucket('second');

for($i = 0; $i < $random_count; $i++)
{
    $timestamp = mt_rand(time()-86400,time()+86400);
    $bucket->insert( "test $i", (new DateTimeImmutable())->setTimestamp($timestamp));
}

foreach($bucket->getTimeSlices() as $priority => $items)
{
    echo $priority . PHP_EOL;
    foreach($items as $item) {
       echo json_encode($item) . PHP_EOL;
    }
    echo PHP_EOL;
}

echo count($bucket) . " datapoints" . PHP_EOL;
echo count(iterator_to_array($bucket->getTimeSlices(), true)) . " timeslices" . PHP_EOL;
echo $bucket->sliceCount() . " slice count" . PHP_EOL;

$time_end = microtime(true);
echo "Generated $random_count time points in " .  ($time_end - $time_start) . " seconds" . PHP_EOL;