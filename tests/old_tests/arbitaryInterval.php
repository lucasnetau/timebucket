<?php declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

$times = [
    '2023-02-10 06:01:33',
    '2023-02-10 06:02:22',
    '2023-02-10 06:04:33',
    '2023-02-10 06:09:33',
    '2023-02-10 06:11:33',
    '2023-02-10 06:12:33',
    '2023-02-10 06:15:00',
    '2023-02-10 06:19:59',
];

$bucket = new \EdgeTelemetrics\TimeBucket\TimeBucket('5 minutes');

foreach ($times as $index => $time) {
    $time = new DateTimeImmutable($time);
    $bucket->insert($index, $time);
}

while (!$bucket->isEmpty()) {
    $slice = $bucket->extractTimeSlice();

    print_r($slice);
}
