<?php


namespace rabbit\server\Task;


use rabbit\App;

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
        $success = 0;
        $failed = [];
        foreach ($this->taskList as $items) {
            $taskGroup = new TaskGroup();
            foreach ($items as $task) {
                $task = array_merge($task, [
                    function (\Swoole\Server $serv, $task_id, $data) use ($taskGroup) {
                        $taskGroup->push($data);
                    }
                ]);
                App::getServer()->task(...$task);
                $taskGroup->add();
            }
            App::info("Task {$taskGroup->getName()} start file count=" . $taskGroup->getCount(), $this->logKey);
            $result = $taskGroup->wait($timeout);
            $tmpSuccess = 0;
            $tmpFaild = [];
            foreach ($result as $res) {
                $tmpSuccess += $res['success'];
                $tmpFaild = array_merge($tmpFaild, $res['failed']);
            }
            $tmpFaildCount = count($tmpFaild);
            $success += $tmpSuccess;
            $failed = array_merge($failed, $tmpFaild);
            $tmpFaild = implode(' & ', $tmpFaild);
            App::info("Task finish {$taskGroup->getName()} success=$tmpSuccess failed=$tmpFaildCount files=$tmpFaild",
                $this->logKey);
        }
        $failedCount = count($failed);
        $failed = implode(' & ', $failed);
        App::info("All Task finish {$this->taskName} success=$success failed=$failedCount files=$failed",
            $this->logKey);
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