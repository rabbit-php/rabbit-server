<?php
declare(strict_types=1);

namespace Rabbit\Server;

use rabbit\App;
use rabbit\exception\InvalidConfigException;
use rabbit\parser\ParserInterface;
use rabbit\parser\PhpParser;

/**
 * Class AbstractPipeMsg
 * @package rabbit\server
 */
abstract class AbstractPipeMsg
{
    /** @var ParserInterface */
    protected $parser;
    /** @var \Swoole\Server */
    protected $server;

    /**
     * AbstractPipeMsg constructor.
     * @param ParserInterface $parser
     */
    public function __construct(ParserInterface $parser = null)
    {
        $this->parser = $parser ?? new PhpParser();
    }

    /**
     * @param $msg
     * @param int $workerId
     * @throws InvalidConfigException
     */
    public function sendMessage(&$msg, int $workerId): void
    {
        $server = App::getServer()->getSwooleServer();
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
     * @param $data
     */
    abstract public function pipeMessage(\Swoole\Server $server, &$data): void;
}