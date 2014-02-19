<?php
/**
 * Project: Wrench
 * Author: Konstantin Burkalev
 * Date: 13.02.14
 */

namespace Wrench\Wamp;

use Wrench\Client as WSClient;
use Wrench\Payload\Payload;

/**
 * Class WampClient
 *
 * WAMP client.
 * @see http://wamp.ws
 *
 * @package Wrench\Wamp
 */
class WampClient extends WSClient {

    /**
     * WAMP SPEC message types
     */

    const TYPE_ID_WELCOME = 0;
    const TYPE_ID_PREFIX = 1;
    const TYPE_ID_CALL = 2;
    const TYPE_ID_CALLRESULT = 3;
    const TYPE_ID_CALLERROR = 4;
    const TYPE_ID_SUBSCRIBE = 5;
    const TYPE_ID_UNSUBSCRIBE = 6;
    const TYPE_ID_PUBLISH = 7;
    const TYPE_ID_EVENT = 8;

    /**
     * @var string
     */
    protected $sessionId = NULL;

    /**
     * @var array
     */
    protected $prefixMap = array();

    /**
     * @var array
     */
    protected $subscriptions = array();

    /**
     * @var array
     */
    protected $calls = array();

    /**
     * @var bool
     */
    protected $welcomeReceived = false;

    /**
     * @var int
     */
    protected $protocolVersion = 1;

    /**
     * @var string
     */
    protected $serverIdent = '';

    /**
     * Constructor
     *
     * @param string $uri
     * @param string $origin  The origin to include in the handshake (required
     *                          in later versions of the protocol)
     * @param array  $options (optional) Array of options
     *                         - socket   => Socket instance (otherwise created)
     *                         - protocol => Protocol
     */
    public function __construct($uri, $origin, array $options = array())
    {
        parent::__construct($uri, $origin, $options);
        $this->configure(array('on_data_callback' => array($this, 'onMessage')));
    }

    /**
     * Connect to the WAMP server
     *
     * @return boolean Whether a new connection was made
     */
    public function connect()
    {
        if ($this->isConnected()) {
            return false;
        }

        $this->socket->connect();

        $key       = $this->protocol->generateKey();
        $handshake = $this->protocol->getRequestHandshake(
            $this->uri,
            $key,
            $this->origin,
            $this->headers
        );

        $this->socket->send($handshake);
        $response = $this->socket->receive(self::MAX_HANDSHAKE_RESPONSE);
        $this->connected = $this->protocol->validateResponseHandshake($response, $key);
        if($this->connected) {
            $this->payloadHandler->handle($this->getRequestBody($response));
        }
        return $this->connected;
    }

    /**
     * Disconnect form WAMP Server
     */
    public function disconnect()
    {
        parent::disconnect();
        $this->prefixMap = array();
        $this->subscriptions = array();
        $this->calls = array();
        $this->welcomeReceived = false;
    }

    /**
     * Returns only body from $response
     *
     * @param string $response
     * @return string
     */
    protected function getRequestBody($response)
    {
        $parts = explode("\r\n\r\n", $response, 2);

        if (count($parts) != 2) {
            return '';
        } else {
            return $parts[1];
        }
    }

    /**
     * Process received WAMP message
     *
     * @param Payload $payload
     */
    protected function onMessage(Payload $payload)
    {
        $data = json_decode($payload->getPayload());

        switch ($data[0]) {
            case self::TYPE_ID_WELCOME:
                $this->welcomeReceived = true;
                $this->sessionId = $data[1];
                $this->protocolVersion = $data[2];
                $this->serverIdent = $data[3];
                break;
            case self::TYPE_ID_CALLRESULT:
                if(isset($this->calls[$data[1]]) && $this->calls[$data[1]]['success']) {
                    call_user_func($this->calls[$data[1]]['success'], $data[2]);
                }
                break;
            case self::TYPE_ID_CALLERROR:
                if(isset($this->calls[$data[1]]) && $this->calls[$data[1]]['error']) {
                    call_user_func($this->calls[$data[1]]['error'], $data[3], count($data) > 4 ? $data[4] : null);
                }
                break;
            case self::TYPE_ID_EVENT:
                $uri = $this->resolvePrefix($data[1]);
                if(isset($this->subscriptions[$uri])) {
                    foreach($this->subscriptions[$uri] as $callback) {
                        call_user_func($callback, $data[2]);
                    }
                }
                break;
        }
    }

    /**
     * Send message to WAMP Server
     *
     * @param $data
     * @return bool Success or fault
     */
    protected function send($data)
    {
        return $this->sendData(json_encode($data));
    }

    /**
     * Create a prefix for uri
     *
     * @param string $prefix
     * @param string $uri
     */
    public function prefix($prefix, $uri)
    {
        $this->prefixMap[$prefix] = $uri;
        $data = array(self::TYPE_ID_PREFIX, $prefix, $uri);
        $this->send($data);
    }

    /**
     * Remove created prefix
     *
     * @param string $prefix
     */
    public function unprefix($prefix)
    {
        unset($this->prefixMap[$prefix]);
    }

    /**
     * Make a RPC call to server
     *
     * @param string $procURI
     * @param callable $successCallback
     * @param callable $errorCallback
     * @throws \InvalidArgumentException
     */
    public function call($procURI, $successCallback, $errorCallback)
    {
        $args = func_get_args();
        array_shift($args);
        array_shift($args);
        $callId = $this->generateId();

        while(array_key_exists($callId, $this->calls)) {
            $callId = $this->generateId();
        }

        if(!is_callable($successCallback)) {
            throw new \InvalidArgumentException('No valid success callback specified');
        }

        if(isset($errorCallback) && !is_callable($errorCallback)) {
            throw new \InvalidArgumentException('No valid error callback specified');
        }

        $this->calls[$callId] = array('success' => $successCallback, 'error' => $errorCallback);

        $this->send(array_merge(array(self::TYPE_ID_CALL, $callId, $procURI), $args));
    }

    /**
     * Subscribe to topic
     *
     * @param string $topicURI
     * @param callable $callback
     * @throws \InvalidArgumentException
     */
    public function subscribe($topicURI, $callback)
    {
        $uri = $this->resolvePrefix($topicURI);

        if(!isset($this->subscriptions[$uri])) {
            $this->subscriptions[$uri] = array();
            $this->send(array(self::TYPE_ID_SUBSCRIBE, $topicURI));
        }

        if(!is_callable($callback)) {
            throw new \InvalidArgumentException('No valid callback specified');
        }

        if(!in_array($callback, $this->subscriptions[$uri])) {
            array_push($this->subscriptions[$uri], $callback);
        }
    }

    /**
     * Unsubscribe from topic
     *
     * @param string $topicURI
     * @param callable $callback
     */
    public function unsubscribe($topicURI, $callback = null)
    {
        $uri = $this->resolvePrefix($topicURI);

        if(isset($this->subscriptions[$uri])) {
            if($callback) {
                $key = array_search($callback, $this->subscriptions[$uri]);

                if($key !== false) {
                    array_splice($this->subscriptions[$uri], $key, 1);
                }

                if(count($this->subscriptions[$uri])) {
                    return ;
                }
            }

            $this->send(array(self::TYPE_ID_UNSUBSCRIBE, $topicURI));
            unset($this->subscriptions[$uri]);
        }
    }

    /**
     * Publish event to topic
     *
     * @param string $topicURI
     * @param object $event
     * @param bool|array $exclude
     * @param array $eligible
     */
    public function publish($topicURI, $event, $exclude = false, $eligible = array())
    {
        $data = array(self::TYPE_ID_PUBLISH, $topicURI, $event, $exclude, $eligible);
        $this->send($data);
    }

    /**
     * Generate unique id
     *
     * @return string
     */
    protected function generateId()
    {
        $keyChars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
        $keyCharsLen = count($keyChars);
        $keyLen = 16;
        $key = "";

        for($i = 0; $i < $keyLen; $i++) {
            $key .= $keyChars[rand(0, $keyCharsLen)];
        }

        return $key;
    }

    /**
     * Resolve prefix to url
     *
     * @param string $prefix
     * @return string
     */
    protected function resolvePrefix($prefix)
    {
        if (array_key_exists($prefix, $this->prefixMap)) {
            return $this->prefixMap[$prefix];
        } else {
            return $prefix;
        }
    }
}
