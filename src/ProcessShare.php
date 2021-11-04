<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Closure;
use Psr\SimpleCache\CacheInterface;
use Rabbit\Base\Core\Context;
use Rabbit\Base\Core\ShareResult;
use Swoole\Coroutine;
use Throwable;

class ProcessShare extends ShareResult
{
    private static array $size = [];

    private static array $cids = [];

    const STATUS_PROCESS = -3;
    const STATUS_CHANNEL = -4;

    private CacheInterface $cache;

    public function __construct(string $key, int $timeout = 3)
    {
        parent::__construct($key, $timeout);
        $this->cache = getDI('cache')->getDriver('memory');
    }

    public function getStatus(): int
    {
        return Context::get('share.status') ?? 0;
    }

    public function __invoke(Closure $function): self
    {
        $this->count++;
        $id = ServerHelper::getLockId();
        $ret = 0;
        try {
            $this->channel->push(1, $this->timeout);
            if ($this->channel->errCode === SWOOLE_CHANNEL_CLOSED) {
                if ($this->e !== null) {
                    throw $this->e;
                }
                Context::set('share.status', self::STATUS_CHANNEL);
                return $this;
            }

            if ($id === -1) {
                $this->result = call_user_func($function);
            } else {
                $msg = new IPCMessage([
                    'data' => [static::class . "::getLock", [$this->key]],
                    'wait' => $this->timeout,
                    'to' => $id
                ]);
                $ret = ServerHelper::sendMessage($msg);
                if ($ret === 1) {
                    $this->result = call_user_func($function);
                    $this->cache->set($this->key, $this->result, $this->timeout);
                } else {
                    $msg->data = [static::class . "::shared", [$this->key, $this->timeout]];
                    ServerHelper::sendMessage($msg);
                    $this->result = $this->cache->get($this->key);
                    Context::set('share.status', self::STATUS_PROCESS);
                }
            }
            return $this;
        } catch (Throwable $throwable) {
            $this->e = $throwable;
            throw $throwable;
        } finally {
            unset(self::$shares[$this->key]);
            $this->channel->close();
            if ($id >= 0) {
                $ret === 1 && ServerHelper::sendMessage(new IPCMessage([
                    'data' => [static::class . "::unLock", [$this->key]],
                    'wait' => $this->timeout,
                    'to' => $id
                ]));
                $this->cache->delete($this->key);
            }
        }
    }

    public static function getLock(string $name): int
    {
        if (!isset(self::$size[$name])) {
            self::$size[$name] = 1;
            return 1;
        }
        self::$size[$name]++;
        return self::$size[$name];
    }

    public static function unLock(string $name): int
    {
        unset(self::$size[$name]);
        if (self::$cids[$name] ?? false) {
            $cid = self::$cids[$name];
            unset(self::$cids[$name]);
            Coroutine::resume($cid);
        }
        return 0;
    }

    public static function shared(string $name, int $timeout = 3): void
    {
        share($name, function () use ($name, $timeout) {
            self::$cids[$name] = Coroutine::getCid();
            if ($timeout > 0) {
                rgo(function () use ($name, $timeout) {
                    sleep($timeout);
                    if (self::$cids[$name] ?? false) {
                        $cid = self::$cids[$name];
                        unset(self::$cids[$name]);
                        Coroutine::resume($cid);
                    }
                });
            }
            Coroutine::yield();
        });
    }
}
