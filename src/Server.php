<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/8
 * Time: 19:09
 */

namespace rabbit\server;

use rabbit\App;
use rabbit\core\ObjectFactory;

/**
 * 基础的server实现
 *
 * @package yii\swoole\server
 */
abstract class Server
{

    use WorkTrait;

    /**
     * @var swoole_server
     */
    protected $server;

    /**
     * @var array 当前配置文件
     */
    protected $config = [];

    /**
     * @var array
     */
    protected $schme = [];

    /**
     * @var string
     */
    protected $name = 'rabbit';

    /**
     * @var array
     */
    protected $beforeStart = [];

    /**
     * 设置进程标题
     *
     * @param  $name
     */
    protected function setProcessTitle($name)
    {
        if (function_exists('swoole_set_process_name')) {
            @swoole_set_process_name($name);
        } else {
            @cli_set_process_title($name);
        }
    }

    protected function beforeStart()
    {
        App::setServer($this->server);
        foreach ($this->beforeStart as $name => $handle) {
            if (!$handle instanceof BootInterface) {
                /**
                 * @var BootInterface $handle
                 */
                $handle = ObjectFactory::createObject($handle);
            }
            $handle->handle($this);
        }
    }

    public function stop()
    {
        if ($this->server->setting['pid_file']) {
            $pid = file_get_contents($this->server->setting['pid_file']);
            \swoole_process::kill(intval($pid));
        }
    }

    /**
     * @param $server
     */
    public function onStart($server)
    {
        $this->setProcessTitle($this->name . ': master');
        if ($this->server->setting['pid_file']) {
            file_put_contents($this->server->setting['pid_file'], $server->master_pid);
        }
    }

    public function onShutdown($server)
    {
        if ($this->server->setting['pid_file']) {
            unlink($this->server->setting['pid_file']);
        }
    }

    public function onWorkerStart($server, $worker_id)
    {
        if (!$server->taskworker) {
            //worker
            $this->setProcessTitle($this->name . ': worker' . ": {$worker_id}");
        } else {
            //task
            $this->setProcessTitle($this->name . ': task' . ": {$worker_id}");
        }
        $this->workerStart($server, $worker_id);
    }

    public function onWorkerStop($server, $worker_id)
    {
        if (extension_loaded('Zend OPcache')) {
            opcache_reset();
        }
    }

    public function onConnect($server, $fd, $from_id)
    {

    }

    public function onReceive($server, $fd, $from_id, $data)
    {

    }

    public function onPacket($server, $data, array $client_info)
    {

    }

    public function onClose($server, $fd, $from_id)
    {

    }

    /**
     * 处理异步任务
     *
     * @param swoole_server $serv
     * @param mixed $task_id
     * @param mixed $from_id
     * @param string $data
     */
    public function onTask($serv, $task_id, $from_id, $data)
    {
    }

    /**
     * 处理异步任务的结果
     *
     * @param swoole_server $serv
     * @param mixed $task_id
     * @param string $data
     */
    public function onFinish($serv, $task_id, $data)
    {
    }

    public function onPipeMessage($server, $from_worker_id, $message)
    {

    }

    public function onWorkerError($serv, $worker_id, $worker_pid, $exit_code)
    {

    }

    /**
     * @param $server
     */
    public function onManagerStart($server)
    {
        $this->setProcessTitle($this->name . ': manager');
    }

    public function onManagerStop($serv)
    {

    }
}