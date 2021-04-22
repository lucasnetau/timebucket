<?php declare(strict_types=1);

/*
 * This file is part of the TimeBucket package.
 *
 * (c) James Lucas <james@lucas.net.au>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EdgeTelemetrics\TimeBucket;

interface TimeOrderedStorageInterface {
    public function insert($value, $priority);
    public function count();
    public function current();
    public function compare($priority1, $priority2) : int;
    public function extract();
    public function top();
}