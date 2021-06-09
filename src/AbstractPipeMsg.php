<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Rabbit\Base\App;
use Rabbit\Parser\ParserInterface;
use Rabbit\Base\Exception\InvalidConfigException;
use Rabbit\Parser\MsgPackParser;

abstract class AbstractPipeMsg
{
    protected ParserInterface $parser;
    protected ParserInterface $closure;
    private string $key = 'ipc.pipe.msg';

    public function __construct(ParserInterface $parser = null)
    {
        $this->parser = $parser ?? create(MsgPackParser::class);
        $this->closure = create(ClosureParser::class);
    }

    public function sendMessage(IPCMessage $msg): ?IPCMessage
    {
        if (null === $server = ServerHelper::getServer()) {
            App::warning("Not running in server, use local process");
            $msg = CommonHandler::handler($this, $msg);
            if ($msg->error !== null) {
                throw $msg->error;
            }
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
            $msg = CommonHandler::handler($this, $msg);
            if ($msg->error !== null) {
                throw $msg->error;
            }
        } else {
            $workerId = $msg->to;
        }

        if ($msg->wait !== 0) {
            if (false === $chan = getContext($msg->msgId)[$this->key] ?? false) {
                $chan = makeChannel();
                getContext($msg->msgId)[$this->key] = $chan;
            }
        }

        if (is_callable($msg->data)) {
            $msg->isCallable = true;
            $msg->data = $this->closure->encode($msg->data);
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
            $msg = $this->pipeMessage($server, $msg);
            $msg->finished = true;
            if ($msg->wait !== 0 && $msg->from === $from_worker_id) {
                $msg->to = $msg->to ^ $msg->from;
                $msg->from = $msg->to ^ $msg->from;
                $msg->to = $msg->to ^ $msg->from;
                $this->sendMessage($msg);
            }
        }
    }

    abstract public function pipeMessage(\Swoole\Server $server, IPCMessage $msg): IPCMessage;
}
