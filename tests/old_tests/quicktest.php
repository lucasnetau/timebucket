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

use \EdgeTelemetrics\TimeBucket\TimeOrderedArray;
use \EdgeTelemetrics\TimeBucket\TimeOrderedQueue;

// Load Composer
require_once __DIR__ . '/../../vendor/autoload.php';

$spl = new TimeOrderedQueue();
$spl->insert("test c", 3);
$spl->insert("test b", 2);
$spl->insert("test a", 1);
$spl->insert("test b2", 2);

echo "TimeOrderedQueue" . PHP_EOL;
$spl->setExtractFlags(SplPriorityQueue::EXTR_DATA);
echo "Data Only: ";
print_r($spl->extract());
$spl->setExtractFlags(SplPriorityQueue::EXTR_PRIORITY);
echo PHP_EOL . "Priority Only: ";
print_r($spl->extract());
$spl->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
echo PHP_EOL . "Both: ";
print_r($spl->extract());
echo PHP_EOL . "Final: ";
print_r($spl->extract());

$bucket = new TimeOrderedArray();

$bucket->insert("test c", 3);
$bucket->insert("test b", 2);
$bucket->insert("test a", 1);
$bucket->insert("test b2", 2);

echo PHP_EOL . PHP_EOL . "TimeOrderedArray" . PHP_EOL;

$bucket->setExtractFlags(SplPriorityQueue::EXTR_DATA);
echo "Data Only: ";
print_r($bucket->extract());
$bucket->setExtractFlags(SplPriorityQueue::EXTR_PRIORITY);
echo PHP_EOL . "Priority Only: ";
print_r($bucket->extract());
$bucket->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
echo PHP_EOL . "Both: ";
print_r($bucket->extract());
echo PHP_EOL . "Final: ";
print_r($bucket->extract());

$bucket = new \EdgeTelemetrics\TimeBucket\TimeOrderedSqlite();

$bucket->insert("test c", 3);
$bucket->insert("test b", 2);
$bucket->insert("test a", 1);
$bucket->insert("test b2", 2);

echo PHP_EOL . PHP_EOL . "TimeOrderedSql" . PHP_EOL;

$bucket->setExtractFlags(SplPriorityQueue::EXTR_DATA);
echo "Data Only: ";
print_r($bucket->extract());
$bucket->setExtractFlags(SplPriorityQueue::EXTR_PRIORITY);
echo PHP_EOL . "Priority Only: ";
print_r($bucket->extract());
$bucket->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
echo PHP_EOL . "Both: ";
print_r($bucket->extract());
echo PHP_EOL . "Final: ";
print_r($bucket->extract());