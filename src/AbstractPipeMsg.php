<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Rabbit\Base\App;
use Rabbit\Base\Core\Context;
use Rabbit\Base\Exception\InvalidConfigException;
use Rabbit\Parser\MsgPackParser;
use Rabbit\Parser\ParserInterface;
use Rabbit\Server\ServerHelper;
use Swoole\Coroutine\Channel;

/**
 * Class AbstractPipeMsg
 * @package Rabbit\Server
 */
abstract class AbstractPipeMsg
{
    protected ?ParserInterface $parser = null;

    /**
     * @author Albert <63851587@qq.com>
     * @param ParserInterface $parser
     */
    public function __construct(ParserInterface $parser = null)
    {
        $this->parser = $parser ?? new MsgPackParser();
    }

    /**
     * @author Albert <63851587@qq.com>
     * @param [type] $msg
     * @param integer $workerId
     * @param integer $wait
     * @return void
     */
    public function sendMessage(&$msg, int $workerId, float $wait = 0): void
    {
        if (null === $server = ServerHelper::getServer()) {
            App::warning("Not running in server, use local process");
            $msg !== null && CommonHandler::handler($this, $msg);
            return;
        }
        if (!$server instanceof Server) {
            throw new InvalidConfigException("only use for swoole_server");
        }
        $this->ids = range(0, $this->server->setting['worker_num'] - 1);
        if ($workerId === -1) {
            $ids = $this->ids;
            unset($ids[$this->server->worker_id]);
            $workerId = array_rand($ids);
        }
        $msg = [$msg, $wait];
        $this->server->sendMessage($this->parser->encode($msg), $workerId);
        if ($wait !== 0) {
            if (!$chan = Context::get('pipemsg.chan')) {
                $chan = new Channel(1);
                Context::set('pipemsg.chan', new Channel(1));
            }
            $chan->pop($wait);
        }
    }

    /**
     * @author Albert <63851587@qq.com>
     * @param \Swoole\Server $server
     * @param integer $from_worker_id
     * @param string $message
     * @return void
     */
    public function handle(\Swoole\Server $server, int $from_worker_id, string $message): void
    {
        $data = $this->parser->decode($message);
        [$data, $wait] = $data;
        $data !== null && $this->pipeMessage($server, $data);
        if ($wait !== 0) {
            if ($data === null) {
                return;
            }
            $null = null;
            $this->sendMessage($null, $from_worker_id, $wait);
        }
    }

    /**
     * @author Albert <63851587@qq.com>
     * @param \Swoole\Server $server
     * @param [type] $data
     * @return void
     */
    abstract public function pipeMessage(\Swoole\Server $server, &$data): void;
}
