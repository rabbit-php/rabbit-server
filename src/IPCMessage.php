<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Throwable;

class IPCMessage
{
    public array|float|int|string|object $data;
    public ?Throwable $error = null;
    public float $wait = 0;
    public int $from = 0;
    public int $to = -1;
    public int $msgId;
    public bool $finished = false;
    public bool $isCallable = false;

    public function __construct(array $columns = [])
    {
        foreach ($columns as $name => $value) {
            if (property_exists($this, $name)) {
                $this->$name = $value;
            }
        }
        $this->msgId = getCid();
    }
}
