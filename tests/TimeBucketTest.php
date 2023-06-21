<?php declare(strict_types=1);

use EdgeTelemetrics\TimeBucket\TimeBucket;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @covers \EdgeTelemetrics\TimeBucket\TimeBucket
 * @covers \EdgeTelemetrics\TimeBucket\TimeOrderedArray
 */
class TimeBucketTest extends TestCase
{
    public function testConstructorDefaultUTC() {
        $bucket = new TimeBucket();
        $this->assertEquals('UTC', $bucket->getTimezone()->getName());
    }

    public function testConstructorTakesTimezoneString() {
        $timezone = 'Australia/Sydney';
        $bucket = new TimeBucket('second', $timezone);
        $this->assertEquals($timezone, $bucket->getTimezone()->getName());
    }

    public function testConstructorTakesTimezoneObject() {
        $timezone = new DateTimeZone('Australia/Sydney');
        $bucket = new TimeBucket('second', $timezone);
        $this->assertEquals($timezone, $bucket->getTimezone());
    }

    public function testEmptyBucketIsEmpty() {
        $bucket = new TimeBucket();
        $this->assertTrue($bucket->isEmpty());
        $this->assertFalse($bucket->nextTimeSlice());
        $this->assertNull($bucket->getTimeSlices()->getReturn());

        $this->expectException(RuntimeException::class);
        $bucket->extractTimeSlice();
    }

    public function testEmptyBucketHasNoTimeIndex() {
        $bucket = new TimeBucket();
        $gen = $bucket->getTimeIndex();

        $this->assertNull($gen->getReturn());
    }

    public function testBucketAfterSingleInsert() {
        $bucket = new TimeBucket();
        $this->assertTrue($bucket->isEmpty());

        $bucket->insert('test', 1);
        $this->assertFalse($bucket->isEmpty());
        $this->assertEquals(1, $bucket->count());
        $this->assertEquals(1, $bucket->sliceCount());
        $this->assertEquals(1, $bucket->nextTimeSliceCount());
    }

    public function testNextTimeSliceReturnsOneSlice() {
        $bucket = new TimeBucket('unixtime');

        $now = time();
        $bucket->insert('correct', $now);
        $bucket->insert('incorrect', $now+1);

        ['time' => $timestamp, 'data' => $data] = $bucket->nextTimeSlice();

        $this->assertEquals(1, count($data));
    }

    public function testCorrectTimesliceRetrieval() {
        $bucket = new TimeBucket('unixtime');

        $now = time();
        $bucket->insert('slice1', $now);
        $bucket->insert('slice2-1', $now+1);
        $bucket->insert('slice2-2', $now+1);
        $bucket->insert('slice3', $now+2);

        $slices = iterator_to_array($bucket->getTimeSlices());

        $this->assertEquals(3, count($slices));
        $this->assertEquals(1, count($slices[0]['data']));
        $this->assertEquals(2, count($slices[1]['data']));
        $this->assertEquals(1, count($slices[2]['data']));

        $this->assertFalse($bucket->isEmpty()); //getTimeSlices is non-destructive
        $this->assertEquals(4, $bucket->count());
    }

    public function testCanInsertTimestampPriority() {
        $bucket = new TimeBucket('unixtime');

        $now = time();
        $bucket->insert('test', $now);

        ['time' => $timestamp, 'data' => $data] = $bucket->nextTimeSlice();

        $this->assertEquals($now, $timestamp);
    }

    public function testCanInsertDateTimePriority() {
        $bucket = new TimeBucket('unixtime');

        $now = new DateTime();
        $bucket->insert('test', $now);

        ['time' => $timestamp, 'data' => $data] = $bucket->nextTimeSlice();
        $this->assertEquals($now->format('U'), $timestamp);
    }

    public function testCanInsertDateTimeImmutablePriority() {
        $bucket = new TimeBucket('unixtime');

        $now = new DateTimeImmutable();
        $bucket->insert('test', $now);

        ['time' => $timestamp, 'data' => $data] = $bucket->nextTimeSlice();
        $this->assertEquals($now->format('U'), $timestamp);
    }

    public function testCanInsertDateTimeString() {
        $bucket = new TimeBucket('unixtime');

        $now = time();
        $bucket->insert('test', 'now');
        $then = time();

        ['time' => $timestamp, 'data' => $data] = $bucket->nextTimeSlice();

        $this->assertGreaterThanOrEqual($now, $timestamp);
        $this->assertLessThanOrEqual($then, $timestamp);
    }

    public function testConstructorSliceFormat() {
        $bucket = new TimeBucket('quarter');

        $this->assertEquals(TimeBucket::SLICE_FORMATS['quarter'], $bucket->getTimeFormat());
    }

    public function testMinuteIntervalsCanBeUser() {
        $bucket = new TimeBucket('5 minute');

        $time = new DateTimeImmutable('2023-01-01 12:31:00');

        $bucket->insert('test', $time);

        ['time' => $timestamp, 'data' => $data] = $bucket->nextTimeSlice();

        $this->assertEquals($time->format('Y-m-d H:30:00'), $timestamp);
    }

    public function testCorrectTimesliceExtractionToEmpty() {
        $bucket = new TimeBucket('unixtime');

        $now = time();
        $bucket->insert('slice1', $now);
        $bucket->insert('slice2-1', $now+1);
        $bucket->insert('slice2-2', $now+1);
        $bucket->insert('slice3', $now+2);

        $slices = [];
        while(!$bucket->isEmpty()) {
            $slices[] = $bucket->extractTimeSlice();
        }

        $this->assertEquals(3, count($slices));
        $this->assertEquals(1, count($slices[0]['data']));
        $this->assertEquals(2, count($slices[1]['data']));
        $this->assertEquals(1, count($slices[2]['data']));

        $this->assertTrue($bucket->isEmpty()); //extractTimeSlice is destructive
    }

    public function testBucketClonesNotLinked() {
        $bucket = new TimeBucket('unixtime');

        $bucket->insert('test', 1);

        $clone = clone $bucket;
        $clone->insert('abc', 2);
        $clone->extractTimeSlice();
        $clone->extractTimeSlice();

        $this->assertEquals(0, $clone->count());
        $this->assertEquals(1, $bucket->count());
    }

}