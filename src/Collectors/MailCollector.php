<?php

namespace Faktly\LaravelPrometheusMetrics\Collectors;

use Faktly\LaravelPrometheusMetrics\Contracts\Mail\MetricsStore;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use stdClass;
use Throwable;

class MailCollector extends BaseCollector
{
    public function collect(): array
    {
        if (!$this->isEnabled('mail')) {
            return [];
        }

        try {
            // Always compute config-derived metrics first
            $defaultMailer = Config::get('mail.default', 'smtp');
            $mailersConfig = Config::get('mail.mailers', []);
            $defaultConfig = Arr::get($mailersConfig, $defaultMailer, []);
            $defaultTransport = Arr::get($defaultConfig, 'transport', 'smtp');
            $supportsFailover = $defaultTransport === 'failover';
            $failoverMailers = $supportsFailover
                ? (array)Arr::get($defaultConfig, 'mailers', [])
                : [];
            $mailers = $this->formatMailers($mailersConfig);
            $payload = [
                'default_mailer'    => $defaultMailer,
                'default_transport' => $defaultTransport,
                'supports_failover' => $supportsFailover,
                'failover_mailers'  => $failoverMailers,
                'mailers'           => $mailers,
            ];

            if (Config::get('prometheus-metrics.collectors.config.mail.track_runtime', false)) {
                /** @var MetricsStore $store */
                $store = app(MetricsStore::class);

                $payload['counters'] = [
                    'sending_total' => $store->getSendingTotal(),
                    'sent_total'    => $store->getSentTotal(),
                    'failed_total'  => $store->getFailedTotal(),
                ];
            } else {
                $payload['counters'] = new stdClass();
            }

            return $payload;
        } catch (Throwable $e) {
            $this->handleException('MailCollector', $e);

            return [
                'default_mailer'    => Config::get('mail.default', 'smtp'),
                'default_transport' => null,
                'supports_failover' => false,
                'failover_mailers'  => [],
                'mailers'           => new stdClass(),
                'counters'          => new stdClass(),
                'error'             => $e->getMessage(),
            ];
        }
    }

    protected function isEnabled($feature): bool
    {
        return (bool)Config::get('prometheus-metrics.collectors.config.mail.enabled', true);
    }

    /**
     * Format information about all configured mailers.
     *
     * @param array<string, mixed> $mailersConfig
     *
     * @return array<string, array<string, mixed>>|stdClass
     */
    private function formatMailers(array $mailersConfig)
    {
        if ($mailersConfig === []) {
            return new stdClass();
        }

        $result = [];

        foreach ($mailersConfig as $name => $config) {
            $transport = Arr::get($config, 'transport', 'smtp');

            $isFailover = $transport === 'failover';
            $failoverMailers = $isFailover
                ? (array)Arr::get($config, 'mailers', [])
                : [];

            $result[$name] = [
                'transport'        => $transport,
                'is_failover'      => $isFailover,
                'failover_mailers' => $failoverMailers,
                'host'             => Arr::get($config, 'host'),
                'port'             => Arr::get($config, 'port'),
                'encryption'       => Arr::get($config, 'encryption'),
                'username'         => Arr::get($config, 'username'),
            ];
        }

        return $result ?: new stdClass();
    }

    public function getName(): string
    {
        return 'mail';
    }
}
