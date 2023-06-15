# Time Bucket

Not all events and measurements occur at the exact moment, however we may wish to correlate between these events by grouping them into arbitary time slices. TimeBucket allows you to aggregate data into slices of time (eg 5 minutes, by day etc) and then retrieve data via a time ordered queue.

TimeBuckets can be used to estimate the interval between slices of data and identify any time slices that are missing data.

## Requirements

The package is compatible with PHP 8.0+

## Installation

You can add the library as project dependency using [Composer](https://getcomposer.org/):

```sh
composer require edgetelemetrics/timebucket
```

## Examples
TBC. See tests/ for now

## License

MIT, see [LICENSE file](LICENSE).

### Contributing

Bug reports (and small patches) can be submitted via the [issue tracker](https://github.com/lucasnetau/timebucket/issues). Forking the repository and submitting a Pull Request is preferred for substantial patches.
