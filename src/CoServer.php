<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Swoole\Coroutine\Http\Server;
use Swoole\Coroutine\Server as CoroutineServer;
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

    protected CoroutineServer|Server $swooleServer;
    protected ?AbstractProcessSocket $socketHandle = null;

    public function getSwooleServer(): CoroutineServer|Server
    {
        return $this->swooleServer;
    }

    public function getSsl(): bool
    {
        return $this->ssl;
    }

    public function start(): void
    {
        $this->setProcessTitle($this->name . ": master");
        $this->startWithPool();
    }

    protected function startWithPool(): void
    {
        if (1 < $num = $this->setting['worker_num']) {
            $num++;
        }
        $pool = new Pool($num, SWOOLE_IPC_UNIXSOCK, 0, true);
        $pool->on('workerStart', function (Pool $pool, int $workerId) use ($num): void {
            Runtime::enableCoroutine();
            if ($this->socketHandle instanceof AbstractProcessSocket) {
                $this->socketHandle->workerId = $workerId;
                $this->socketHandle->socketIPC();
            }
            if ($num > 1 && $workerId === $num - 1) {
                @swoole_set_process_name(config('appName') . ':share');
            } else {
                ServerHelper::setServer($this);
                $this->workerStart($workerId);
                $this->swooleServer = $this->createServer();
                $this->startServer($this->swooleServer);
            }
        });
        $pool->on('workerStop', function (Pool $pool, int $workerId): void {
            $this->onWorkerExit($workerId);
        });
        if ($this->socketHandle instanceof AbstractProcessSocket) {
            $this->socketHandle->setPool($pool);
        }
        ServerHelper::setNum($num);
        ServerHelper::setLockId($num - 1);
        $this->beforeStart();
        $pool->start();
    }

    public function getProcessSocket(): AbstractProcessSocket
    {
        return $this->socketHandle;
    }

    abstract protected function createServer(): CoroutineServer|Server;

    protected function startServer(CoroutineServer|Server $server): void
    {
        $server->set($this->setting);
    }

    protected function setProcessTitle(string $name): void
    {
        if (function_exists('swoole_set_process_name')) {
            @swoole_set_process_name($name);
        } else {
            @cli_set_process_title($name);
        }
    }
}
