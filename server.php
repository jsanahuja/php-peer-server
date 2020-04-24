#!/usr/bin/env php
<?php
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();

require_once(__dir__ . "/vendor/autoload.php");

use Workerman\Worker;
use PHPSocketIO\SocketIO;

use Monolog\Logger;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

use Sowe\PHPPeerServer\Controller;

$logger = new Logger("");
$formatter = new LineFormatter("[%datetime%]:%level_name%: %message%\n", "Y-m-d\TH:i:s");
$stream = new StreamHandler(LOG_PATH, Logger::DEBUG);
$stream->setFormatter($formatter);
$logger->pushHandler($stream);
//  $handler = new ErrorHandler($logger);
//  $handler->registerErrorHandler([], false);
//  $handler->registerExceptionHandler();
//  $handler->registerFatalHandler();

$io = new SocketIO(PORT, array(
    'ssl' => array(
        'local_cert'  => CERT_CA,
        'local_pk'    => CERT_KEY,
        'verify_peer' => false,
        'allow_self_signed' => true,
        'verify_peer_name' => false
    )
));


$controller = new Controller($io, $logger);

$io->on('connection', function ($socket) use ($controller) {

    $controller->connect($socket);

    $socket->on("disconnect", function () use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->disconnect($client);
        }
    });

    $socket->on("message", function($message) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->message($client, $message);
        }
    });
    
    $socket->on("toggle", function($resource) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->toggleResource($client, $resource);
        }
    });

    $socket->on("create", function() use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->createRoom($client);
        }
    });

    $socket->on("join", function($roomId) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->joinRoom($client, $roomId);
        }
    });

    $socket->on("leave", function() use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->leaveRoom($client);
        }
    });

    $socket->on("kick", function($userId) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->kickFromRoom($client, $userId);
        }
    });

    $socket->on("ban", function($userId) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->banFromRoom($client, $userId);
        }
    });

    $socket->on("unban", function($userId) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->unbanFromRoom($client, $userId);
        }
    });

});

Worker::runAll();