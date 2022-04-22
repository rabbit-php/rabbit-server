<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Closure;
use Rabbit\Base\Contract\LockInterface;
use Rabbit\Base\Core\Channel;
use RuntimeException;
use Throwable;

class ProcessLock implements LockInterface
{
    use LockTrait;

    protected readonly Channel $channel;
    private static array $shares = [];

    public function __construct(protected string $key, protected int $timeout = 3)
    {
        $this->key = 'lock-' . $key;
        $this->channel = new Channel();
        if (self::$shares[$key] ?? false) {
            throw new RuntimeException("$key is exists!");
        }
        self::$shares[$key] = $this;
    }

    public static function getLock(string $key, int $timeout): self
    {
        if (self::$shares[$key] ?? false) {
            return self::$shares[$key];
        }
        return new static($key, $timeout);
    }

    public function __invoke(string $name, Closure $function,  bool $next = true, float $timeout = 600): void
    {
        if (!$this->channel->isEmpty() && !$next) {
            return;
        }
        $id = ServerHelper::getLockId();
        $ret = $this->channel->push(1, $this->timeout);
        try {
            if ($id < 1 || $ret === false) {
                call_user_func($function);
            } else {
                if (ServerHelper::sendMessage(new IPCMessage([
                    'data' => [static::class . "::lock", [$this->key]],
                    'wait' => $this->timeout,
                    'to' => $id
                ])) === 1) {
                    call_user_func($function);
                } else {
                    ServerHelper::sendMessage(new IPCMessage([
                        'data' => [static::class . "::shared", [$this->key, $this->timeout]],
                        'wait' => $this->timeout,
                        'to' => $id
                    ]));
                    call_user_func($function);
                }
            }
        } catch (Throwable $throwable) {
            throw $throwable;
        } finally {
            if ($id > 1 && $ret !== false) {
                ServerHelper::sendMessage(new IPCMessage([
                    'data' => [static::class . "::unLock", [$this->key]],
                    'wait' => $this->timeout,
                    'to' => $id
                ]));
            }
            if (!$this->channel->isEmpty()) {
                $this->channel->pop();
            }
        }
    }
}
