<?php declare(strict_types=1);

use EdgeTelemetrics\TimeBucket\TimeBuffer;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @covers \EdgeTelemetrics\TimeBucket\TimeBuffer
 * @covers \EdgeTelemetrics\TimeBucket\TimeBucket
 * @covers \EdgeTelemetrics\TimeBucket\TimeOrderedArray
 */
class TimeBufferTest extends TestCase
{
    public function testSpillBuffer() {
        $ready = fn(string $time, array $data, \EdgeTelemetrics\TimeBucket\TimeBucket $bucket): bool => count($data) >= 2;
        $process = function(string $time, array $data) {
            $this->assertCount(2, $data);
        };

        $buffer = new TimeBuffer(
            'minute',
            'UTC',
            $ready,
            $process,
        );

        for ($i = 0; $i < 2; $i++) {
            $buffer->insert(['value' => $i], '2026-03-28 12:00:00');
        }

        $this->assertTrue($buffer->isEmpty());
    }

}