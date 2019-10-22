<?php


namespace rabbit\server;

use rabbit\App;
use rabbit\core\ObjectFactory;
use Swoole\Process;
use Swoole\Process\Pool;

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
        $pool = new Pool($this->setting['worker_num'], SWOOLE_IPC_UNIXSOCK, 0, true);
        $pool->on('workerStart', function (Pool $pool, int $workerId) {
            $this->socketHandle->workerId = $workerId;
            $process = $pool->getProcess();
            $this->onWorkerStart($workerId);
            if ($this->socketHandle) {
                rgo(function () use ($process) {
                    $this->socketHandle->socketIPC($process);
                });
            }
            App::setServer($this);
            $this->startServer($this->swooleServer = $this->createServer());
        });
        $pool->on('workerStop', function (Pool $pool, int $workerId) {
            $this->onWorkerExit($workerId);
        });
        $this->socketHandle->setWorkerIds($this->setting['worker_num']);
        $this->socketHandle->setPool($pool);
        $pool->start();
    }

    /**
     * @param int $workerId
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    protected function onWorkerStart(int $workerId, bool $isTask = false): void
    {
        ObjectFactory::workerInit();
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
        $this->beforeStart($server);
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
     * @param $server
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    protected function beforeStart($server): void
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
