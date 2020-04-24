<?php

namespace Sowe\PHPPeerServer;

use Sowe\PHPPeerServer\Room;

class Client{
    private $id;
    private $socket;
    private $room;
    private $resources;

    public function __construct($id, $socket){
        $this->id = $id;
        $this->socket = $socket;
        $this->room = false;

        $this->resources = [
            "screen" => false,
            "video" => false,
            "audio" => false
        ];
    }

    public function toggleResource($resource){
        if(isset($this->resources[$resource])){
            $this->resources[$resource] = !$this->resources[$resource];
            return true;
        }
        return false;
    }

    public function getResource($resource){
        if(isset($this->resources[$resource])){
            return $this->resources[$resource];
        }
    }


    public function getId(){
        return $this->id;
    }

    public function getSocket(){
        return $this->socket;
    }

    public function getRoom(){
        return $this->room;
    }

    public function setRoom($room){
        if($this->room !== false){
            $client->getSocket()->leave($this->room->getId());
        }
        if($room !== false){
            $client->getSocket()->join($room->getId());
        }
        $this->room = $room;
    }

    public function equals(Client $other){
        return $this->id === $other->getId();
    }
}