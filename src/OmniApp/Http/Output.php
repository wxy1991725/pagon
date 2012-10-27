<?php

namespace OmniApp\Http;

use OmniApp\Data\MimeType;
use OmniApp\Registry;
use OmniApp\Exception\Stop;
use OmniApp\Config;
use OmniApp\App;

class Output extends Registry
{
    public static $messages = array(
        // Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',

        // Success 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',

        // Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found', // 1.1
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        // 306 is deprecated but reserved
        307 => 'Temporary Redirect',

        // Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',

        // Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        509 => 'Bandwidth Limit Exceeded'
    );

    /**
     * @var \OmniApp\App
     */
    public $app;

    /**
     * @var \OmniApp\Config Env
     */
    protected $env;

    // Send?
    protected $_send = false;

    /**
     * @param \OmniApp\App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;

        $this->env = new Config(array(
            'status'       => 200,
            'body'         => '',
            'content_type' => 'text/html',
            'length'       => 0,
            'charset'      => 'utf-8',
            'headers'      => array('CONTENT-TYPE' => 'text/html; charset=utf-8'),
            'cookies'      => array(),
        ));
    }

    /**
     * Set body
     *
     * @static
     * @param string $content
     * @return string|Output
     */
    public function body($content = null)
    {
        if ($content !== null) {
            if (ob_get_level() !== 0) {
                ob_end_clean();
                ob_start();
            }
            $this->env['body'] = $content;
            $this->env['length'] = strlen($this->env['body']);
            return $this;
        }

        return $this->env['body'];
    }

    /**
     * Get or set charset
     *
     * @param $charset
     * @return string|Output
     */
    public function charset($charset = null)
    {
        if ($charset) {
            $this->env['charset'] = $charset;
            $this->header('content-type', $this->env['content_type'] . '; charset=' . $this->env['charset']);
            return $this;
        }
        return $this->env['charset'];
    }

    /**
     * Write body
     *
     * @param string $data
     * @return string|Output
     */
    public function write($data)
    {
        if (!$data) return $this->env['body'];

        if (ob_get_level() !== 0) {
            $data = ob_get_clean() . $data;
            ob_start();
        }

        $this->env['body'] .= $data;
        $this->env['length'] = strlen($this->env['body']);

        return $this;
    }

    /**
     * End the response
     *
     * @param string $data
     * @throws \OmniApp\Exception\Stop
     * @return void
     */
    public function end($data = '')
    {
        $this->write($data);
        throw new Stop();
    }

    /**
     * Set status code
     *
     * @static
     * @param int $status
     * @return int|Output
     * @throws \Exception
     */
    public function status($status = null)
    {
        if ($status === null) {
            return $this->env['status'];
        } elseif (isset(self::$messages[$status])) {
            $this->env['status'] = (int)$status;
            return $this;
        } else throw new \Exception('Unknown status :value', array(':value' => $status));
    }

    /**
     * Set or get header
     *
     * @todo Array Support
     *
     * @param string $name
     * @param string $value
     * @param bool   $replace
     * @return array|Output
     */
    public function header($name = null, $value = null, $replace = false)
    {
        if ($name === null) {
            return $this->env['headers'];
        } else {
            $name = strtoupper(str_replace('_', '-', $name));
            if ($value === null) {
                return $this->env['headers'][$name];
            } else {
                $this->env['headers'][$name] = !$replace && !empty($this->env['headers'][$name]) ? $this->env['headers'][$name] . "\n" . $value : $value;
                return $this;
            }
        }
    }

    /**
     * Set content type
     *
     * @param string $mime_type
     * @return string|Output
     */
    public function contentType($mime_type = null)
    {
        if ($mime_type) {
            if (!strpos($mime_type, '/')) {
                $mime_type = MimeType::load()->{$mime_type}[0];
                if (!$mime_type) return $this->env['content_type'];
            }
            $this->env['content_type'] = $mime_type;

            $this->header('Content-Type', $this->env['content_type'] . '; charset=' . $this->env['charset']);
            return $this;
        }

        return $this->env['content_type'];
    }

    /**
     * Get or set cookie
     *
     * @param string             $key
     * @param array|string|mixed $value
     * @param array              $option
     * @return array|string|Output
     */
    public function cookie($key = null, $value = null, $option = array())
    {
        if ($value !== null) {
            $this->env['cookies'][$key] = array($value, $option);
            return $this;
        }

        if ($key === null) return $this->env['cookie'];
        return isset($this->env['cookies'][$key]) ? $this->env['cookies'][$key] : null;
    }

    /**
     * Get message by code
     *
     * @return string|null
     */
    public function message()
    {
        if (isset(self::$messages[$this->env['status']])) {
            return self::$messages[$this->env['status']];
        }
        return null;
    }

    /**
     * Send headers
     */
    public function sendHeader()
    {
        // Check headers
        if (headers_sent() === false) {
            // Send header
            header(sprintf('HTTP/%s %s %s', $this->app->input->protocol(), $this->env['status'], $this->message()));

            // Loop headers to send
            if ($this->env['headers']) {
                foreach ($this->env['headers'] as $name => $value) {
                    // Multiple line headers support
                    $h_values = explode("\n", $value);
                    foreach ($h_values as $h_val) {
                        header("$name: $h_val", false);
                    }
                }
            }

            // Set cookies
            if ($this->env['cookies']) {
                $_default = $this->app->config->cookie;
                if (!$_default) {
                    $_default = array(
                        'path'     => '/',
                        'domain'   => null,
                        'secure'   => false,
                        'httponly' => false,
                        'expires'  => 0,
                        'sign'     => false,
                        'secret'   => ''
                    );
                }
                // Loop for set
                foreach ($this->env['cookies'] as $key => $value) {
                    $_option = (array)$value[1] + $_default;
                    $value = $value[0];
                    // Json object cookie
                    if (is_array($value)) {
                        $value = 'j:' . json_encode($value);
                    }
                    // Sign cookie
                    if ($_option['sign'] && $_default['secret']) {
                        $value = 's:' . $value . '.' . hash_hmac('sha1', $value, $_default['secret']);
                    }
                    // Set cookie
                    setcookie($key, $value, $_option['expires'], $_option['path'], $_option['domain'], $_option['secure'], $_option['httponly']);
                }
            }
        }
        return $this;
    }

    /**
     * Send
     */
    public function send()
    {
        if ($this->_send) return;
        $this->sendHeader();
        echo $this->env['body'];
        $this->env['body'] = '';
        $this->_send = true;
    }

    /**
     * Set expires time
     *
     * @param string|int $time
     * @return array|null
     */
    public function expires($time = null)
    {
        if ($time) {
            if (is_string($time)) {
                $time = strtotime($time);
            }
            $this->header('Expires', gmdate(DATE_RFC1123, $time));
            return $this;
        }
        return $this->header('Expires');
    }

    /**
     * Render with template
     *
     * @param $template
     * @param $data
     * @return Output
     */
    public function render($template, $data = array())
    {
        $this->app->render($template, $data);
        return $this;
    }

    /**
     * To json
     *
     * @param mixed $data
     * @return Output
     */
    public function json($data)
    {
        $this->contentType('application/json');
        $this->body(json_encode($data));
        return $this;
    }

    /**
     * To jsonp
     *
     * @param mixed  $data
     * @param string $callback
     * @return Output
     */
    public function jsonp($data, $callback = 'callback')
    {
        $this->contentType('application/javascript');
        $this->body($callback . '(' . json_encode($data) . ');');
        return $this;
    }

    /**
     * To xml
     *
     * @param object|array $data
     * @param string       $root
     * @param string       $item
     * @return Output
     */
    public function xml($data, $root = 'root', $item = 'item')
    {
        $this->contentType('application/xml');
        $this->body(\OmniApp\Helper\XML::fromArray($data, $root, $item));
        return $this;
    }

    /**
     * Redirect url
     *
     * @param string $url
     * @param int    $status
     * @return Output
     */
    public function redirect($url, $status = 302)
    {
        $this->env['status'] = $status;
        $this->env['headers']['LOCATION'] = $url;
        return $this;
    }

    /**
     * Stop
     *
     * @throws Stop
     */
    public static function stop()
    {
        throw new Stop();
    }

    /**
     * Is empty?
     *
     * @return bool
     */
    public function isEmpty()
    {
        return in_array($this->env['status'], array(201, 204, 304));
    }

    /**
     * Is 200 ok?
     *
     * @return bool
     */
    public function isOk()
    {
        return $this->env['status'] === 200;
    }

    /**
     * Is successful?
     *
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->env['status'] >= 200 && $this->env['status'] < 300;
    }

    /**
     * Is redirect?
     *
     * @return bool
     */
    public function isRedirect()
    {
        return in_array($this->env['status'], array(301, 302, 303, 307));
    }

    /**
     * Is forbidden?
     *
     * @return bool
     */
    public function isForbidden()
    {
        return $this->env['status'] === 403;
    }

    /**
     * Is found?
     *
     * @return bool
     */
    public function isNotFound()
    {
        return $this->env['status'] === 404;
    }

    /**
     * Is client error?
     *
     * @return bool
     */
    public function isClientError()
    {
        return $this->env['status'] >= 400 && $this->env['status'] < 500;
    }

    /**
     * Is server error?
     *
     * @return bool
     */
    public function isServerError()
    {
        return $this->env['status'] >= 500 && $this->env['status'] < 600;
    }

    /**
     * Env
     *
     * @param $key
     * @return mixed
     */
    public function env($key = null)
    {
        if (is_array($key)) {
            $this->env = new Config($key);
            return $this->env;
        }

        if ($key === null) return $this->env;

        return isset($this->env[$key]) ? $this->env[$key] : null;
    }
}