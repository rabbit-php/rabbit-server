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
    /** @var Pool */
    protected $pool;
    /** @var ProcessSocketInterface */
    protected $socketHandle;
    /** @var int */
    public $workerId;
    /** @var bool */
    protected $usePool = true;

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
     * @return Process
     */
    public function getProcess(?int $workerId = null): Process
    {
        $workerId = $workerId ?? $this->workerId;
        return $this->pool instanceof Pool ? $this->pool->getProcess($workerId) : $this->pool[$workerId];
    }

    /**
     *
     */
    public function start(): void
    {
        if ($this->usePool) {
            $this->startWithPool();
        } else {
            $this->startWithProcess();
        }
    }

    protected function startWithPool(): void
    {
        $this->pool = new Pool($this->setting['worker_num'], SWOOLE_IPC_UNIXSOCK, 0, true);
        $this->pool->on('workerStart', function (Pool $pool, int $workerId) {
            $this->workerId = $workerId;
            if ($this->socketHandle) {
                rgo(function () use ($pool) {
                    $socket = $pool->getProcess()->exportSocket();
                    while (true) {
                        $data = $socket->recv();
                        !empty($data) && $this->socketHandle->handle($this, $data);
                    }
                });
            }
            App::setServer($this);
            $this->onWorkerStart($workerId);
            $this->startServer($this->swooleServer = $this->createServer());
            $this->onWorkerExit($workerId);
        });
        $this->pool->start();
    }

    protected function startWithProcess(): void
    {
        for ($workerId = 0; $workerId < $this->setting['worker_num']; $workerId++) {
            $process = new Process(function (Process $proc) use ($workerId) {
                $this->workerId = $workerId;
                $this->onWorkerStart($workerId);
                App::setServer($this);
                if ($this->socketHandle) {
                    rgo(function () use ($proc) {
                        $socket = $proc->exportSocket();
                        while (true) {
                            $data = $socket->recv();
                            !empty($data) && $this->socketHandle->handle($this, $data);
                        }
                    });
                }
                $this->startServer($this->swooleServer = $this->createServer());
                $this->onWorkerExit($workerId);
            }, false, 1, true);
            $this->pool[$workerId] = $process;
        }
        Process::signal(SIGCHLD, function ($sig) {
            //必须为false，非阻塞模式
            while ($ret = Process::wait(false)) {
            }
        });
        foreach ($this->pool as $process) {
            $process->start();
        }
    }

    /**
     * @param int $workerId
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    protected function onWorkerStart(int $workerId): void
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
        $this->setProcessTitle($this->name . ': worker' . ": {$workerId}");
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
     * @return ProcessSocketInterface
     */
    public function getProcessSocket(): ProcessSocketInterface
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