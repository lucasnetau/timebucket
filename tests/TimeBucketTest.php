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
        $this->assertEquals(1, $bucket->currentTimeSliceCount());
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

    public function testMinuteIntervalsCanBeUsed() {
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

    /**
     * Verify that a “3 hour” interval groups timestamps correctly.
     */
    public function testHourIntervalBuckets(): void
    {
        $bucket = new TimeBucket('3 hour');

        // 01:10 and 02:45 should fall into the 00:00‑03:00 slice
        $bucket->insert('a', new DateTimeImmutable('2023-01-01 01:10:00'));
        $bucket->insert('b', new DateTimeImmutable('2023-01-01 02:45:00'));

        // 04:00 should fall into the next slice (03:00‑06:00)
        $bucket->insert('c', new DateTimeImmutable('2023-01-01 04:00:00'));

        $slices = iterator_to_array($bucket->getTimeSlices());

        // Expect two slices
        $this->assertCount(2, $slices);

        // First slice timestamp should be rounded to the start of the 3‑hour bucket
        $this->assertEquals('2023-01-01 00:00:00', $slices[0]['time']);
        $this->assertCount(2, $slices[0]['data']);
        $this->assertContains('a', $slices[0]['data']);
        $this->assertContains('b', $slices[0]['data']);

        // Second slice
        $this->assertEquals('2023-01-01 03:00:00', $slices[1]['time']);
        $this->assertCount(1, $slices[1]['data']);
        $this->assertContains('c', $slices[1]['data']);
    }

    /**
     * Verify that a “2 day” interval groups dates correctly.
     */
    public function testDayIntervalBuckets(): void
    {
        $bucket = new TimeBucket('2 day');

        // 2023‑01‑01 and 2023‑01‑02 belong to the first 2‑day slice
        $bucket->insert('x', new DateTimeImmutable('2023-01-01 12:00:00'));
        $bucket->insert('y', new DateTimeImmutable('2023-01-02 23:59:59'));

        // 2023‑01‑03 starts the next slice
        $bucket->insert('z', new DateTimeImmutable('2023-01-03 00:00:00'));

        $slices = iterator_to_array($bucket->getTimeSlices());

        $this->assertCount(2, $slices);
        $this->assertEquals('2023-01-01', $slices[0]['time']);
        $this->assertCount(2, $slices[0]['data']);
        $this->assertEquals('2023-01-03', $slices[1]['time']);
        $this->assertCount(1, $slices[1]['data']);
    }

    /**
     * Verify that a “2 week” interval groups ISO weeks correctly.
     */
    public function testWeekIntervalBuckets(): void
    {
        $bucket = new TimeBucket('2 week');

        // 2023‑01‑02 is Monday of ISO week 1
        $bucket->insert('a', new DateTimeImmutable('2023-01-02 10:00:00'));
        // Same week (still week 1)
        $bucket->insert('b', new DateTimeImmutable('2023-01-07 22:00:00'));

        // Week 3 (the next 2‑week bucket)
        $bucket->insert('c', new DateTimeImmutable('2023-01-16 05:00:00'));

        $slices = iterator_to_array($bucket->getTimeSlices());

        $this->assertCount(2, $slices);
        // First slice should be the week-1
        $this->assertEquals('2023-01', $slices[0]['time']);
        $this->assertCount(2, $slices[0]['data']);
        // Second slice should be the week-3
        $this->assertEquals('2023-03', $slices[1]['time']);
        $this->assertCount(1, $slices[1]['data']);
    }

    /**
     * Verify that a “3 month” interval groups months correctly.
     */
    public function testMonthIntervalBuckets(): void
    {
        $bucket = new TimeBucket('3 month');

        // Jan and Feb belong to the first 3‑month bucket (starting Jan)
        $bucket->insert('jan', new DateTimeImmutable('2023-01-15'));
        $bucket->insert('feb', new DateTimeImmutable('2023-02-28'));

        // Apr belongs to the next bucket (starting Apr)
        $bucket->insert('apr', new DateTimeImmutable('2023-04-01'));

        $slices = iterator_to_array($bucket->getTimeSlices());

        $this->assertCount(2, $slices);
        $this->assertEquals('2023-01', $slices[0]['time']);
        $this->assertCount(2, $slices[0]['data']);
        $this->assertEquals('2023-04', $slices[1]['time']);
        $this->assertCount(1, $slices[1]['data']);
    }

    /**
     * Verify that a “5 year” interval groups years correctly.
     */
    public function testYearIntervalBuckets(): void
    {
        $bucket = new TimeBucket('5 year');

        // 2021 and 2023 belong to the 2020‑2024 bucket (starting 2020)
        $bucket->insert('2021', new DateTimeImmutable('2021-06-01'));
        $bucket->insert('2023', new DateTimeImmutable('2023-11-30'));

        // 2026 belongs to the next bucket (starting 2025)
        $bucket->insert('2026', new DateTimeImmutable('2026-01-01'));

        $slices = iterator_to_array($bucket->getTimeSlices());

        $this->assertCount(2, $slices);
        $this->assertEquals('2020', $slices[0]['time']);
        $this->assertCount(2, $slices[0]['data']);
        $this->assertEquals('2025', $slices[1]['time']);
        $this->assertCount(1, $slices[1]['data']);
    }

}