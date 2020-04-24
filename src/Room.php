<?php

namespace Sowe\PHPPeerServer;

use Sowe\PHPPeerServer\Exceptions\RoomIsFullException;
use Sowe\PHPPeerServer\Exceptions\ClientIsBannedException;
use Sowe\PHPPeerServer\Exceptions\ClientIsNotBannedException;
use Sowe\PHPPeerServer\Exceptions\ClientIsNotOwnerException;
use Sowe\PHPPeerServer\Exceptions\ClientIsNotInTheRoomException;

class Room{
    protected $id;
    protected $clients;
    protected $owner;
    protected $banned;
    
    public function __construct($id, $owner){
        $this->id = $id;
        $this->clients = new Mapping();
        $this->banned = new Mapping();
        $this->owner = $owner;

        $this->join($owner);
    }

    public function getId(){
        return $this->id;
    }
    public function getSocket($io){
        return $io->to($this->id);
    }
    /**
     * State helpers
     */
    public function isOwner($client){
        return $this->owner->equals($client);
    }
    public function isMember($client){
        return $this->clients->contains($client);
    }
    public function isBanned($client){
        return $this->banned->contains($client);
    }
    public function isFull(){
        return sizeof($this->clients) >= ROOM_MAX_CLIENTS;
    }

    /**
     * Actions
     */
    public function join($client){
        if($this->isFull()){
            throw new RoomIsFullException();
        }
        if($this->isBanned($client)){
            throw new ClientIsBannedException();
        }
        $this->clients->add($client);
        $client->setRoom($this);
    }

    public function leave($client){
        if($this->isOwner($client)){
            // @TODO: Make another client the owner.
        }
        $this->clients->remove($client);
        $client->setRoom(false);
    }

    public function kick($client, $clientToKick){
        if(!$this->isOwner($client)){
            throw new ClientIsNotOwnerException();
        }
        if(!$this->isMember($clientToKick)){
            throw new ClientIsNotInTheRoomException();
        }
        $this->clients->remove($clientToKick);
        $client->setRoom(false);
    }

    public function ban($client, $clientToBan){
        if(!$this->isOwner($client)){
            throw new ClientIsNotOwnerException();
        }
        if($this->isBanned($client)){
            throw new ClientIsBannedException();
        }
        
        $this->banned->add($clientToBan);

        if(!$this->isMember($clientToBan)){
            $this->clients->remove($clientToBan);
            $client->setRoom(false);
        }
    }

    public function unban($client, $clientToUnban){
        if(!$this->isOwner($client)){
            throw new ClientIsNotOwnerException();
        }
        if(!$this->isBanned($client)){
            throw new ClientIsNotBannedException();
        }
        $this->banned->remove($clientToUnban);
    }

}