<?php

namespace Sowe\PHPPeerServer;

use Sowe\PHPPeerServer\Room;

class Client{
    private $id;
    private $socket;
    private $room;
    private $resources;
    private $candidates;

    public function __construct($id, $socket){
        $this->id = $id;
        $this->socket = $socket;
        $this->room = false;

        $this->resources = [
            "screen" => false,
            "video" => false,
            "audio" => false
        ];

        $this->candidates = [];
    }
    
    public function getId(){
        return $this->id;
    }

    public function getSocket(){
        return $this->socket;
    }

    public function equals(Client $other){
        return $this->id === $other->getId();
    }

    /**
     * Resources
     */
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

    /**
     * Candidates
     */
    public function addCandidate($candidate){
        $this->candidates[] = $candidate;
    }

    public function getCandidates(){
        return $this->candidates;
    }

    /**
     * Room
     */
    public function getRoom(){
        return $this->room;
    }

    public function setRoom($room){
        if($this->room !== false){
            $this->socket->leave($this->room->getId());
        }
        if($room !== false){
            $this->socket->join($room->getId());
        }
        $this->room = $room;
    }
}
