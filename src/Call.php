<?php


namespace Sowe\PHPPeerServer;

use Sowe\PHPPeerServer\Client;
use Sowe\PHPPeerServer\Room;

class Call{
    private $id;
    private $client1;
    private $client2;
    private $offer;
    private $answer;

    public function __construct(Room $room, Client $client1, Client $client2){
        $this->id = $room->getId() . $client1->getId() . $client2->getId();
        $this->client1 = $client1;
        $this->client2 = $client2;

        $this->init();
    }

    /**
     * Call workflow
     */
    public function init(){
        $this->client1->getSocket()->emit("call", $this->id);
    }

    public function offer($offer){
        $this->client2->getSocket()->emit("offer", $this->id, $offer);
    }

    public function answer($answer){
        $this->client1->getSocket()->emit("answer", $this->id, $answer);
    }
    
    public function hangup(){
        $this->client1->getSocket()->emit("hangup", $this->id);
        $this->client2->getSocket()->emit("hangup", $this->id);
    }
    
    /**
     * Helpers
     */
    public function getId(){
        return $this->id;
    }

    public function clientCanOffer(Client $client){
        return $this->client1->equals($client);
    }

    public function clientCanAnswer(Client $client){
        return $this->client2->equals($client);
    }

    public function contains(Client $client){
        return $this->client1->equals($client) || 
            $this->client2->equals($client);
    }
}