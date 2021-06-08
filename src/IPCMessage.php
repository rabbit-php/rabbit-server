<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Throwable;

class IPCMessage
{
    public $data;
    public ?Throwable $error = null;
    public int $wait = 0;
    public int $from = 0;
    public int $to = -1;
    public int $msgId;
    public bool $finished = false;

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
