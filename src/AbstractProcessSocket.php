<?php


namespace rabbit\server;

use rabbit\exception\InvalidArgumentException;
use rabbit\helper\WaitGroup;
use rabbit\parser\ParserInterface;
use rabbit\parser\PhpParser;
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

    /**
     * ProcessSocket constructor.
     */
    public function __construct()
    {
        $this->parser = new PhpParser();
    }

    /**
     * @param int $totalNum
     */
    public function setWorkerIds(int $totalNum): void
    {
        for ($i = 0; $i < $totalNum; $i++) {
            $this->workerIds[] = $i;
        }
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
     * @param $data
     * @param int|null $workerId
     * @return mixed
     */
    public function send(&$data, int $workerId = null)
    {
        if ($workerId === null) {
            $workerIds = $this->workerIds;
            unset($workerIds[$this->workerId]);
            $workerId = array_rand($workerIds);
        } elseif ($workerId === $this->workerId) {
            throw new InvalidArgumentException("The workerId must be not eq current worker=$workerId");
        }
        $socket = $this->getProcess($workerId)->exportSocket();
        $socket->send($this->parser->encode($data));
        return $this->parser->decode($socket->recv());
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
                $group->add($id, function () use ($id) {
                    $resulst[$id] = $this->send($data, $id);
                });
            }
            $group->wait();
        } else {
            foreach ($workerIds as $id) {
                rgo(function () {
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
    abstract public function handle(string &$data): string;
}
