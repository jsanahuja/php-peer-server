<?php

namespace Sowe\PHPPeerServer;

use Sowe\PHPPeerServer\Mapping;

use Sowe\PHPPeerServer\Exceptions\RoomIsFullException;
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

    public function connect($socket){
        $client = new Client($socket->id, $socket);
        $socket->ppsClient = $client;
        $this->clients->add($client);

        $this->logger->info(__FUNCTION__.":".__LINE__ .":". $socket->conn->remoteAddress .": connected with ID: ". $socket->id . " (ONLINE: " . sizeof($this->clients) . ")");
    }

    public function disconnect($client){
        $this->clients->remove($client);

        $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . ": disconnected (ONLINE: " . sizeof($this->clients) . ")");
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

    public function candidate($client, $candidate){
        $client->addCandidate($candidate);
        
        $room = $client->getRoom();
        if($room !== false){
            $room->getSocket($this->io)->emit("r_candidate", $client->getId(), $candidate);
        }

        $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " added a candidate");
    }

    /**
     * Room management
     */
    public function createRoom($client, $offer){
        do{
            $roomId = bin2hex(random_bytes(ROOM_HASH_LENGTH));
        }while($this->rooms->hasKey($roomId));

        $room = new Room($roomId, $client);
        $room->setData("offer", $offer);
        $this->rooms->add($room);
        $client->getSocket()->emit("created", $roomId);

        $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " created the room " . $roomId);
    }

    public function getRoom($client, $roomId){
        $room = $this->rooms->get($roomId);
        if($room !== false){
            // Getting
            $client->getSocket()->emit("gotten", $room->getData("offer"));
        }
    }

    public function joinRoom($client, $roomId, $answer){
        $room = $this->rooms->get($roomId);

        $this->leaveRoom($client);

        if($room !== false){
            try{
                $room->join($client);
                // Joined
                $room->setData("answer", $answer);
                $client->getSocket()->emit("joined", $room->getId());
                $room->getSocket($this->io)->emit("r_joined", $client->getId(), $room->getData("answer"));

                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " joined " . $roomId);
            }catch(RoomIsFullException $e){
                // Room is full
                $client->getSocket()->emit("join_full");

                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " join failed (full) " . $roomId);
            }catch(ClientIsBannedException $e){
                // Client is banned
                $client->getSocket()->emit("join_banned");

                $this->logger->info(__FUNCTION__.":".__LINE__ .":" . $client->getId() . " join failed (banned) " . $roomId);
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
                // Unbanned
                $clientToUnban->getSocket()->emit("unbanned", $room->getId());
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
