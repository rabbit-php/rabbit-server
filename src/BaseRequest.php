<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/20
 * Time: 20:06
 */

namespace rabbit\server;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use rabbit\web\MessageTrait;
use rabbit\web\Uri;

/**
 * Class BaseRequest
 * @package rabbit\server
 */
class BaseRequest implements ServerRequestInterface
{
    use MessageTrait;
    /**
     * @var \Swoole\Http\Request
     */
    protected $swooleRequest;

    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var array
     */
    private $cookieParams = [];

    /**
     * @var null|array|object
     */
    private $parsedBody;

    /**
     * the body of parser
     *
     * @var mixed
     */
    private $bodyParams;

    /**
     * @var array
     */
    private $queryParams = [];

    /**
     * @var array
     */
    private $serverParams = [];

    /**
     * @var array
     */
    private $uploadedFiles = [];

    /**
     * @var string
     */
    private $method;

    /**
     * @var UriInterface|Uri
     */
    private $uri;

    /**
     * @var string
     */
    private $requestTarget;

    /**
     * BaseRequest constructor.
     * @param array $query
     * @param array $body
     * @param array $headers
     * @param array $server
     */
    public function __construct(array $query = [], array $body = [], array $headers = [], array $server = [])
    {
        $this->withQueryParams($query)
            ->withParsedBody($body)
            ->withHeaders($headers);
        $this->uri = self::getUriFromGlobals($headers, $server);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return Request|static
     */
    public function withAttribute($name, $value)
    {
        $clone = &$this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    /**
     * @param array|null|object $data
     * @return Request|static
     */
    public function withParsedBody($data)
    {
        $clone = &$this;
        $clone->parsedBody = $data;
        return $clone;
    }

    /**
     * @param array $query
     * @return Request|static
     */
    public function withQueryParams(array $query)
    {
        $clone = &$this;
        $clone->queryParams = $query;
        return $clone;
    }

    /**
     * @param array $headers
     * @return $this
     */
    private function setHeaders(array $headers): Request
    {
        $this->headers = [];
        foreach ($headers as $header => $value) {
            if (!is_array($value)) {
                $value = [$value];
            }

            $value = $this->trimHeaderValues($value);
            $normalized = strtolower($header);
            $this->headers[$normalized] = $value;
        }
        return $this;
    }

    /**
     * @param array $headers
     * @param array $server
     * @return Uri
     */
    private static function getUriFromGlobals(array $headers = [], array $server = []): Uri
    {
        $uri = new Uri();
        $uri = $uri->withScheme(!empty($server['https']) && $server['https'] !== 'off' ? 'https' : 'http');

        $hasPort = false;
        if (isset($server['http_host'])) {
            $hostHeaderParts = explode(':', $server['http_host']);
            $uri = $uri->withHost($hostHeaderParts[0]);
            if (isset($hostHeaderParts[1])) {
                $hasPort = true;
                $uri = $uri->withPort($hostHeaderParts[1]);
            }
        } elseif (isset($server['server_name'])) {
            $uri = $uri->withHost($server['server_name']);
        } elseif (isset($server['server_addr'])) {
            $uri = $uri->withHost($server['server_addr']);
        } elseif (isset($header['host'])) {
            if (\strpos($header['host'], ':')) {
                $hasPort = true;
                list($host, $port) = explode(':', $header['host'], 2);

                if ($port !== '80') {
                    $uri = $uri->withPort($port);
                }
            } else {
                $host = $header['host'];
            }

            $uri = $uri->withHost($host);
        }

        if (!$hasPort && isset($server['server_port'])) {
            $uri = $uri->withPort($server['server_port']);
        }

        $hasQuery = false;
        if (isset($server['request_uri'])) {
            $requestUriParts = explode('?', $server['request_uri']);
            $uri = $uri->withPath($requestUriParts[0]);
            if (isset($requestUriParts[1])) {
                $hasQuery = true;
                $uri = $uri->withQuery($requestUriParts[1]);
            }
        }

        if (!$hasQuery && isset($server['query_string'])) {
            $uri = $uri->withQuery($server['query_string']);
        }

        return $uri;
    }

    /**
     * @param array $serverParams
     * @return Request
     */
    public function withServerParams(array $serverParams): Request
    {
        $clone = &$this;
        $clone->serverParams = $serverParams;
        return $clone;
    }

    /**
     * @param array $cookies
     * @return Request|static
     */
    public function withCookieParams(array $cookies)
    {
        $clone = &$this;
        $clone->cookieParams = $cookies;
        return $clone;
    }

    /**
     * @return array
     */
    public function getServerParams()
    {
        return $this->serverParams;
    }

    /**
     * @return array
     */
    public function getCookieParams()
    {
        return $this->cookieParams;
    }

    /**
     * @return array
     */
    public function getQueryParams()
    {
        return $this->queryParams;
    }

    /**
     * @return array
     */
    public function getUploadedFiles()
    {
        return $this->uploadedFiles;
    }

    /**
     * @param array $uploadedFiles
     * @return Request|static
     */
    public function withUploadedFiles(array $uploadedFiles)
    {
        $clone = &$this;
        $clone->uploadedFiles = $uploadedFiles;
        return $clone;
    }

    /**
     * @return array|null|object
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param string $name
     * @param null $default
     * @return mixed|null
     */
    public function getAttribute($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /**
     * @param string $name
     * @return $this|Request|static
     */
    public function withoutAttribute($name)
    {
        if (false === array_key_exists($name, $this->attributes)) {
            return $this;
        }

        $clone = &$this;
        unset($clone->attributes[$name]);

        return $clone;
    }

    /**
     * @return string
     */
    public function getRequestTarget()
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target == '') {
            $target = '/';
        }
        if ($this->uri->getQuery() != '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    /**
     * @param mixed $requestTarget
     * @return Request|static
     */
    public function withRequestTarget($requestTarget)
    {
        if (preg_match('#\s#', $requestTarget)) {
            throw new \InvalidArgumentException('Invalid request target provided; cannot contain whitespace');
        }

        $clone = &$this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param string $method
     * @return Request|static
     */
    public function withMethod($method)
    {
        $method = strtoupper($method);
        $methods = ['GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'HEAD'];
        if (!in_array($method, $methods)) {
            throw new \InvalidArgumentException('Invalid Method');
        }
        $clone = &$this;
        $clone->method = $method;
        return $clone;
    }

    /**
     * @return UriInterface|Request|Uri
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @param UriInterface $uri
     * @param bool $preserveHost
     * @return $this|Request|static
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        if ($uri === $this->uri) {
            return $this;
        }

        $clone = &$this;
        $clone->uri = $uri;

        if (!$preserveHost) {
            $clone->updateHostFromUri();
        }

        return $clone;
    }

    /**
     *
     */
    private function updateHostFromUri(): void
    {
        $host = $this->uri->getHost();

        if ($host === '') {
            return;
        }

        if (($port = $this->uri->getPort()) !== null) {
            $host .= ':' . $port;
        }

        if ($this->hasHeader('host')) {
            $header = $this->getHeaderLine('host');
        } else {
            $header = 'Host';
        }
        // Ensure Host is the first header.
        $this->headers = [$header => [$host]] + $this->headers;
    }
}
