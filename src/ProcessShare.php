<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Closure;
use Psr\SimpleCache\CacheInterface;
use Rabbit\Base\Core\Context;
use Rabbit\Base\Core\ShareResult;
use Throwable;

class ProcessShare extends ShareResult
{
    use LockTrait;

    const STATUS_PROCESS = -3;
    const STATUS_CHANNEL = -4;

    const CACHE_KEY = 'share.status';

    private CacheInterface $cache;

    public function __construct(protected string $key, protected int $timeout = 3, string $type = 'share')
    {
        parent::__construct($key, $timeout);
        try {
            $this->cache = service('cache')->getDriver($type);
        } catch (Throwable $e) {
            $this->cache = service('cache')->getDriver('memory');
        }
    }

    public function getStatus(): int
    {
        return Context::get(self::CACHE_KEY) ?? 0;
    }

    public function __invoke(Closure $function): self
    {
        $this->count++;
        $id = ServerHelper::getLockId();
        $ret = $this->channel->push(1, $this->timeout);
        try {
            if ($this->channel->errCode === SWOOLE_CHANNEL_CLOSED) {
                if ($this->e !== null) {
                    throw $this->e;
                }
                if (Context::has(self::CACHE_KEY)) {
                    Context::set(self::CACHE_KEY, self::STATUS_CHANNEL);
                } else {
                    Context::set(self::CACHE_KEY, $this->channel->errCode);
                }
                return $this;
            }

            if ($id < 1 || $ret === false) {
                $this->result = call_user_func($function);
            } else {
                if (ServerHelper::sendMessage(new IPCMessage([
                    'data' => [static::class . "::lock", [$this->key]],
                    'wait' => $this->timeout,
                    'to' => $id
                ])) === 1) {
                    $this->result = call_user_func($function);
                    $this->cache->set($this->key, $this->result, $this->timeout);
                } else {
                    ServerHelper::sendMessage(new IPCMessage([
                        'data' => [static::class . "::shared", [$this->key, $this->timeout]],
                        'wait' => $this->timeout,
                        'to' => $id
                    ]));
                    $this->result = $this->cache->get($this->key);
                    Context::set(self::CACHE_KEY, self::STATUS_PROCESS);
                }
            }
            return $this;
        } catch (Throwable $throwable) {
            $this->e = $throwable;
            throw $throwable;
        } finally {
            if ($ret !== false) {
                if ($id > 1) {
                    if (ServerHelper::sendMessage(new IPCMessage([
                        'data' => [static::class . "::unLock", [$this->key]],
                        'wait' => $this->timeout,
                        'to' => $id
                    ])) === 0) {
                        $this->cache->delete($this->key);
                    }
                }
                $this->channel->close();
            }
            unset(self::$shares[$this->key]);
        }
    }
}
