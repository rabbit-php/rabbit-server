<?php

declare(strict_types=1);

namespace Rabbit\Server;

use DI\DependencyException;
use DI\NotFoundException;
use Rabbit\Base\App;
use ReflectionException;

/**
 * Trait ServerTrait
 * @package Rabbit\Server
 */
trait ServerTrait
{
    protected string $name = 'Rabbit';
    protected string $host = '0.0.0.0';
    protected int $port = 80;
    protected bool $ssl = false;
    protected array $beforeStart = [];
    protected array $workerExit = [];
    protected array $workerStart = [];
    protected array $processes = [];
    protected array $setting = [];

    public function __construct(array $setting = [], array $coSetting = [])
    {
        $this->setting = array_merge([
            'worker_num' => swoole_cpu_num(),
            'dispatch_mode' => 1,
            'log_file' => sys_get_temp_dir() . '/runtime/swoole_web.log',
            'daemonize' => 0,
            'pid_file' => sys_get_temp_dir() . '/runtime/swooleweb.pid',
            'enable_reuse_port' => true,
            'http_parse_post' => true,
            'max_coroutine' => 1000000,
            'reload_async' => true,
        ], $setting);
        \Co::set(array_merge([
            'hook_flags' => SWOOLE_HOOK_ALL,
            'enable_preemptive_scheduler' => false
        ], $coSetting));
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    protected function beforeStart(): void
    {
        foreach ($this->beforeStart as $handle) {
            if (!$handle instanceof BootInterface) {
                $handle = create($handle);
            }
            $handle->handle();
        }
    }

    protected function workerStart(int $workerId, bool $isTask = false): void
    {
        App::$id = $workerId;
        foreach ($this->workerStart as $handle) {
            if (!$handle instanceof WorkerHandlerInterface) {
                /**
                 * @var WorkerHandlerInterface $handle
                 */
                $handle = create($handle);
            }
            $handle->handle($workerId);
        }
        if ($isTask) {
            $this->setProcessTitle($this->name . ': task' . ": {$workerId}");
        } else {
            $this->setProcessTitle($this->name . ': worker' . ": {$workerId}");
        }
    }

    protected function onWorkerExit(int $workerId): void
    {
        foreach ($this->workerExit as $handle) {
            if (!$handle instanceof WorkerHandlerInterface) {
                $handle = create($handle);
            }
            $handle->handle($workerId);
        }
    }
}
