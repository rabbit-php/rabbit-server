<?php
declare(strict_types=1);

namespace Rabbit\Server;

use Co\Channel;
use Co\Socket;
use Rabbit\Base\Core\Exception;
use Rabbit\Base\Helper\FileHelper;
use Rabbit\Base\Helper\WaitGroup;
use Rabbit\Parser\MsgPackParser;
use Rabbit\Parser\ParserInterface;
use Swoole\Process;
use Swoole\Process\Pool;

/**
 * Class AbstractProcessSocket
 * @package Rabbit\Server
 */
abstract class AbstractProcessSocket
{
    /** @var ParserInterface */
    protected ParserInterface $parser;
    /** @var Pool */
    protected Pool $pool;
    /** @var int */
    public int $workerId;
    /** @var array */
    protected array $workerIds = [];
    /** @var string */
    protected string $path = '/dev/shm/ProcessSocket';
    /** @var Channel */
    private Channel $channel;

    /**
     * ProcessSocket constructor.
     * @param ParserInterface|null $parser
     * @throws Exception
     */
    public function __construct(ParserInterface $parser = null)
    {
        $this->parser = $parser ?? new MsgPackParser();
        FileHelper::createDirectory($this->path);
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
    public function socketIPC(Process $process)
    {
        $socket = $process->exportSocket();
        while (true) {
            [$data, $wait] = $this->parser->decode($this->dealRecv($socket));
            $result = $this->parser->encode($this->handle($data));
            $wait && $this->dealSend($socket, $result);
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
    public function send(&$data, int $workerId = null, bool $wait = false)
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
        } catch (\Throwable $exception) {
            $len = strlen($data);
            throw new \RuntimeException("$len bytes send failed!");
        } finally {
            $this->channel->pop();
        }

        if ($wait) {
            return $this->parser->decode($this->dealRecv($socket));
        }
    }

    /**
     * @param $data
     * @param bool $return
     * @return array
     * @throws \Exception
     */
    public function sendAll(&$data, bool $return = false): array
    {
        $workerIds = $this->workerIds;
        unset($workerIds[$this->workerId]);
        $result = [];
        if ($return) {
            $group = new WaitGroup();
            foreach ($workerIds as $id) {
                $group->add($id, function () use ($id, &$data) {
                    $result[$id] = $this->send($data, $id);
                });
            }
            $group->wait();
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
