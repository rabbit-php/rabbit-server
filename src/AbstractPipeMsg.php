<?php
declare(strict_types=1);

namespace Rabbit\Server;

use Rabbit\Base\App;
use Rabbit\Base\Exception\InvalidConfigException;
use Rabbit\Parser\MsgPackParser;
use Rabbit\Parser\ParserInterface;

/**
 * Class AbstractPipeMsg
 * @package rabbit\server
 */
abstract class AbstractPipeMsg
{
    /** @var ParserInterface */
    protected ParserInterface $parser;
    /** @var \Swoole\Server */
    protected \Swoole\Server $server;

    /**
     * AbstractPipeMsg constructor.
     * @param ParserInterface $parser
     */
    public function __construct(ParserInterface $parser = null)
    {
        $this->parser = $parser ?? new MsgPackParser();
    }

    /**
     * @param $msg
     * @param int $workerId
     * @throws InvalidConfigException
     */
    public function sendMessage(&$msg, int $workerId): void
    {
        $server = App::getServer();
        if (!$server instanceof \Swoole\Server) {
            throw new InvalidConfigException("only use for swoole_server");
        }
        $server->sendMessage($this->parser->encode($msg), $workerId);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $from_worker_id
     * @param string $message
     */
    public function handle(\Swoole\Server $server, int $from_worker_id, string $message): void
    {
        $data = $this->parser->decode($message);
        $this->pipeMessage($server, $data);
    }

    /**
     * @param \Swoole\Server $server
     * @param $data
     */
    abstract public function pipeMessage(\Swoole\Server $server, &$data): void;
}