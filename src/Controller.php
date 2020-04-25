<?php

namespace Sowe\PHPPeerServer;

use Sowe\PHPPeerServer\Mapping;

use Sowe\PHPPeerServer\Exceptions\RoomIsFullException;
use Sowe\PHPPeerServer\Exceptions\RoomWrongPasswordException;
use Sowe\PHPPeerServer\Exceptions\ClientIsAlreadyInException;
use Sowe\PHPPeerServer\Exceptions\ClientIsBannedException;
use Sowe\PHPPeerServer\Exceptions\ClientIsNotBannedException;
use Sowe\PHPPeerServer\Exceptions\ClientIsNotOwnerException;
use Sowe\PHPPeerServer\Exceptions\ClientIsNotInTheRoomException;

class Controller{
    private $io;
    private $logger;
    private $clients;
    private $rooms;
    
    public function __construct($io, $logger){
        $this->io = $io;
        $this->logger = $logger;
        $this->clients = new Mapping();
        $this->rooms = new Mapping();
    }

    public function getClient($socket){
        $client = $socket->ppsClient;
        if($this->clients->hasKey($client->getId())){
            return $client;
        }
        return false;
    }

    public function handleException($client, $exception){
        $this->logger->error(__FUNCTION__.":".__LINE__ .":" . $client->getId() . ": " . $exception->getMessage());
    }

    /**
     * Actions
     */
    public function connect($socket){
        $client = new Client($socket->id, $socket);
        $socket->ppsClient = $client;
        $this->clients->add($client);

        $this->logger->info(__FUNCTION__.":".__LINE__ .":". $client->getId() .": connected (ONLINE: " . sizeof($this->clients) . ") (IP: " . $socket->conn->remoteAddress . ")");
    }

    public function disconnect($client, $reason){
        $this->clients->remove($client);

        $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . ": disconnected (ONLINE: " . sizeof($this->clients) . ") (Reason: " . $reason . ")");
    }

    public function message($client, $message){
        if(empty($message)){
            return;
        }

        $room = $client->getRoom();
        if($room !== false){
            $room->getSocket($this->io)->emit("r_message", [
                "userId" => $client->getId(),
                "message" => $message
            ]);
    
            $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " message in " . $room->getId() . ": " . $message);
        }
    }

    public function toggleResource($client, $resource){
        if($client->toggleResource($resource)){
            $room->getSocket($this->io)->emit("r_resource", [
                "userId" => $client->getId(),
                "resource" => $resource,
                "status" => $client->getResource($resource)
            ]);
        }
    }


    /**
     * Call management
     */
    public function candidate($client, $callId, $candidate){
        $room = $client->getRoom();
        if($room !== false){
            if($room->candidate($client, $callId, $candidate)){
                // Candidate sent
                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " sent a candidate " .  $callId);
                return;
            }
        }
        $this->logger->warning(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " tried to send a candidate " .  $callId);
    }

    public function offer(Client $client, $callId, $offer){
        $room = $client->getRoom();
        if($room !== false){
            if($room->offer($client, $callId, $offer)){
                // Offered
                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " offered " .  $callId);
                return;
            }
        }
        $this->logger->warning(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " tried to offer " .  $callId);
    }

    public function answer(Client $client, $callId, $answer){
        $room = $client->getRoom();
        if($room !== false){
            if($room->answer($client, $callId, $answer)){
                // Answered
                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " answered " .  $callId);
                return;
            }
        }
        $this->logger->warning(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " tried to answer " .  $callId);
    }

    /**
     * Room management
     */
    public function createRoom($client, $name, $password){
        do{
            $roomId = bin2hex(random_bytes(ROOM_HASH_LENGTH));
        }while($this->rooms->hasKey($roomId));

        $this->rooms->add(new Room($roomId, $name, $password, $client));
        $client->getSocket()->emit("created", $roomId);

        $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " created the room " . $roomId);
    }

    public function joinRoom($client, $roomId, $password){
        $currentRoom = $client->getRoom();
        $room = $this->rooms->get($roomId);

        if($currentRoom !== false){
            if($currentRoom->equals($room)){
                // Already in this room
                $this->logger->warning(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " tried to rejoin " . $roomId);
                
                $client->getSocket()->emit("join_alreadyin");
                return;
            }else{
                // Leaving previous room
                $this->leaveRoom($client);
            }
        }

        if($room !== false){
            try{
                $room->join($client, $password);
                // Joined
                $client->getSocket()->emit("joined", $room->getId());
                $room->getSocket($this->io)->emit("r_joined", $client->getId());

                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " joined " . $roomId);
            }catch(RoomWrongPasswordException $e){
                // Wrong room password
                $client->getSocket()->emit("join_wrongpass");

                $this->logger->error(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " join failed (wrong password) " . $roomId);
            }catch(RoomIsFullException $e){
                // Room is full
                $client->getSocket()->emit("join_full");

                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " join failed (full) " . $roomId);
            }catch(ClientIsBannedException $e){
                // Client is banned
                $client->getSocket()->emit("join_banned");

                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " join failed (banned) " . $roomId);
            }catch(ClientIsAlreadyInException $e){
                // Shouldnt happen because we're checking it earlier
                $this->logger->error(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " ClientIsAlreadyInException " . $roomId . ". Report this error.");
            }
        }

    }

    public function leaveRoom($client){
        $room = $client->getRoom();
        if($room !== false){
            $room->leave($client);
            $client->getSocket()->emit("left", $room->getId());
            $room->getSocket($this->io)->emit("r_left", $client->getId());

            $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " left " . $room->getId());
        }
    }

    public function kickFromRoom($client, $userId){
        $room = $client->getRoom();
        $clientToKick = $this->clients->get($userId);
        if($room !== false && $clientToKick !== false){
            try{
                $room->kick($client, $clientToKick);
                // Kicked
                $clientToKick->getSocket()->emit("kicked", $room->getId());
                $room->getSocket($this->io)->emit("r_kicked", $clientToKick->getId());

                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " kicked " . $clientToKick->getId() . " from " . $room->getId());
            }catch(ClientIsNotOwnerException $e){
                // Client is not the owner
                $client->getSocket()->emit("kick_noprivileges");

                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " failed kicking (privileges) " . $clientToKick->getId() . " from " . $room->getId());
            }catch(ClientIsNotInTheRoomException $e){
                // ClientToKick is no longuer in the room
                $client->getSocket()->emit("kick_notin");

                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " failed kicking (not in) " . $clientToKick->getId() . " from " . $room->getId());
            }
        }
    }

    public function banFromRoom($client, $userId){
        $room = $client->getRoom();
        $clientToBan = $this->clients->get($userId);
        if($room !== false && $clientToBan !== false){
            try{
                $room->ban($client, $clientToBan);
                // Banned
                $clientToBan->getSocket()->emit("banned", $room->getId());
                $room->getSocket($this->io)->emit("r_banned", $clientToBan->getId());

                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " banned " . $clientToBan->getId() . " from " . $room->getId());
            }catch(ClientIsNotOwnerException $e){
                // Client is not the owner
                $client->getSocket()->emit("ban_noprivileges");

                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " failed banning (privileges) " . $clientToBan->getId() . " from " . $room->getId());
            }catch(ClientIsBannedException $e){
                // ClientToBan is already banned
                $client->getSocket()->emit("ban_already");

                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " failed banning (already) " . $clientToBan->getId() . " from " . $room->getId());
            }
        }
    }

    public function unbanFromRoom($client, $userId){
        $room = $client->getRoom();
        $clientToUnban = $this->clients->get($userId);
        if($room !== false && $clientToUnban !== false){
            try{
                $room->unban($client, $clientToUnban);
                // Unbanned. Not notifying unbanned client.
                // $clientToUnban->getSocket()->emit("unbanned", $room->getId());
                $room->getSocket($this->io)->emit("r_unbanned", $clientToUnban->getId());

                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " unbanned " . $clientToUnban->getId() . " from " . $room->getId());
            }catch(ClientIsNotOwnerException $e){
                // Client is not the owner
                $client->getSocket()->emit("unban_noprivileges");
                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " failed unbanning (privileges) " . $clientToUnban->getId() . " from " . $room->getId());
            }catch(ClientIsNotBannedException $e){
                // ClientToUnban is not banned
                $client->getSocket()->emit("unban_notbanned");
                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " failed unbanning (not banned) " . $clientToUnban->getId() . " from " . $room->getId());
            }
        }
    }

}
