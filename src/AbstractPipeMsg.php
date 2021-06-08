<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Rabbit\Base\App;
use Rabbit\Parser\MsgPackParser;
use Rabbit\Parser\ParserInterface;
use Rabbit\Base\Exception\InvalidConfigException;

/**
 * Class AbstractPipeMsg
 * @package Rabbit\Server
 */
abstract class AbstractPipeMsg
{
    protected ?ParserInterface $parser = null;
    private string $key = 'ipc.pipe.msg';

    public function __construct(ParserInterface $parser = null)
    {
        $this->parser = $parser ?? new MsgPackParser();
    }

    public function sendMessage(IPCMessage $msg): ?IPCMessage
    {
        if (null === $server = ServerHelper::getServer()) {
            App::warning("Not running in server, use local process");
            $msg->data !== null && CommonHandler::handler($this, $msg->data);
            return;
        }
        if (!$server instanceof Server) {
            throw new InvalidConfigException("only use for swoole_server");
        }
        $swooleServer = $server->getSwooleServer();

        if ($msg->to === -1) {
            $ids = $this->workerIds;
            unset($ids[$this->workerId]);
            $msg->to = $workerId = array_rand($ids);
        } elseif ($msg->to === $this->workerId) {
            $msg->data = $this->pipeMessage($swooleServer, $msg->data);
            return $msg;
        } else {
            $workerId = $msg->to;
        }

        if ($msg->wait !== 0) {
            if (false === $chan = getContext($msg->msgId)[$this->key] ?? false) {
                $chan = makeChannel();
                getContext($msg->msgId)[$this->key] = $chan;
            }
        }
        $swooleServer->sendMessage($this->parser->encode($msg), $workerId);
        if ($msg->wait !== 0) {
            $msg = $chan->pop();
            if ($msg->error !== null) {
                throw $msg->error;
            }
            return $msg;
        }
        return null;
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
        $msg = $this->parser->decode($message);
        if ($msg->finished) {
            if ($chan = getContext($msg->msgId)[$this->key] ?? false) {
                $chan->push($msg);
            }
        } else {
            $msg->data = $this->pipeMessage($server, $data);
            $msg->finished = true;
            if ($msg->wait !== 0 && $msg->from === $from_worker_id) {
                $msg->to = $msg->to ^ $msg->from;
                $msg->from = $msg->to ^ $msg->from;
                $msg->to = $msg->to ^ $msg->from;
                $this->sendMessage($msg);
            }
        }
    }

    abstract public function pipeMessage(\Swoole\Server $server, &$data): void;
}
