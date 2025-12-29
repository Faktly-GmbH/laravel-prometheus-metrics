<?php

namespace Faktly\LaravelPrometheusMetrics\Metrics\Mail;

use Faktly\LaravelPrometheusMetrics\Contracts\Mail\MetricsStore;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;

class MetricsSubscriber
{
    public function __construct(private MetricsStore $store)
    {
    }

    public function onSending(MessageSending $event): void
    {
        // $event->data may contain mailer info depending on version and usage.
        // We keep it generic and default to configured mailer.
        $mailer = config('mail.default', 'smtp');

        $this->store->incrementSending($mailer);
    }

    public function onSent(MessageSent $event): void
    {
        $mailer = config('mail.default', 'smtp');

        $this->store->incrementSent($mailer);
    }

    public function subscribe($events): void
    {
        $events->listen(MessageSending::class, [self::class, 'onSending']);
        $events->listen(MessageSent::class, [self::class, 'onSent']);
    }
}
