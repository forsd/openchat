<?php
/**
 * Chat Class Doc Comment
 *
 * PHP version 5
 *
 * @category PHP
 * @package  OpenChat
 * @author   Ankit Jain <ankitjain28may77@gmail.com>
 * @license  The MIT License (MIT)
 * @link     https://github.com/ankitjain28may/openchat
 */
namespace ChatApp;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
// use ChatApp\Models\Message;
use ChatApp\Reply;
use ChatApp\Conversation;
use ChatApp\Receiver;
use ChatApp\SideBar;
use ChatApp\Search;
use ChatApp\Compose;
use ChatApp\Online;

/**
 * This Class handles the all the main functionalities for this ChatApp.
 *
 * @category PHP
 * @package  OpenChat
 * @author   Ankit Jain <ankitjain28may77@gmail.com>
 * @license  The MIT License (MIT)
 * @link     https://github.com/ankitjain28may/openchat
 */

class Chat implements MessageComponentInterface
{
    /*
    |--------------------------------------------------------------------------
    | Chat Class
    |--------------------------------------------------------------------------
    |
    | This Class handles the all the main functionalities for this ChatApp.
    |
    */

    protected $clients;

    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
    }

    /**
     * Open the Socket Connection and get client connection
     *
     * @param ConnectionInterface $conn To store client details
     *
     * @return void
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $conn = $this->setID($conn);
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
        Online::setOnlineStatus($conn->userId);
    }

    /**
     * Set Session Id in Connection object
     *
     * @param ConnectionInterface $conn To store client details
     *
     * @return $conn
     */
    public function setID($conn)
    {
        session_id($conn->WebSocket->request->getCookies()['PHPSESSID']);
        @session_start();
        $conn->userId = $_SESSION['start'];
        session_write_close();
        return $conn;
    }

    /**
     * Send Messages to Clients
     *
     * @param ConnectionInterface $from To store client details
     * @param string              $msg  To store message
     *
     * @return void
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        $msg = (object)json_decode($msg);
        if ($msg->type == 'OpenChat initiated..!') {
            $initial = (object)array();
            $initial->initial = json_decode($this->onSidebar($from->userId));

            if ($initial->initial != null) {
                $initial->conversation = json_decode(
                    $this->onConversation(
                        json_encode(
                            [
                            "details" => $initial->initial[0]->login_id,
                            "load" => 20,
                            "userId" => $from->userId
                            ]
                        ), true
                    )
                );
            }
            $from->send(json_encode($initial));
        } else if ($msg->type == 'Load Sidebar') {
            $sidebar = (object)array();
            $sidebar->sidebar = json_decode($this->onSidebar($from->userId));
            $from->send(json_encode($sidebar));
        } else if ($msg->type == 'Initiated') {
            $msg->userId = $from->userId;
            $result = (object)array();
            $result->conversation = json_decode(
                $this->onConversation(json_encode($msg), false)
            );
            $from->send(json_encode($result));
        } else if ($msg->type == 'Search') {
            $msg->userId = $from->userId;
            $searchResult = $this->onSearch($msg);
            $from->send($searchResult);
        } else if ($msg->type == 'Compose') {
            $msg->userId = $from->userId;
            $composeResult = $this->onCompose($msg);
            $from->send($composeResult);
        } else if ($msg->type == 'typing') {
            $msg->userId = $from->userId;
            $msg->name = convert_uudecode(hex2bin($msg->name));
            foreach ($this->clients as $client) {
                if ($client->userId == $msg->name) {
                    $client->send(json_encode(array('typing' => 'typing')));
                }
            }
        } else {
            $msg->userId = $from->userId;
            $msg->name = convert_uudecode(hex2bin($msg->name));

            $getReturn = $this->onReply($msg);
            echo $getReturn;

            $receiveResult = (object)array();
            $sentResult = (object)array();
            foreach ($this->clients as $client) {
                if ($client->userId == $msg->name) {
                    $receiveResult->sidebar = json_decode(
                        $this->onSidebar($client->userId)
                    );

                    $receiveResult->reply = json_decode(
                        $this->onReceiver(
                            json_encode(
                                [
                                "details" => $client->userId,
                                "load" => 20,
                                "userId" => $from->userId
                                ]
                            ), true
                        )
                    );

                    $client->send(json_encode($receiveResult));
                } else if ($client == $from) {
                    $sentResult->sidebar = json_decode(
                        $this->onSidebar($client->userId)
                    );

                    $sentResult->conversation = json_decode(
                        $this->onConversation(
                            json_encode(
                                [
                                "details" => bin2hex(convert_uuencode($msg->name)),
                                "load" => 20,
                                "userId" => $from->userId
                                ]
                            ), true
                        )
                    );
                    $client->send(json_encode($sentResult));
                }
            }

        }
    }

    /**
     * To Call SideBar Class
     *
     * @param string $data To store data
     *
     * @return string
     */
    public function onSidebar($data)
    {
        $obSidebar = new SideBar();
        return $obSidebar->loadSideBar($data);
    }

    /**
     * To Call Conversation Class
     *
     * @param string  $data to store data
     * @param boolean $para to store True/False
     *
     * @return string
     */
    public function onConversation($data, $para)
    {
        $obConversation = new Conversation();
        return $obConversation->conversationLoad($data, $para);
    }

    /**
     * To Call Receiver Class
     *
     * @param string  $data to store data
     * @param boolean $para to store True/False
     *
     * @return string
     */
    public function onReceiver($data, $para)
    {
        $obReceiver = new Receiver();
        return $obReceiver->receiverLoad($data, $para);
    }

    /**
     * To Call Search Class
     *
     * @param string $data to store data
     *
     * @return string
     */
    public function onSearch($data)
    {
        $obSearch = new Search();
        return $obSearch->searchItem($data);
    }

    /**
     * To Call Compose Class
     *
     * @param string $data to store data
     *
     * @return string
     */
    public function onCompose($data)
    {
        $obCompose = new Compose();
        return $obCompose->selectUser($data);
    }

    /**
     * To Call Reply Class
     *
     * @param string $data to store data
     *
     * @return string
     */
    public function onReply($data)
    {
        $obReply = new Reply();
        return $obReply->replyTo($data);
    }

    /**
     * To Call Online Class
     *
     * @param ConnectionInterface $conn To store client details
     *
     * @return void
     */
    public function onClose(ConnectionInterface $conn)
    {
        Online::removeOnlineStatus($conn->userId);
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    /**
     * To Show error due to any problem occured
     *
     * @param ConnectionInterface $conn To store client details
     * @param \Exception          $e    To store exception
     *
     * @return void
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

}
