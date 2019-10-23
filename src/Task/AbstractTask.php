<?php


namespace rabbit\server\Task;

/**
 * Interface TaskInterface
 * @package rabbit\server\Task
 */
abstract class AbstractTask
{
    /** @var string */
    protected $logKey = 'Task';

    /**
     * AbstractTask constructor.
     * @param string $logKey
     */
    public function __construct(string $logKey = 'Task')
    {
        $this->logKey = $logKey;
    }

    /**
     * @param int $task_id
     * @param int $from_id
     * @param $data
     * @return mixed
     */
    abstract public function handle(int $task_id, int $from_id, &$data);

    /**
     * @param \Swoole\Server $serv
     * @param int $task_id
     * @param string $data
     * @return mixed
     */
    public function finish(\Swoole\Server $serv, int $task_id, string $data)
    {
    }
}
