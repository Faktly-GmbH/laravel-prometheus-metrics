# Security Policy

## Supported Versions

The following versions receive security updates:

| Version | Status              | Supported until |
|---------|---------------------|-----------------|
| 1.0.x   | Supported           | 2026‑12‑25      |
| < 1.0   | Not supported (EOL) | –               |

Only the latest patch of each supported minor is eligible for security fixes.

## Reporting a Vulnerability

Do **not** open public issues for security problems.

Instead, report vulnerabilities via email:

- Email: `security@faktly.ch`
- Subject: `Security issue: laravel-prometheus-metrics`

Please include:

- A clear description of the vulnerability.
- Steps to reproduce (code, configuration, requests).
- A description of the impact (what can an attacker do).
- Any suggested mitigation or patch, if you have one.

You will normally receive an initial response within 48 hours. If you do not receive a response in that time frame, please resend your report.

Please do **not** publicly disclose the vulnerability until:

- A fix has been developed and released, and
- A security advisory has been published.

## Security Best Practices for Users

For production deployments:

1. **Protect the metrics endpoint**

    - Require a strong token:
      ```
      PROMETHEUS_METRICS_AUTH_ENABLED=true
      PROMETHEUS_METRICS_TOKEN=your-64-char-random-string
      ```
    - Generate via:
      ```
      php -r "echo bin2hex(random_bytes(64));"
      ```

2. **Restrict network access**

    - Expose the metrics endpoint only on internal networks or behind a VPN or reverse proxy.
    - Use firewall rules, security groups, or ingress rules to allow only monitoring systems.

3. **Force HTTPS**

    - Terminate TLS at your load balancer or reverse proxy.
    - Never expose metrics over plain HTTP on the public internet.

4. **Avoid logging sensitive data**

    - Do not log tokens, secrets, or raw headers containing the auth token.
    - Ensure application logs and access logs are access‑controlled.

5. **Keep dependencies updated**

    - Regularly update Laravel, PHP, and this package:
      ```
      composer update
      ```
    - Run:
      ```
      composer audit
      ```

6. **Limit data exposed**

    - Disable collectors you do not need via configuration.
    - Avoid exposing identifying user data or PII through custom metrics.

## Security Best Practices for Contributors

When contributing code:

- Use constant‑time comparison for secrets
