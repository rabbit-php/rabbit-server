<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/8
 * Time: 19:09
 */

namespace rabbit\server;

use rabbit\App;
use rabbit\contract\DispatcherInterface;
use rabbit\core\ObjectFactory;

/**
 * Class Server
 * @package rabbit\server
 */
abstract class Server
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
    protected $type = SWOOLE_PROCESS;

    /**
     * @var DispatcherInterface
     */
    protected $dispatcher;

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

    public function __construct()
    {
        $this->name = ObjectFactory::get("appName", false, $this->name);
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
    public function getType(): int
    {
        return $this->type;
    }

    /**
     *
     */
    public function start(): void
    {
        $this->startServer($this->createServer());
    }

    /**
     * @return \Swoole\Server
     */
    abstract protected function createServer(): \Swoole\Server;

    /**
     * @param \Swoole\Server|null $server
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    protected function startServer(\Swoole\Server $server = null): void
    {
        App::setServer($server);
        $server->on('start', [$this, 'onStart']);
        $server->on('shutdown', [$this, 'onShutdown']);

        $server->on('managerStart', [$this, 'onManagerStart']);

        $server->on('workerStart', [$this, 'onWorkerStart']);
        $server->on('workerStop', [$this, 'onWorkerStop']);
        $server->on('workerError', [$this, 'onWorkerError']);


        $server->on('task', [$this, 'onTask']);
        $server->on('finish', [$this, 'onFinish']);

        $server->on('pipeMessage', [$this, 'onPipeMessage']);
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
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    protected function beforeStart(\Swoole\Server $server): void
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

    /**
     * @param \Swoole\Server $server
     * @param int $worker_id
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function workerStart(\Swoole\Server $server,int $worker_id): void
    {
        ObjectFactory::reload();
        foreach ($this->workerStart as $name => $handle) {
            if (!$handle instanceof WorkerHandlerInterface) {
                /**
                 * @var WorkerHandlerInterface $handle
                 */
                $handle = ObjectFactory::createObject($handle);
            }
            $handle->handle($worker_id);
        }
    }

    /**
     *
     */
    public function stop(): void
    {
        if (App::getServer()->setting['pid_file']) {
            $pid = file_get_contents(App::getServer()->setting['pid_file']);
            \swoole_process::kill(intval($pid));
        }
    }

    /**
     * @param \Swoole\Server $server
     */
    public function onStart(\Swoole\Server $server): void
    {
        $this->setProcessTitle($this->name . ': master');
        if ($server->setting['pid_file']) {
            file_put_contents($server->setting['pid_file'], $server->master_pid);
        }
    }

    /**
     * @param \Swoole\Server $server
     */
    public function onShutdown(\Swoole\Server $server): void
    {
        if ($server->setting['pid_file']) {
            unlink($server->setting['pid_file']);
        }
    }

    /**
     * @param \Swoole\Server $server
     * @param int $worker_id
     */
    public function onWorkerStart(\Swoole\Server $server, int $worker_id): void
    {
        if (!$server->taskworker) {
            //worker
            $this->setProcessTitle($this->name . ': worker' . ": {$worker_id}");
        } else {
            //task
            $this->setProcessTitle($this->name . ': task' . ": {$worker_id}");
        }
        $this->workerStart($server, $worker_id);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $worker_id
     */
    public function onWorkerStop(\Swoole\Server $server, int $worker_id): void
    {
        if (extension_loaded('Zend OPcache')) {
            opcache_reset();
        }
    }

    public function onWorkerExit(\Swoole\Server $server, int $worker_id)
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
     * @param int $from_id
     * @param string $data
     */
    public function onReceive(\Swoole\Server $server, int $fd, int $reactor_id, string $data): void
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
     * @param \Swoole\Server $serv
     * @param int $task_id
     * @param int $from_id
     * @param string $data
     */
    public function onTask(\Swoole\Server $serv, int $task_id, int $from_id, string $data): void
    {
    }

    /**
     * @param \Swoole\Server $serv
     * @param int $task_id
     * @param string $data
     */
    public function onFinish(\Swoole\Server $serv, int $task_id, string $data): void
    {
    }

    /**
     * @param \Swoole\Server $server
     * @param int $from_worker_id
     * @param string $message
     */
    public function onPipeMessage(\Swoole\Server $server, int $from_worker_id, string $message): void
    {

    }

    /**
     * @param \Swoole\Server $serv
     * @param int $worker_id
     * @param int $worker_pid
     * @param int $exit_code
     */
    public function onWorkerError(\Swoole\Server $serv, int $worker_id, int $worker_pid, int $exit_code): void
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
     * @param \Swoole\Server $serv
     */
    public function onManagerStop(\Swoole\Server $serv): void
    {

    }
}