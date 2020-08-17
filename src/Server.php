<?php
declare(strict_types=1);

namespace Rabbit\Server;

use DI\DependencyException;
use DI\NotFoundException;
use Exception;
use Rabbit\Base\App;
use Rabbit\Base\Helper\ExceptionHelper;
use Rabbit\Base\Helper\VarDumper;
use Rabbit\Web\DispatcherInterface;
use ReflectionException;
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

    /**
     * @return \Swoole\Server
     */
    public function getSwooleServer(): \Swoole\Server
    {
        return $this->swooleServer;
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     *
     */
    public function start(): void
    {
        $this->startServer($this->swooleServer = $this->createServer());
    }

    /**
     * @return \Swoole\Server
     */
    abstract protected function createServer(): \Swoole\Server;

    /**
     * @param \Swoole\Server|null $server
     * @throws DependencyException
     * @throws NotFoundException|ReflectionException
     */
    protected function startServer(\Swoole\Server $server = null): void
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

    /**
     * @param string $name
     */
    protected function setProcessTitle(string $name): void
    {
        if (function_exists('swoole_set_process_name')) {
            @swoole_set_process_name($name);
        } else {
            @cli_set_process_title($name);
        }
    }

    /**
     *
     */
    public function stop(): void
    {
        if ($this->swooleServer->setting['pid_file']) {
            $pid = file_get_contents($this->swooleServer->setting['pid_file']);
            Process::kill(intval($pid));
        }
    }

    /**
     * @param \Swoole\Server $server
     */
    public function onStart(\Swoole\Server $server): void
    {
        $this->setProcessTitle($this->name . ': master');
        if (isset($server->setting['pid_file'])) {
            @file_put_contents($server->setting['pid_file'], $server->master_pid);
        }
    }

    /**
     * @param \Swoole\Server $server
     */
    public function onShutdown(\Swoole\Server $server): void
    {
        if (isset($server->setting['pid_file']) && $server->setting['pid_file']) {
            unlink($server->setting['pid_file']);
        }
    }

    /**
     * @param \Swoole\Server $server
     * @param int $worker_id
     * @throws DependencyException
     * @throws NotFoundException|ReflectionException
     */
    public function onWorkerStart(\Swoole\Server $server, int $worker_id): void
    {
        $this->workerStart($worker_id, $server->taskworker);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $worker_id
     */
    public function onWorkerStop(\Swoole\Server $server, int $worker_id): void
    {
    }

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $from_id
     */
    public function onConnect(\Swoole\Server $server, int $fd, int $from_id): void
    {
    }

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactor_id
     * @param string $data
     */
    public function onReceive(\Swoole\Server $server, int $fd, int $reactor_id, string $data): void
    {
    }

    /**
     * @param Request $request
     * @param Response $response
     */
    public function onRequest(Request $request, Response $response): void
    {
    }

    /**
     * @param \Swoole\WebSocket\Server $server
     * @param Frame $frame
     */
    public function onMessage(\Swoole\WebSocket\Server $server, Frame $frame): void
    {
    }

    /**
     * @param \Swoole\WebSocket\Server $server
     * @param Request $request
     */
    public function onOpen(\Swoole\WebSocket\Server $server, Request $request): void
    {
    }

    public function onHandShake(Request $request, Response $response): bool
    {
    }

    /**
     * @param \Swoole\Server $server
     * @param string $data
     * @param array $client_info
     */
    public function onPacket(\Swoole\Server $server, string $data, array $client_info): void
    {
    }

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $from_id
     */
    public function onClose(\Swoole\Server $server, int $fd, int $from_id): void
    {
    }

    /**
     * @param \Swoole\Server $server
     * @param int $task_id
     * @param int $from_id
     * @param $data
     * @return Exception|string|Throwable
     * @throws Throwable
     */
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

    /**
     * @param \Swoole\Server $server
     * @param Task $task
     * @throws Throwable
     */
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

    /**
     * @param \Swoole\Server $server
     * @param int $task_id
     * @param string $data
     */
    public function onFinish(\Swoole\Server $server, int $task_id, string $data): void
    {
        $this->taskHandle->finish($server, $task_id, $data);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $from_worker_id
     * @param $message
     */
    public function onPipeMessage(\Swoole\Server $server, int $from_worker_id, string $message): void
    {
        $this->pipeHandler && $this->pipeHandler->handle($server, $from_worker_id, $message);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $worker_id
     * @param int $worker_pid
     * @param int $exit_code
     */
    public function onWorkerError(\Swoole\Server $server, int $worker_id, int $worker_pid, int $exit_code): void
    {
    }

    /**
     * @param \Swoole\Server $server
     */
    public function onManagerStart(\Swoole\Server $server): void
    {
        $this->setProcessTitle($this->name . ': manager');
    }

    /**
     * @param \Swoole\Server $server
     */
    public function onManagerStop(\Swoole\Server $server): void
    {
    }
}
