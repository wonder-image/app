<?php

namespace Wonder\App\Scheduler;

/** Admin-configured jobs reuse the worker, locking and execution log pipeline. */
final class ConfiguredTask extends AbstractTask
{
    public function __construct(private array $schedule) {}
    public function key(): string { return $this->schedule['task_key']; }
    public function label(): string { return $this->schedule['name']; }
    public function timeout(): int { return max(1, min(3600, (int) ($this->schedule['timeout'] ?? 300))); }

    public static function resolve(array $schedule): Contracts\TaskInterface
    {
        return ($schedule['kind'] ?? 'task') === 'task'
            ? TaskRegistry::get($schedule['task_key']) : new self($schedule);
    }

    public function validate(array $parameters): array
    {
        $kind = $this->schedule['kind'] ?? '';
        $target = trim((string) ($this->schedule['target'] ?? ''));
        if ($kind === 'php') {
            self::script($target);
            if (!array_is_list($parameters)) { throw new \InvalidArgumentException('Gli argomenti PHP devono essere una lista.'); }
            foreach ($parameters as $argument) {
                if (!is_string($argument) || str_contains($argument, "\0")) { throw new \InvalidArgumentException('Argomento PHP non valido.'); }
            }
        } elseif ($kind === 'https') {
            $url = parse_url($target);
            if (!filter_var($target, FILTER_VALIDATE_URL) || ($url['scheme'] ?? '') !== 'https'
                || isset($url['user']) || isset($url['pass']) || isset($url['fragment'])) {
                throw new \InvalidArgumentException('Inserire un URL HTTPS senza credenziali o frammenti.');
            }
            if (!in_array($this->schedule['http_method'] ?? 'GET', ['GET', 'POST'], true)) { throw new \InvalidArgumentException('Metodo HTTP non valido.'); }
            foreach ($parameters as $key => $value) {
                if (!is_string($key) || !is_scalar($value)) { throw new \InvalidArgumentException('I parametri HTTPS devono avere un nome e un valore semplice.'); }
            }
        } else { throw new \InvalidArgumentException('Tipo di attivita non valido.'); }
        return $parameters;
    }

    public static function script(string $target): string
    {
        $root = rtrim((string) ($GLOBALS['ROOT'] ?? ''), '/');
        if ($target === '' || str_starts_with($target, '/') || str_contains($target, "\0")
            || preg_match('~(^|/)\.\.(/|$)|^[a-z]+:~i', $target)) {
            throw new \InvalidArgumentException('Indicare un percorso relativo alla cartella del sito.');
        }
        $path = realpath($root.'/'.$target);
        // Composer path repositories may legitimately be symlinked outside the site.
        $allowed = [realpath($root)];
        if (str_starts_with($target, 'vendor/')) { $allowed[] = realpath($root.'/'.implode('/', array_slice(explode('/', $target), 0, 3))); }
        $inside = false;
        foreach ($allowed as $base) { if ($base && $path && str_starts_with($path, $base.'/')) { $inside = true; } }
        if (!$inside || !$path || !is_file($path) || !is_readable($path)
            || !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['', 'php'], true)) {
            throw new \InvalidArgumentException('Script PHP non trovato nel sito o nei pacchetti Composer.');
        }
        return $path;
    }

    public function run(Context $context): array
    {
        if ($this->schedule['kind'] === 'php') {
            $context->externalProcess = true;
            $exit = Process::run([PHP_BINARY, self::script($this->schedule['target']), ...$context->parameters],
                $GLOBALS['ROOT'], $this->timeout(), $context->log(...));
            if ($exit !== 0) { throw new \RuntimeException('Lo script PHP ha restituito il codice '.$exit.'.'); }
            return ['exit_code' => $exit];
        }
        if (!function_exists('curl_init')) { throw new \RuntimeException('Estensione cURL non disponibile.'); }
        $context->externalProcess = true;
        $url = $this->schedule['target'];
        $method = $this->schedule['http_method'] ?? 'GET';
        $query = http_build_query($context->parameters, '', '&', PHP_QUERY_RFC3986);
        if ($method === 'GET' && $query !== '') { $url .= (str_contains($url, '?') ? '&' : '?').$query; }
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(15, $this->timeout()), CURLOPT_TIMEOUT => $this->timeout(),
            CURLOPT_WRITEFUNCTION => static function ($handle, string $data) use ($context): int { $context->log($data); return strlen($data); }]);
        if ($method === 'POST') { curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $query]); }
        try {
            $ok = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($ok === false) { throw new \RuntimeException('Richiesta HTTPS fallita (cURL '.curl_errno($curl).').'); }
            if ($status < 200 || $status >= 300) { throw new \RuntimeException('Risposta HTTPS non riuscita: HTTP '.$status.'.'); }
            return ['http_status' => $status];
        } finally { unset($curl); }
    }
}
