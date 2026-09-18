<?php

namespace Wonder\App\Scheduler;

final class Context
{
    public const OUTPUT_LIMIT = 16384;
    private string $output = '';
    private array $secrets;
    public bool $externalProcess = false;
    public ?array $processMetrics = null;

    public function __construct(public readonly array $parameters, public readonly float $deadline)
    {
        $this->secrets = [];
        $user = function_exists('infoUser') ? infoUser('@system', 'username') : null;
        $token = (string) ($user->api_internal_user->token ?? '');
        if ($token !== '') { $this->secrets[] = $token; }
        array_walk_recursive($parameters, function ($value, $key): void {
            if (preg_match('/token|secret|password|api.?key/i', (string) $key) && is_scalar($value) && (string) $value !== '') {
                $this->secrets[] = (string) $value;
            }
        });
    }

    public function checkDeadline(): void
    {
        if (microtime(true) >= $this->deadline) { throw new \RuntimeException('Tempo massimo esaurito.'); }
    }

    public function redact(string $message): string
    {
        $message = str_replace($this->secrets, '[redacted]', $message);
        return preg_replace('/Bearer\s+[A-Za-z0-9_.-]+/i', 'Bearer [redacted]', $message) ?? '';
    }

    public function log(string $message): void
    {
        $this->output = substr($this->output.$this->redact($message), 0, self::OUTPUT_LIMIT);
    }

    public function output(): string { return $this->output; }
}
