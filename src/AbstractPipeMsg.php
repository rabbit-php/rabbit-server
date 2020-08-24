<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Rabbit\Base\Contract\InitInterface;
use Rabbit\Base\Core\Context;
use Rabbit\Base\Exception\InvalidConfigException;
use Rabbit\Parser\MsgPackParser;
use Rabbit\Parser\ParserInterface;
use Rabbit\Server\ServerHelper;
use Swoole\Coroutine\Channel;
use Swoole\Server;

/**
 * Class AbstractPipeMsg
 * @package Rabbit\Server
 */
abstract class AbstractPipeMsg implements InitInterface
{
    protected ParserInterface $parser;
    protected Server $server;
    protected array $ids = [];

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
     * @return void
     */
    public function init(): void
    {
        $this->server = ServerHelper::getServer()->getSwooleServer();
        if (!$this->server instanceof Server) {
            throw new InvalidConfigException("only use for swoole_server");
        }
        $this->ids = range(0, $this->server->setting['worker_num'] - 1);
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
        if ($workerId === -1) {
            $ids = $this->ids;
            unset($ids[$this->server->workerId]);
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
     * @param Server $server
     * @param integer $from_worker_id
     * @param string $message
     * @return void
     */
    public function handle(Server $server, int $from_worker_id, string $message): void
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
     * @param Server $server
     * @param [type] $data
     * @return void
     */
    abstract public function pipeMessage(Server $server, &$data): void;
}
