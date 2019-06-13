<?php declare(strict_types=1);

namespace EdgeTelemetrics\TimeBucket;

use \SplPriorityQueue;

class TimeOrderedQueue extends SplPriorityQueue {
    /**
     * @var int
     */
    protected $serial = PHP_INT_MIN;

    public function insert($datum, $priority)
    {
        if (! is_array($priority)) {
            $priority = [$priority, $this->serial++];
        }
        parent::insert($datum, $priority);
    }

    public function compare($priority1, $priority2)
    {
        if ($priority1 === $priority2) return 0;

        return $priority1 > $priority2 ? -1 : 1;
    }

    public function fixPriority($extract)
    {
        switch ($this->getExtractFlags()) {
            case self::EXTR_PRIORITY :
                $extract = $extract[0];
                break;
            case self::EXTR_BOTH :
                $extract['priority'] = $extract['priority'][0];
                break;
            case self::EXTR_DATA :
                break;
        }
        return $extract;
    }

    public function current()
    {
        $extract = parent::current();
        return $this->fixPriority($extract);
    }

    public function extract()
    {
        $extract = parent::extract();
        return $this->fixPriority($extract);
    }

    public function top()
    {
        $extract = parent::top();
        return $this->fixPriority($extract);
    }
}