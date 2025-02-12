<?php

namespace Icinga\Module\Perfdatagraphsgraphite\Client;

/**
 * GraphiteRecord represents a single CSV line
 */
class GraphiteRecord
{
    public string $seriesname;
    public string $metricname;
    public int $timestamp;
    public ?float $value;

    public function __construct(string $seriesname, string $metricname, int $timestamp, ?float $value)
    {
        $this->seriesname = $seriesname;
        $this->metricname = $metricname;
        $this->timestamp = $timestamp;
        $this->value = $value;
    }

    public function getSeriesName(): string
    {
        return $this->seriesname;
    }

    public function getMetricName(): string
    {
        return $this->metricname;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }
}
