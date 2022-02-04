<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Rabbit\Base\App;
use Rabbit\Base\Helper\ExceptionHelper;
use Rabbit\Base\Helper\VarDumper;
use Rabbit\Web\DispatcherInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Process;
use Swoole\Server\Task;
use Swoole\WebSocket\Frame;
use Throwable;

/**
 * Class Server
 * @package Rabbit\Server
 */
abstract class Server
{
    use ServerTrait;

    protected int $type = SWOOLE_PROCESS;
    protected ?DispatcherInterface $dispatcher = null;
    public ?AbstractPipeMsg $pipeHandler = null;
    protected \Swoole\Server $swooleServer;
    public ?AbstractTask $taskHandle = null;

    public function getSwooleServer(): \Swoole\Server
    {
        return $this->swooleServer;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function start(): void
    {
        $this->startServer($this->swooleServer = $this->createServer());
    }

    abstract protected function createServer(): \Swoole\Server;

    protected function startServer(\Swoole\Server $server): void
    {
        ServerHelper::setServer($this);
        $server->on('start', [$this, 'onStart']);
        $server->on('shutdown', [$this, 'onShutdown']);

        $server->on('managerStart', [$this, 'onManagerStart']);

        $server->on('workerStart', [$this, 'onWorkerStart']);
        $server->on('workerStop', [$this, 'onWorkerStop']);
        $server->on('workerError', [$this, 'onWorkerError']);
        $server->on('pipeMessage', [$this, 'onPipeMessage']);

        if ($this->taskHandle !== null && !isset($this->setting['task_worker_num'])) {
            $this->setting['task_worker_num'] = swoole_cpu_num();
        }

        if (isset($this->setting['task_worker_num']) && $this->setting['task_worker_num'] > 0) {
            if (isset($this->setting['task_enable_coroutine']) && $this->setting['task_enable_coroutine']) {
                $server->on('task', [$this, 'onTaskCo']);
            } else {
                $server->on('task', [$this, 'onTask']);
            }
            $server->on('finish', [$this, 'onFinish']);
        }
        $server->set($this->setting);
        $this->beforeStart();
    }

    protected function setProcessTitle(string $name): void
    {
        if (function_exists('swoole_set_process_name')) {
            @swoole_set_process_name($name);
        } else {
            @cli_set_process_title($name);
        }
    }

    public function stop(): void
    {
        if ($this->setting['pid_file'] && is_file($this->setting['pid_file'])) {
            $pid = file_get_contents($this->setting['pid_file']);
            Process::kill(intval($pid));
        }
    }

    public function onStart(\Swoole\Server $server): void
    {
        $this->setProcessTitle($this->name . ': master');
        if (isset($server->setting['pid_file'])) {
            @file_put_contents($server->setting['pid_file'], $server->master_pid);
        }
    }

    public function onShutdown(\Swoole\Server $server): void
    {
        if (isset($server->setting['pid_file']) && $server->setting['pid_file']) {
            unlink($server->setting['pid_file']);
        }
    }

    public function onWorkerStart(\Swoole\Server $server, int $worker_id): void
    {
        $this->workerStart($worker_id, $server->taskworker);
    }

    public function onWorkerStop(\Swoole\Server $server, int $worker_id): void
    {
    }

    public function onConnect(\Swoole\Server $server, int $fd, int $from_id): void
    {
    }

    public function onReceive(\Swoole\Server $server, int $fd, int $reactor_id, string $data): void
    {
    }

    public function onRequest(Request $request, Response $response): void
    {
    }

    public function onMessage(\Swoole\WebSocket\Server $server, Frame $frame): void
    {
    }

    public function onOpen(\Swoole\WebSocket\Server $server, Request $request): void
    {
    }

    public function onHandShake(Request $request, Response $response): bool
    {
    }

    public function onPacket(\Swoole\Server $server, string $data, array $client_info): void
    {
    }

    public function onClose(\Swoole\Server $server, int $fd, int $from_id): void
    {
    }

    public function onTask(\Swoole\Server $server, int $task_id, int $from_id, $data)
    {
        try {
            $result = $this->taskHandle->handle($task_id, $from_id, $data);
            return $result === null ? '' : $result;
        } catch (Throwable $throwable) {
            App::error(
                VarDumper::getDumper()->dumpAsString(ExceptionHelper::convertExceptionToArray($throwable)),
                'Task'
            );
            return $throwable->getMessage();
        }
    }

    public function onTaskCo(\Swoole\Server $server, Task $task)
    {
        try {
            $result = $this->taskHandle->handle($task->id, $task->worker_id, $task->data);
            $task->finish($result === null ? '' : $result);
        } catch (Throwable $throwable) {
            App::error(
                VarDumper::getDumper()->dumpAsString(ExceptionHelper::convertExceptionToArray($throwable)),
                'Task'
            );
            $task->finish($throwable->getMessage());
        }
    }

    public function onFinish(\Swoole\Server $server, int $task_id, string $data): void
    {
        $this->taskHandle->finish($server, $task_id, $data);
    }

    public function onPipeMessage(\Swoole\Server $server, int $from_worker_id, string $message): void
    {
        $this->pipeHandler && $this->pipeHandler->handle($server, $from_worker_id, $message);
    }

    public function onWorkerError(\Swoole\Server $server, int $worker_id, int $worker_pid, int $exit_code): void
    {
    }

    public function onManagerStart(\Swoole\Server $server): void
    {
        $this->setProcessTitle($this->name . ': manager');
    }

    public function onManagerStop(\Swoole\Server $server): void
    {
    }
}
