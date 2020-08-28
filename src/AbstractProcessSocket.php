<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Rabbit\Base\Core\Exception;
use Rabbit\Parser\MsgPackParser;
use Rabbit\Parser\ParserInterface;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Socket;
use Swoole\Process;
use Swoole\Process\Pool;
use Throwable;

/**
 * Class AbstractProcessSocket
 * @package Rabbit\Server
 */
abstract class AbstractProcessSocket
{
    protected ParserInterface $parser;
    protected Pool $pool;
    public int $workerId;
    protected array $workerIds = [];
    private Channel $channel;
    protected bool $isRun = false;

    /**
     * ProcessSocket constructor.
     * @param ParserInterface|null $parser
     * @throws Exception
     */
    public function __construct(ParserInterface $parser = null)
    {
        $this->parser = $parser ?? new MsgPackParser();
        $this->channel = new Channel(1);
    }

    /**
     * @param int $totalNum
     */
    public function setWorkerIds(int $totalNum): void
    {
        $this->workerIds = range(0, $totalNum - 1);
    }

    /**
     * @return array
     */
    public function getWorkerIds(): array
    {
        return $this->workerIds;
    }

    /**
     * @param Pool $pool
     */
    public function setPool(Pool $pool): void
    {
        $this->pool = $pool;
    }

    /**
     * @return Pool
     */
    public function getPool(): Pool
    {
        return $this->pool;
    }

    /**
     * @param int|null $workerId
     * @return Process
     */
    public function getProcess(?int $workerId = null): Process
    {
        $workerId = $workerId ?? $this->workerId;
        return $this->pool->getProcess($workerId);
    }

    /**
     * @param Process $process
     */
    public function socketIPC(Process $process): void
    {
        $socket = $process->exportSocket();
        $this->isRun = true;
        while (true) {
            [$data, $wait] = $this->parser->decode($this->dealRecv($socket));
            $result = $this->parser->encode($this->handle($data));
            $wait !== 0 && $this->dealSend($socket, $result);
        }
    }
    
    /**
     * @author Albert <63851587@qq.com>
     * @param boolean $isRun
     * @return void
     */
    public function setRun(bool $isRun): void
    {
        $this->isRun = $isRun;
    }

    /**
     * @param Socket $socket
     * @return string
     */
    private function dealRecv(Socket $socket): string
    {
        $data = $socket->recv();
        $len = current(unpack('N', substr($data, 0, 4))) + 4;
        while (true) {
            $data .= $socket->recv();
            if (strlen($data) === $len) {
                break;
            }
        }
        return substr($data, 4);
    }

    /**
     * @param Socket $socket
     * @param string $data
     */
    private function dealSend(Socket $socket, string &$data): void
    {
        while ($data) {
            $tmp = substr($data, 0, 65535);
            $len = $socket->send($tmp);
            $data = substr($data, $len);
        }
    }

    /**
     * @param $data
     * @param int|null $workerId
     * @param bool $wait
     * @return mixed
     */
    public function send(&$data, int $workerId = null, float $wait = 0)
    {

        if ($workerId === null) {
            $workerId = array_rand($this->workerIds);
        }

        if ($workerId === $this->workerId) {
            return $this->handle($data);
        }
        $socket = $this->getProcess($workerId)->exportSocket();
        $data = $this->parser->encode([$data, $wait]);
        $pLen = pack('N', strlen($data));
        $data = $pLen . $data;
        $this->channel->push(1);
        try {
            $this->dealSend($socket, $data);
        } catch (Throwable $exception) {
            $len = strlen($data);
            throw new \RuntimeException("$len bytes send failed!");
        } finally {
            $this->channel->pop();
        }

        if ($wait !== 0) {
            return $this->parser->decode($this->dealRecv($socket, $wait));
        }
    }

    /**
     * @param $data
     * @param float $wait
     * @return array
     * @throws Throwable
     */
    public function sendAll(&$data, float $wait = 0): array
    {
        $workerIds = $this->workerIds;
        unset($workerIds[$this->workerId]);
        $result = [];
        if ($wait !== 0) {
            wgeach($workerIds, fn (int $i, int $id) => $result[] = $this->send($data, $id), $wait > 0 ? $wait : -1);
        } else {
            foreach ($workerIds as $id) {
                rgo(function () use ($id, &$data) {
                    $this->send($data, $id);
                });
            }
        }
        return $result;
    }

    /**
     * @param array $data
     * @return mixed
     */
    abstract public function handle(array &$data);
}
