<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Closure;
use Rabbit\Base\Core\ShareResult;
use Swoole\Coroutine;
use Throwable;

class ProcessShare extends ShareResult
{
    private static array $shareResult = [];

    private static array $size = [];

    private static array $cids = [];

    private int $status = 0;

    const STATUS_PROCESS = -3;
    const STATUS_CHANNEL = -4;

    public function getStatus(): int
    {
        return $this->status;
    }

    public function __invoke(Closure $function): self
    {
        $this->count++;
        $id = ServerHelper::getLockId();
        try {
            $this->channel->push(1, $this->timeout);
            if ($this->channel->errCode === SWOOLE_CHANNEL_CLOSED) {
                if ($this->e !== null) {
                    throw $this->e;
                }
                $this->status = self::STATUS_CHANNEL;
                return $this;
            }

            if ($id === -1) {
                $this->result = call_user_func($function);
            } else {
                $ret = ServerHelper::sendMessage(new IPCMessage([
                    'data' => [static::class . "::getLock", [$this->key]],
                    'wait' => $this->timeout,
                    'to' => $id
                ]));
                if ($ret === 1) {
                    $this->result = call_user_func($function);
                    ServerHelper::sendMessage(new IPCMessage([
                        'data' => [static::class . "::setData", [$this->key, $this->result]],
                        'wait' => $this->timeout,
                        'to' => $id
                    ]));
                } else {
                    $this->result = ServerHelper::sendMessage(new IPCMessage([
                        'data' => [static::class . "::shared", [$this->key, $this->timeout]],
                        'wait' => $this->timeout,
                        'to' => $id
                    ]));
                    $this->status = self::STATUS_PROCESS;
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
                ServerHelper::sendMessage(new IPCMessage([
                    'data' => [static::class . "::unLock", [$this->key]],
                    'wait' => $this->timeout,
                    'to' => $id
                ]));
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
        self::$size[$name]--;
        if (self::$size[$name] === 0) {
            unset(self::$shareResult[$name]);
        }
        return self::$size[$name];
    }

    public static function shared(string $name, int $timeout = 3)
    {
        $data = share($name, function () use ($name, $timeout) {
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
            return self::$shareResult[$name] ?? null;
        });
        return $data->result;
    }

    public static function setData(string $name, $data): void
    {
        self::$shareResult[$name] = $data;
        if (self::$cids[$name] ?? false) {
            $cid = self::$cids[$name];
            unset(self::$cids[$name]);
            Coroutine::resume($cid);
        }
    }
}
