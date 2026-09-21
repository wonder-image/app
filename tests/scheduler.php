<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Wonder\App\Scheduler\{ConfiguredTask, Context, Process, Repository, Task};

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
};
$task = Task::make('test.example', static fn (Context $context) => ['value' => $context->parameters['value']])
    ->named('Example')->schedule('*/5 * * * *')->maxSeconds(10)
    ->parameters(static fn (array $parameters): array => ['value' => (int) ($parameters['value'] ?? 0)]);
$check($task->run(new Context($task->validate(['value' => '42']), microtime(true) + 10)) === ['value' => 42], 'Callback parameters');
$check($task->enabled() === false, 'Custom tasks default to disabled');
try { $task->schedule('not a cron'); $check(false, 'Invalid cron accepted'); } catch (InvalidArgumentException) { $checks++; }
try { Repository::next('* * * * *', 'invalid/timezone'); $check(false, 'Invalid timezone accepted'); } catch (Exception) { $checks++; }
$date = Repository::next('0 12 * * 1-5', 'Europe/Rome');
$check(strtotime($date.' UTC') > time(), 'Next date is in future');
$context = new Context(['api_key' => 'private-value'], microtime(true) + 1);
$context->log('Bearer abc.def.ghi private-value'.str_repeat('x', 30000));
$check(!str_contains($context->output(), 'private-value') && !str_contains($context->output(), 'abc.def'), 'Secrets redacted');
$check(strlen($context->output()) === Context::OUTPUT_LIMIT, 'Output cap');
$reflection = new ReflectionClass(\Wonder\Api\Endpoint::class);
$endpoint = $reflection->newInstanceWithoutConstructor();
$reflection->getProperty('user')->setValue($endpoint, (object) ['username' => '@system']);
$check($endpoint->requireUsername('@system') === $endpoint, 'System API identity allowed');
$reflection->getProperty('user')->setValue($endpoint, (object) ['username' => 'other']);
try { $endpoint->requireUsername('@system'); $check(false, 'Other API identity accepted'); }
catch (\Wonder\Api\EndpointException $error) { $check($error->getCode() === 403, 'Other API identity denied'); }
$output = '';
$code = Process::run([PHP_BINARY, '-r', 'fwrite(STDOUT, $argv[1]); fwrite(STDERR, "err"); exit(7);', '$(echo injected)'], __DIR__, 3,
    static function ($chunk) use (&$output): void { $output .= $chunk; });
$check($code === 7 && str_contains($output, '$(echo injected)') && str_contains($output, 'err'), 'Exit code, stderr and shell bypass');
$start = microtime(true);
try { Process::run([PHP_BINARY, '-r', 'sleep(5);'], __DIR__, 1, static fn ($text) => null); $check(false, 'Timeout not enforced'); }
catch (RuntimeException $error) { $check(str_contains($error->getMessage(), 'Timeout') && microtime(true) - $start < 3, 'Timeout enforced'); }
$root = sys_get_temp_dir().'/wonder-custom-'.bin2hex(random_bytes(6));
mkdir($root);
$GLOBALS['ROOT'] = $root;
file_put_contents($root.'/job.php', '<?php echo json_encode(array_slice($argv, 1));');
try {
    $schedule = ['task_key' => 'custom.test', 'name' => 'Test', 'kind' => 'php', 'target' => 'job.php', 'timeout' => 2];
    $custom = ConfiguredTask::resolve($schedule);
    $args = ['sync', '--feed=1', 'a b', '$(touch unwanted)', '; exit 9'];
    $context = new Context($custom->validate($args), microtime(true) + 3);
    $check($custom->run($context) === ['exit_code' => 0] && json_decode($context->output(), true) === $args, 'PHP job preserves argument boundaries without shell');
    $check($context->externalProcess && $context->processMetrics === null, 'Unavailable child metrics are not worker metrics');
    foreach (['../outside.php', '/etc/passwd', 'php://input', 'missing.php'] as $target) {
        try { ConfiguredTask::script($target); $check(false, 'Unsafe script accepted'); }
        catch (InvalidArgumentException) { $checks++; }
    }
    $http = new ConfiguredTask(array_replace($schedule, ['kind' => 'https', 'target' => 'https://example.com/task', 'http_method' => 'POST']));
    $check($http->validate(['feed' => '1']) === ['feed' => '1'], 'Named HTTPS parameters');
    foreach (['http://example.com/task', 'file:///etc/passwd', 'https://user:pass@example.com/task'] as $target) {
        try { (new ConfiguredTask(array_replace($schedule, ['kind' => 'https', 'target' => $target])))->validate([]); $check(false, 'Unsafe URL accepted'); }
        catch (InvalidArgumentException) { $checks++; }
    }
    $resource = \Wonder\App\Resources\Scheduler\ScheduleResource::class;
    $input = ['name' => 'Custom', 'kind' => 'php', 'target' => 'job.php', 'arguments' => "sync\n--feed=1", 'expression' => '*/5 * * * *', 'timezone' => 'Europe/Rome', 'enabled' => 'false', 'timeout' => 5, 'origin' => 'code'];
    $saved = $resource::mutateRequestValues($input, 'store');
    $check($saved['origin'] === 'backend' && json_decode($saved['parameters'], true) === ['sync', '--feed=1'], 'Custom origin and args persisted');
    $check($resource::mutateFormValues($saved, 'edit')['arguments'] === "sync\n--feed=1", 'PHP args edit round trip');
    try { $resource::mutateRequestValues($input, 'update', 'backend', ['origin' => 'code', 'task_key' => 'wonder.sitemap']); $check(false, 'Code task replaced'); }
    catch (InvalidArgumentException) { $checks++; }
} finally { unlink($root.'/job.php'); rmdir($root); }
echo "$checks scheduler checks passed.\n";
