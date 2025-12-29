<?php

namespace Faktly\LaravelPrometheusMetrics\Metrics\Mail;

use Faktly\LaravelPrometheusMetrics\Contracts\Mail\MetricsStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class CacheMetricsStore implements MetricsStore
{
    public function __construct(
        private CacheRepository $cache,
        private string $prefix = 'prometheus_metrics:mail:'
    ) {
    }

    public function incrementSending(string $mailer): void
    {
        $this->cache->increment($this->prefix . 'sending_total');
    }

    public function incrementSent(string $mailer): void
    {
        $this->cache->increment($this->prefix . 'sent_total');
        $this->cache->increment($this->prefix . 'sent_per_mailer:' . $mailer);
    }

    public function incrementFailed(string $mailer): void
    {
        $this->cache->increment($this->prefix . 'failed_total');
    }

    public function getSendingTotal(): int
    {
        return (int)$this->cache->get($this->prefix . 'sending_total', 0);
    }

    public function getSentTotal(): int
    {
        return (int)$this->cache->get($this->prefix . 'sent_total', 0);
    }

    public function getFailedTotal(): int
    {
        return (int)$this->cache->get($this->prefix . 'failed_total', 0);
    }

    public function getSentPerMailer(): array
    {
        return [];
    }
}
