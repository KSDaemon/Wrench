<?php
/**
 * Project: Wrench
 * User: KSDaemon
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
        $this->configure(array('on_data_callback' => $this->onMessage));
    }

    /**
     * Data receiver handler
     *
     * @param Payload $payload
     */
    protected function onMessage(Payload $payload)
    {
        echo "Data received!\n";
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

    }

    /**
     * Remove created prefix
     *
     * @param string $prefix
     */
    public function unprefix($prefix)
    {

    }

    /**
     * Make a RPC call to server
     *
     * @param string $procURI
     * @param callable $callbacks
     */
    public function call($procURI, $callbacks)
    {

    }

    /**
     * Subscribe to topic
     *
     * @param string $topicURI
     * @param callable $callback
     */
    public function subscribe($topicURI, $callback)
    {

    }

    /**
     * Unsubscribe from topic
     *
     * @param string $topicURI
     * @param callable $callback
     */
    public function unsubscribe($topicURI, $callback)
    {

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


}
