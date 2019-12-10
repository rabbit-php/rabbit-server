<?php


namespace rabbit\server;

use rabbit\helper\FileHelper;
use rabbit\helper\WaitGroup;
use rabbit\parser\MsgPackParser;
use rabbit\parser\ParserInterface;
use Swoole\Process;
use Swoole\Process\Pool;

/**
 * Class AbstractProcessSocket
 * @package rabbit\server
 */
abstract class AbstractProcessSocket
{
    /** @var ParserInterface */
    protected $parser;
    /** @var Pool */
    protected $pool;
    /** @var int */
    public $workerId;
    /** @var array */
    protected $workerIds = [];
    /** @var string */
    protected $path = '/dev/shm/ProcessSocket';
    /** @var bool */
    protected $sendBigData = true;
    /** @var bool */
    protected $return = false;

    /**
     * ProcessSocket constructor.
     */
    public function __construct(ParserInterface $parser = null)
    {
        $this->parser = $parser ?? new MsgPackParser();
        FileHelper::createDirectory($this->path);
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
            $data = $this->parser->decode($socket->recv());
            $result = $this->handle($data);
            $this->return && $socket->send($this->parser->encode($result));
        }
    }

    /**
     * @param $data
     * @param int|null $workerId
     * @return mixed
     */
    public function send(&$data, int $workerId = null)
    {
        if ($workerId === null) {
            $workerId = array_rand($this->workerIds);
        }

        if ($workerId === $this->workerId) {
            return $this->handle($data);
        }
        $socket = $this->getProcess($workerId)->exportSocket();
        $data = $this->parser->encode($data);
        $len = strlen($data);
        if ($len >= 65536) {
            if ($this->sendBigData) {
                $fileName = uniqid();
                $writeLen = $this->writeMemory($fileName, $data);
                if ($len !== $writeLen) {
                    throw new \RuntimeException("Write to memory $len but only $writeLen writed");
                }
                $data = $this->parser->encode(['readMemory', [$fileName]]);
            } else {
                $data = $this->parser->decode($data);
                return $this->handle($data);
            }
        }

        while ($data) {
            $len = $socket->sendAll($data);
            if (strlen($data) === $len) {
                break;
            }
            $data = substr($data, $len);
        }

        if ($this->return) {
            return $this->parser->decode($socket->recv());
        }
    }

    /**
     * @param string $fileName
     * @param string $data
     * @return int
     */
    public function writeMemory(string $fileName, string &$data): int
    {
        return file_put_contents($this->path . '/' . $fileName, $data);
    }

    /**
     * @param string $fileName
     * @return string
     */
    public function readMemory(string $fileName): string
    {
        $data = file_get_contents($this->path . '/' . $fileName);
        unlink($this->path . '/' . $fileName);
        $data = $this->parser->decode($data);
        return $this->handle($data);
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
        $resulst = [];
        if ($return) {
            $group = new WaitGroup();
            foreach ($workerIds as $id) {
                $group->add($id, function () use ($id, &$data) {
                    $resulst[$id] = $this->send($data, $id);
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
    }

    /**
     * @param array $responses
     * @param string $data
     * @return mixed
     */
    abstract public function handle(array &$data);
}
