<?php
declare(strict_types=1);

namespace Rabbit\Server;

use DI\DependencyException;
use DI\NotFoundException;
use Rabbit\Process\Process;
use Rabbit\Process\ProcessInterface;
use Swoole\Process\Pool;
use Swoole\Runtime;

/**
 * Class CoServer
 * @package Rabbit\Server
 */
abstract class CoServer
{
    /**
     * @var string
     */
    protected string $name = 'rabbit';

    /**
     * @var string
     */
    protected string $host = '0.0.0.0';

    /**
     * @var int
     */
    protected int $port = 80;

    /**
     * @var bool
     */
    protected bool $ssl = false;

    /**
     * @var array
     */
    protected array $beforeStart = [];

    /**
     * @var array
     */
    protected array $workerExit = [];

    /**
     * @var array
     */
    protected array $workerStart = [];
    /** @var array */
    protected array $processes = [];
    /** @var array */
    protected array $setting = [];

    protected \Co\Server $swooleServer;

    /** @var AbstractProcessSocket */
    protected AbstractProcessSocket $socketHandle;

    /**
     * Server constructor.
     * @param array $setting
     * @param array $coSetting
     */
    public function __construct(array $setting = [], array $coSetting = [])
    {
        $this->setting = $setting;
        \Co::set($coSetting);
    }

    /**
     * @return \Co\Server
     */
    public function getSwooleServer():\Co\Server
    {
        return $this->swooleServer;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
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
     * @throws NotFoundException
     */
    public function start(): void
    {
        $this->setProcessTitle($this->name . ": master");
        $this->startWithPool();
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
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
     * @param int $workerId
     * @param bool $isTask
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function onWorkerStart(int $workerId, bool $isTask = false): void
    {
        foreach ($this->workerStart as $name => $handle) {
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

    /**
     * @param int $workerId
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function onWorkerExit(int $workerId): void
    {
        foreach ($this->workerExit as $name => $handle) {
            if (!$handle instanceof WorkerHandlerInterface) {
                $handle = create($handle);
            }
            $handle->handle($workerId);
        }
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

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function beforeStart(): void
    {
        foreach ($this->beforeStart as $name => $handle) {
            if (!$handle instanceof BootInterface) {
                $handle = create($handle);
            }
            $handle->handle();
        }
    }
}
