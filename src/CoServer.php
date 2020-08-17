<?php
declare(strict_types=1);

namespace Rabbit\Server;

use DI\DependencyException;
use DI\NotFoundException;
use Rabbit\Process\Process;
use Rabbit\Process\ProcessInterface;
use ReflectionException;
use Swoole\Process\Pool;
use Swoole\Runtime;

/**
 * Class CoServer
 * @package Rabbit\Server
 */
abstract class CoServer
{
    use ServerTrait;

    protected \Co\Server $swooleServer;
    protected AbstractProcessSocket $socketHandle;

    /**
     * @return \Co\Server
     */
    public function getSwooleServer(): \Co\Server
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
        $pool = new Pool($this->setting['worker_num'] + count($this->processes), SWOOLE_IPC_UNIXSOCK, 0, true);
        $pool->on('workerStart', function (Pool $pool, int $workerId) {
            Runtime::enableCoroutine();
            $process = $pool->getProcess();
            if ($this->socketHandle instanceof AbstractProcessSocket) {
                $this->socketHandle->workerId = $workerId;
                rgo(function () use ($process) {
                    $this->socketHandle->socketIPC($process);
                });
            }
            if ($workerId < $this->setting['worker_num']) {
                ServerHelper::setCoServer($this);
                $this->onWorkerStart($workerId);
                $this->startServer($this->swooleServer = $this->createServer());
            } else {
                $keys = array_keys($this->processes);
                $pro = $this->processes[$keys[$workerId - $this->setting['worker_num']]];
                if ($pro instanceof ProcessInterface) {
                    $child = new Process($process);
                    $child->name($keys[$workerId - $this->setting['worker_num']]);
                    $pro->run($child);
                }
            }
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
