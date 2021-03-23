#!/usr/bin/env php
<?php
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();

require_once(__dir__ . "/vendor/autoload.php");

ini_set("log_errors", 1);
ini_set("error_log", LOG_PATH);

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
$handler = new ErrorHandler($logger);
$handler->registerErrorHandler([], false);
$handler->registerExceptionHandler();
$handler->registerFatalHandler();

$controller = new Controller($logger);
$controller->bind();

Worker::runAll();

