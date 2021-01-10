<?php

declare(strict_types=1);

namespace Rabbit\Server;

use DI\DependencyException;
use DI\NotFoundException;
use Rabbit\Base\App;
use ReflectionException;
use Swoole\Process;
use Swoole\Process\Pool;
use Swoole\Runtime;

/**
 * Class CoServer
 * @package Rabbit\Server
 */
abstract class CoServer
{
    use ServerTrait;

    protected $swooleServer;
    protected ?AbstractProcessSocket $socketHandle = null;

    /**
     * @return \Co\Server
     */
    public function getSwooleServer()
    {
        return $this->swooleServer;
    }

    /**
     * @return bool
     */
    public function getSsl(): bool
    {
        return $this->ssl;
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException|ReflectionException
     */
    public function start(): void
    {
        $this->setProcessTitle($this->name . ": master");
        $this->startWithPool();
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException|ReflectionException
     */
    protected function startWithPool(): void
    {
        $pool = new Pool($this->setting['worker_num'], SWOOLE_IPC_UNIXSOCK, 0, true);
        $pool->on('workerStart', function (Pool $pool, int $workerId) {
            Runtime::enableCoroutine();
            $process = $pool->getProcess();
            if ($this->socketHandle instanceof AbstractProcessSocket) {
                $this->socketHandle->workerId = $workerId;
                rgo(function () use ($process) {
                    $this->socketHandle->socketIPC($process);
                });
            }
            ServerHelper::setCoServer($this);
            $this->workerStart($workerId);
            $this->swooleServer = $this->createServer();
            Process::signal(SIGTERM, function () {
                $this->server->shutdown();
            });
            $this->startServer($this->swooleServer);
        });
        $pool->on('workerStop', function (Pool $pool, int $workerId) {
            $this->onWorkerExit($workerId);
        });
        if ($this->socketHandle instanceof AbstractProcessSocket) {
            $this->socketHandle->setWorkerIds($this->setting['worker_num']);
            $this->socketHandle->setPool($pool);
        }
        $this->beforeStart();
        $pool->start();
    }

    /**
     * @return AbstractProcessSocket
     */
    public function getProcessSocket(): AbstractProcessSocket
    {
        return $this->socketHandle;
    }

    abstract protected function createServer();

    /**
     * @param null $server
     */
    protected function startServer($server = null): void
    {
        $server->set($this->setting);
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
}
