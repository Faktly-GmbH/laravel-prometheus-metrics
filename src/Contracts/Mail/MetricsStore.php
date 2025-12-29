<?php

namespace Faktly\LaravelPrometheusMetrics\Contracts\Mail;

interface MetricsStore
{
    public function incrementSending(string $mailer): void;

    public function incrementSent(string $mailer): void;

    public function getSendingTotal(): int;

    public function getSentTotal(): int;

    public function incrementFailed(string $mailer): void;

    public function getFailedTotal(): int;

    /**
     * @return array<string,int>
     */
    public function getSentPerMailer(): array;
}
