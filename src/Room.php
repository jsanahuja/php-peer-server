<?php

namespace Sowe\PHPPeerServer;

use Sowe\PHPPeerServer\Call;
use Sowe\PHPPeerServer\Mapping;
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
    protected $data;
    
    public function __construct($id, $owner){
        $this->id = $id;
        $this->clients = new Mapping();
        $this->banned = new Mapping();
        $this->owner = $owner;
        $this->calls = new Mapping();

        $this->join($owner);
    }

    public function getId(){
        return $this->id;
    }
    public function getSocket($io){
        return $io->to($this->id);
    }

    /**
     * Calls
     */
    public function createCalls(Client $client){
        foreach($this->clients as $c){
            $this->calls->add(new Call($this, $c, $client));
        }
    }

    public function removeCalls(Client $client){
        foreach($this->calls as $call){
            if($call->contains($client)){
                $call->hangup();
                $this->calls->remove($call);
            }
        }
    }

    public function candidate(Client $client, $callId, $candidate){
        $call = $this->calls->get($callId);
        if($call !== false && $call->contains($client)){
            $call->candidate($client, $candidate);
            return true;
        }
        return false;
    }

    public function offer(Client $client, $callId, $offer){
        $call = $this->calls->get($callId);
        if($call !== false && $call->clientCanOffer($client)){
            $call->offer($offer);
            return true;
        }
        return false;
    }

    public function answer(Client $client, $callId, $answer){
        $call = $this->calls->get($callId);
        if($call !== false && $call->clientCanAnswer($client)){
            $call->answer($answer);
            return true;
        }
        return false;
    }

    /**
     * Status helpers
     */
    public function isOwner(Client $client){
        return $this->owner->equals($client);
    }

    public function isMember(Client $client){
        return $this->clients->contains($client);
    }

    public function isBanned(Client $client){
        return $this->banned->contains($client);
    }

    public function isFull(){
        return sizeof($this->clients) >= ROOM_MAX_CLIENTS;
    }

    /**
     * Actions
     */
    public function join(Client $client){
        if($this->isFull()){
            throw new RoomIsFullException();
        }
        if($this->isBanned($client)){
            throw new ClientIsBannedException();
        }
        $this->clients->add($client);
        $this->createCalls($client);
        $client->setRoom($this);
    }

    public function leave(Client $client){
        if($this->isOwner($client)){
            // @TODO: Make another client the owner or disband the call.
        }
        $this->clients->remove($client);
        $this->removeCalls($client);
        $client->removeCalls(false);
    }

    public function kick(Client $client, Client $clientToKick){
        if(!$this->isOwner($client)){
            throw new ClientIsNotOwnerException();
        }
        if(!$this->isMember($clientToKick)){
            throw new ClientIsNotInTheRoomException();
        }
        $this->clients->remove($clientToKick);
        $this->removeCalls($clientToKick);
        $client->setRoom(false);
    }

    public function ban(Client $client, Client $clientToBan){
        if(!$this->isOwner($client)){
            throw new ClientIsNotOwnerException();
        }
        if($this->isBanned($client)){
            throw new ClientIsBannedException();
        }
        
        $this->banned->add($clientToBan);

        if(!$this->isMember($clientToBan)){
            $this->clients->remove($clientToBan);
            $this->removeCalls($clientToBan);
            $client->setRoom(false);
        }
    }

    public function unban(Client $client, Client $clientToUnban){
        if(!$this->isOwner($client)){
            throw new ClientIsNotOwnerException();
        }
        if(!$this->isBanned($client)){
            throw new ClientIsNotBannedException();
        }
        $this->banned->remove($clientToUnban);
    }

}