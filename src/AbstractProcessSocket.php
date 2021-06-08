<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Rabbit\Base\Core\Context;
use Rabbit\Parser\MsgPackParser;
use Rabbit\Parser\ParserInterface;
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
    private $sendChan;
    protected bool $isRun = false;
    private string $key = 'ipc.co.msg';

    public function __construct(ParserInterface $parser = null)
    {
        $this->parser = $parser ?? new MsgPackParser();
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

    public function socketIPC(): void
    {
        if ($this->isRun === false) {
            $this->isRun = true;
            $this->sendChan = makeChannel();
            $this->recvChan = makeChannel();
            foreach ($this->workerIds as $wid) {
                $socket = $this->getProcess($wid)->exportSocket();
                loop(function () use ($socket) {
                    $msg = $this->parser->decode($this->dealRecv($socket));
                    if ($msg->finished) {
                        if ($chan = getContext($msg->msgId)[$this->key] ?? false) {
                            $chan->push($msg);
                        }
                    } else {
                        $msg->finished = true;
                        rgo(function () use ($msg) {
                            $msg->data = $this->handle($msg->data);
                            $msg->wait !== 0 && $this->dealSend($this->getProcess($msg->from)->exportSocket(), $this->parser->encode($msg));
                        });
                    }
                }, 0);
            }
        }
    }

    /**
     * @param Socket $socket
     * @return string
     */
    private function dealRecv(Socket $socket): string
    {
        $data = $socket->recv();
        $len = current(unpack('N', substr($data, 0, 4))) + 4;
        while (strlen($data) < $len) {
            $data .= $socket->recv();
        }
        return substr($data, 4);
    }

    /**
     * @param Socket $socket
     * @param string $data
     */
    private function dealSend(Socket $socket, string $data): void
    {
        $this->sendChan->push(1);
        try {
            $pLen = pack('N', strlen($data));
            $data = $pLen . $data;
            while ($data) {
                $tmp = substr($data, 0, 65535);
                $len = $socket->send($tmp);
                $data = substr($data, $len);
            }
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            $this->sendChan->pop();
        }
    }

    public function send(IPCMessage $msg): ?IPCMessage
    {
        if ($msg->to === -1) {
            $ids = $this->workerIds;
            unset($ids[$this->workerId]);
            $msg->to = $workerId = array_rand($ids);
        } elseif ($msg->to === $this->workerId) {
            $msg->data = $this->handle($msg->data);
            return $msg;
        } else {
            $workerId = $msg->to;
        }
        $msg->from = $this->workerId;

        if ($msg->wait !== 0) {
            if (false === $chan = getContext($msg->msgId)[$this->key] ?? false) {
                $chan = makeChannel();
                getContext($msg->msgId)[$this->key] = $chan;
            }
        }
        $socket = $this->getProcess($workerId)->exportSocket();
        $data = $this->parser->encode($msg);
        $this->dealSend($socket, $data);
        if ($msg->wait !== 0) {
            $msg = $chan->pop();
            if ($msg->error !== null) {
                throw $msg->error;
            }
            return $msg;
        }
        return null;
    }

    /**
     * @param $data
     * @param float $wait
     * @return array
     * @throws Throwable
     */
    public function sendAll(IPCMessage $msg): array
    {
        $workerIds = $this->workerIds;
        unset($workerIds[$this->workerId]);
        $result = [];
        if ($msg->wait !== 0) {
            wgeach($workerIds, fn (int $i, int $id) => $result[] = $this->send($msg));
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
