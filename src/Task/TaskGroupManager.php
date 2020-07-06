<?php


namespace Rabbit\Server\Task;

use rabbit\App;
use rabbit\helper\VarDumper;

/**
 * Class TaskGroupManager
 * @package rabbit\server\Task
 */
class TaskGroupManager
{
    /** @var array */
    protected $taskList = [];
    /** @var string */
    protected $logKey = 'Task';
    /** @var string */
    protected $taskName;
    /** @var int */
    private $group = 0;

    /**
     * AbstractTask constructor.
     * @param array $taskList
     */
    public function __construct(int $group = 0, string $name = null)
    {
        $this->taskName = $name ?? uniqid();
        $this->group = $group;
    }

    /**
     * @param float $timeout
     */
    public function startGroup(float $timeout = 0.5): void
    {
        foreach ($this->taskList as $items) {
            $taskGroup = new TaskGroup();
            foreach ($items as $task) {
                $task = array_merge($task, [
                    function (\Swoole\Server $serv, $task_id, $data) use ($taskGroup) {
                        $taskGroup->push($data);
                    }
                ]);
                App::getServer()->getSwooleServer()->task(...$task);
                $taskGroup->add();
            }
            App::info("Task {$taskGroup->getName()} start count=" . $taskGroup->getCount(), $this->logKey);
            $result = $taskGroup->wait($timeout);
            App::info(
                "Task finish {$taskGroup->getName()}" . VarDumper::getDumper()->dumpAsString($result),
                $this->logKey
            );
        }
        App::info(
            "{$this->taskName} All Task finish",
            $this->logKey
        );
    }

    /**
     * @param $task
     * @param int $dst_worker_id
     * @return AbstractTaskGroup
     */
    public function addGroup($task, int $dst_worker_id = -1): self
    {
        if ($this->group > 0) {
            if (!empty($this->taskList) && count($this->taskList[count($this->taskList) - 1]) < $this->group) {
                $this->taskList[count($this->taskList) - 1][] = [$task, $dst_worker_id];
                return $this;
            }
        }
        $this->taskList[][] = [$task, $dst_worker_id];
        return $this;
    }
}
