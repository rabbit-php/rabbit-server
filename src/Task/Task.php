<?php


namespace rabbit\server\Task;

use rabbit\App;

/**
 * Class Task
 * @package rabbit\server\Task
 */
class Task
{
    /** @var array */
    protected $taskList = [];
    /** @var string */
    protected $logKey = 'Task';
    /** @var string */
    protected $taskName;

    /**
     * AbstractTask constructor.
     * @param array $taskList
     */
    public function __construct(string $name = null)
    {
        $this->taskName = $name ?? uniqid();
    }


    /**
     * @param float $timeout
     * @return array
     */
    public function start(float $timeout = 0.5): array
    {
        App::info('Task' . " $this->taskName " . 'start count=' . count($this->taskList), $this->logKey);
        $result = App::getServer()->getSwooleServer()->taskCo($this->taskList, $timeout);
        App::info('Task' . " $this->taskName " . 'finish!', $this->logKey);
        return is_array($result) ? $result : [$result];
    }

    /**
     * @param $task
     * @return AbstractTask
     */
    public function addTask($task): self
    {
        $this->taskList[] = $task;
        return $this;
    }

    /**
     * @param $data
     */
    public function task($data, int $dst_worker_id = -1, \Closure $function = null)
    {
        return $function ? App::getServer()->getSwooleServer()->task($data, $dst_worker_id,
            $function) : App::getServer()->getSwooleServer()->task($data,
            $dst_worker_id);
    }

    /**
     * @param $data
     * @param float $timeout
     * @param int $dstWorkerId
     */
    public function taskwait($data, float $timeout = 0.5, int $dstWorkerId = -1)
    {
        return App::getServer()->getSwooleServer()->taskwait($data, $timeout, $dstWorkerId);
    }
}