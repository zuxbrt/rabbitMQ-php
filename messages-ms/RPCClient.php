<?php

/**
 * Created by PhpStorm.
 * User: zulfo
 * Date: 23/10/2018
 * Time: 12:15
 */


use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RPCClient
{

    private $connection;
    private $channel;
    private $callback_queue;
    private $response;
    private $corr_id;

    /**
     * RPCClient constructor.
     */
    public function __construct()
    {
        $this->connection = new AMQPStreamConnection(
            'localhost',
            5672,
            'guest',
            'guest'
        );
        $this->channel = $this->connection->channel();
        list($this->callback_queue, ,) = $this->channel->queue_declare(
            "",
            false,
            false,
            true,
            false
        );
        $this->channel->basic_consume(
            $this->callback_queue,
            '',
            false,
            false,
            false,
            false,
            array(
                $this,
                'onResponse'
            )
        );
    }


    /**
     * On response
     *
     * @param $rep
     */
    public function onResponse($rep)
    {
        if ($rep->get('correlation_id') == $this->corr_id) {
            $this->response = $rep->body;
        }
    }

    /**
     * Call
     *
     * @param $data
     * @return int
     */
    public function getResponse($data)
    {
        $this->response = null;
        $this->corr_id = uniqid();


        $msg = new AMQPMessage(
            (string)$data,
            array(
                'correlation_id' => $this->corr_id,
                'reply_to' => $this->callback_queue
            )
        );

        $this->channel->basic_publish($msg, '', 'rpc_queue');
        while (!$this->response) {
            $this->channel->wait();
        }

        return ($this->response);
    }

}
