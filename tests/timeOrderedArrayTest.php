<?php declare(strict_types=1);

use EdgeTelemetrics\TimeBucket\TimeOrderedArray;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @covers \EdgeTelemetrics\TimeBucket\TimeOrderedArray
 */
class TimeOrderedArrayTest extends TestCase
{
    public function provideRandomDataset()
    {
        $dataset = [];
        $now = time();
        for($i = 0; $i < 20; $i++) {
            $dataset[] = [
                'priority' => mt_rand($now - 86400, $now + 86400),
                'data' => bin2hex(random_bytes(5)),
            ];
        }

        return [
            'dataset' => [$dataset]
        ];
    }
    public function testQueueAfterSingleInsert() {
        $queue = new TimeOrderedArray();

        $queue->insert('test', 1);
        $this->assertEquals(1, $queue->count());
        $this->assertEquals(1, $queue->peekSetCount());
        $this->assertEquals('test', $queue->current());
    }

    public function testPriorityCountAfterInsert() {
        $queue = new TimeOrderedArray();

        $queue->insert('test', 1);
        $queue->insert('test', 2);
        $queue->insert('test', 3);
        $queue->insert('test', 3);
        $queue->insert('test', 4);
        $this->assertEquals(5, $queue->count());
        $this->assertEquals(1, $queue->peekSetCount());
        $this->assertEquals(4, $queue->priorityCount());
    }

    /**
     * @dataProvider provideRandomDataset
     */
    public function testQueueExtractionInOrder($dataset) {
        $queue = new TimeOrderedArray();
        $queue->setExtractFlags(SplPriorityQueue::EXTR_PRIORITY);

        foreach($dataset as ['data' => $data, 'priority' => $priority]) {
            $queue->insert($data, $priority);
        }

        $this->assertEquals(count($dataset), $queue->count());

        $prevPriority = $queue->top();
        $extracted = 0;
        while (!$queue->isEmpty()) {
            $priority = $queue->extract();
            $this->assertGreaterThanOrEqual($prevPriority, $priority);
            ++$extracted;
        }

        $this->assertEquals(count($dataset), $extracted);
        $this->assertEquals(0, $queue->count());
    }

    /**
     * @dataProvider provideRandomDataset
     */
    public function testQueueIterationInOrder($dataset) {
        $queue = new TimeOrderedArray();
        $queue->setExtractFlags(SplPriorityQueue::EXTR_PRIORITY);

        foreach($dataset as ['data' => $data, 'priority' => $priority]) {
            $queue->insert($data, $priority);
        }

        $this->assertEquals(count($dataset), $queue->count());

        $prevPriority = $queue->top();
        $extracted = 0;
        foreach($queue as $index => $priority) {
            $this->assertGreaterThanOrEqual($prevPriority, $priority);
            ++$extracted;
        }

        $this->assertEquals(count($dataset), $extracted);
        $this->assertEquals(0, $queue->count());
    }

    public function testEmptyQueueIsEmpty() {
        $queue = new TimeOrderedArray();

        $this->assertFalse($queue->valid());
        $this->assertTrue($queue->isEmpty());
        $this->assertEquals(0, $queue->peekSetCount());
    }

    public function testExtractEmptyQueueReturnsFalse() {
        $queue = new TimeOrderedArray();

        $this->assertFalse($queue->extract());
        $this->assertFalse($queue->top());
    }

    public function testQueueClonesNotLinked() {
        $queue = new TimeOrderedArray();

        $queue->insert('test', 1);

        $clone = clone $queue;
        $clone->insert('abc', 2);
        $clone->extract();
        $clone->extract();

        $this->assertEquals(0, $clone->count());
        $this->assertEquals(1, $queue->count());
    }

    public function testCanChangeExtractFlags() {
        $queue = new TimeOrderedArray();

        $this->assertEquals(SplPriorityQueue::EXTR_DATA, $queue->getExtractFlags());
        $queue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $this->assertEquals(SplPriorityQueue::EXTR_BOTH, $queue->getExtractFlags());
    }

}