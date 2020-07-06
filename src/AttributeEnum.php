<?php
declare(strict_types=1);

namespace Rabbit\Server;

/**
 * Class AttributeEnum
 * @package Rabbit\Server
 */
class AttributeEnum
{
    /**
     * The attribute of Router
     *
     * @var string
     */
    const ROUTER_ATTRIBUTE = 'requestHandler';

    /**
     * The attribute of response data
     *
     * @var string
     */
    const RESPONSE_ATTRIBUTE = 'responseAttribute';

    /**
     * The attribute of requesId
     *
     * @var string
     */
    const REQUESTID_ATTRIBUTE = 'requestId';

    /**
     * The attribute of connectFd
     *
     * @var int
     */
    const CONNECT_FD = 'connectFd';
}
