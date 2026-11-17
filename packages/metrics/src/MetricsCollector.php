<?php

declare(strict_types=1);

namespace Nex\Metrics;

/**
 * In-memory application metrics collector.
 *
 * Supports Counters, Gauges, and Histograms with label support.
 * Designed to avoid unbounded label cardinality (labels should be low-cardinality enums/categories).
 * Exports to Prometheus exposition format.
 */
final class MetricsCollector
{
    /**
     * @var array<string, array<string, float|int>> counters [metric_name => [label_key => value]]
     */
    private array $counters = [];

    /**
     * @var array<string, array<string, float|int>> gauges [metric_name => [label_key => value]]
     */
    private array $gauges = [];

    /**
     * @var array<string, array<string, list<float>>> histograms [metric_name => [label_key => samples]]
     */
    private array $histograms = [];

    /**
     * Increment a monotonic counter.
     *
     * @param array<string, string> $labels
     */
    public function incrementCounter(string $name, int $amount = 1, array $labels = []): void
    {
        $key = $this->formatLabels($labels);
        $this->counters[$name][$key] = ($this->counters[$name][$key] ?? 0) + $amount;
    }

    /**
     * Set a gauge value (can go up or down).
     *
     * @param array<string, string> $labels
     */
    public function setGauge(string $name, float|int $value, array $labels = []): void
    {
        $key = $this->formatLabels($labels);
        $this->gauges[$name][$key] = $value;
    }

    /**
     * Record a duration or observation in a histogram.
     *
     * @param array<string, string> $labels
     */
    public function observeHistogram(string $name, float $value, array $labels = []): void
    {
        $key = $this->formatLabels($labels);
        $this->histograms[$name][$key][] = $value;
    }

    /**
     * Get a counter's current value.
     */
    public function getCounter(string $name, array $labels = []): int
    {
        $key = $this->formatLabels($labels);
        return (int) ($this->counters[$name][$key] ?? 0);
    }

    /**
     * Get a gauge's current value.
     */
    public function getGauge(string $name, array $labels = []): float|int
    {
        $key = $this->formatLabels($labels);
        return $this->gauges[$name][$key] ?? 0;
    }

    /**
     * Get all histogram samples for a metric.
     *
     * @return list<float>
     */
    public function getHistogramSamples(string $name, array $labels = []): array
    {
        $key = $this->formatLabels($labels);
        return $this->histograms[$name][$key] ?? [];
    }

    /**
     * Export all metrics in Prometheus text exposition format.
     */
    public function toPrometheus(): string
    {
        $lines = [];

        // Counters
        foreach ($this->counters as $name => $values) {
            $lines[] = "# TYPE {$name} counter";
            foreach ($values as $labelKey => $val) {
                $lines[] = $name . $labelKey . ' ' . $val;
            }
        }

        // Gauges
        foreach ($this->gauges as $name => $values) {
            $lines[] = "# TYPE {$name} gauge";
            foreach ($values as $labelKey => $val) {
                $lines[] = $name . $labelKey . ' ' . $val;
            }
        }

        // Histograms (count & sum)
        foreach ($this->histograms as $name => $series) {
            $lines[] = "# TYPE {$name} histogram";
            foreach ($series as $labelKey => $samples) {
                $count = count($samples);
                $sum = array_sum($samples);
                $lines[] = "{$name}_count{$labelKey} {$count}";
                $lines[] = "{$name}_sum{$labelKey} " . sprintf('%.4f', $sum);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    public function reset(): void
    {
        $this->counters = [];
        $this->gauges = [];
        $this->histograms = [];
    }

    /**
     * @param array<string, string> $labels
     */
    private function formatLabels(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }

        $pairs = [];
        ksort($labels);
        foreach ($labels as $k => $v) {
            $pairs[] = sprintf('%s="%s"', $k, addcslashes($v, '"\\'));
        }

        return '{' . implode(',', $pairs) . '}';
    }
}
