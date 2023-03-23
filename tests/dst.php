<?php

$date = new DateTimeImmutable('2021-04-04T01:00:00', new DateTimeZone('Australia/Sydney'));

for($i = 0; $i < 180; $i++) {
    $newdate = $date->add(new DateInterval('PT' . $i . 'M'));
    echo $newdate->format('H:i:s T') . ' / ' . $newdate->format('c') . ' : ' . $newdate->setTimezone(new DateTimeZone('UTC'))->format('c') . PHP_EOL;
}

echo PHP_EOL . PHP_EOL . "*******" . PHP_EOL . PHP_EOL;

$date = new DateTimeImmutable('2021-04-04 02:00:00', new DateTimeZone('Australia/Sydney'));
echo $date->format('H:i:s T') . ' / ' . $date->format('c') . ' : ' . $date->setTimezone(new DateTimeZone('UTC'))->format('c') . PHP_EOL;

$date = new DateTimeImmutable('2021-04-04 02:00:00 DST', new DateTimeZone('Australia/Sydney'));
echo $date->format('H:i:s T') . ' / ' . $date->format('c') . ' : ' . $date->setTimezone(new DateTimeZone('UTC'))->format('c') . PHP_EOL;

$date = new DateTimeImmutable('2021-04-04 02:00:00 DT', new DateTimeZone('Australia/Sydney'));
echo $date->format('H:i:s T') . ' / ' . $date->format('c') . ' : ' . $date->setTimezone(new DateTimeZone('UTC'))->format('c') . PHP_EOL;
