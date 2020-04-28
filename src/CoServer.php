<?php


namespace rabbit\server;

use rabbit\App;
use rabbit\core\ObjectFactory;
use rabbit\process\Process;
use rabbit\process\ProcessInterface;
use Swoole\Process\Pool;
use Swoole\Runtime;

/**
 * Class CoServer
 * @package rabbit\server
 */
abstract class CoServer
{
    /**
     * @var array
     */
    protected $schme = [];

    /**
     * @var string
     */
    protected $name = 'rabbit';

    /**
     * @var string
     */
    protected $host = '0.0.0.0';

    /**
     * @var int
     */
    protected $port = 80;

    /**
     * @var int
     */
    protected $ssl = false;

    /**
     * @var array
     */
    protected $beforeStart = [];

    /**
     * @var array
     */
    protected $workerExit = [];

    /**
     * @var array
     */
    protected $workerStart = [];
    /** @var array */
    protected $processes = [];
    /** @var array */
    protected $setting = [];

    protected $swooleServer;

    /** @var AbstractProcessSocket */
    protected $socketHandle;

    /**
     * Server constructor.
     * @param array $setting
     * @throws \Exception
     */
    public function __construct(array $setting = [], array $coSetting = [])
    {
        $this->setting = $setting;
        \Co::set($coSetting);
    }

    public function getSwooleServer()
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
     * @return int
     */
    public function getSsl(): bool
    {
        return $this->ssl;
    }

    /**
     *
     */
    public function start(): void
    {
        $this->setProcessTitle($this->name . ": master");
        $this->startWithPool();
    }

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
                App::setServer($this);
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
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    protected function onWorkerStart(int $workerId, bool $isTask = false): void
    {
        if (extension_loaded('Zend OPcache')) {
            opcache_reset();
        }
        foreach ($this->workerStart as $name => $handle) {
            if (!$handle instanceof WorkerHandlerInterface) {
                /**
                 * @var WorkerHandlerInterface $handle
                 */
                $handle = ObjectFactory::createObject($handle);
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
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    protected function onWorkerExit(int $workerId): void
    {
        foreach ($this->workerExit as $name => $handle) {
            if (!$handle instanceof WorkerHandlerInterface) {
                /**
                 * @var BootInterface $handle
                 */
                $handle = ObjectFactory::createObject($handle);
            }
            $handle->handle($worker_id);
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
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
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
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    protected function beforeStart(): void
    {
        foreach ($this->beforeStart as $name => $handle) {
            if (!$handle instanceof BootInterface) {
                /**
                 * @var BootInterface $handle
                 */
                $handle = ObjectFactory::createObject($handle);
            }
            $handle->handle();
        }
    }
}
