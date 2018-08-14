<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/8/14
 * Time: 下午1:21
 */

namespace EasySwoole\Trace\DefaultHandler;


use EasySwoole\Trace\AbstractInterface\TriggerInterface;
use EasySwoole\Trace\Bean\Location;
use EasySwoole\Trace\Logger;

class TriggerHandler implements TriggerInterface
{
    protected $logger;

    function __construct(Logger $logger = null)
    {
        if($logger == null){
            $logger = new Logger();
        }
        $this->logger = $logger;
    }

    public function error($msg, Location $location)
    {
        // TODO: Implement error() method.
        $debug = "Error at file[{$location->getFile()}] line[{$location->getLine()}] message:[{$msg}]";
        $this->logger->console($debug,false);
        $this->logger->log($debug,'debugError');
    }

    public function throwable(\Throwable $throwable)
    {
        // TODO: Implement throwable() method.
        $debug = "Exception at file[{$throwable->getFile()}] line[{$throwable->getLine()}] message:[{$throwable->getMessage()}]";
        $this->logger->console($debug,false);
        $this->logger->log($debug,'debugException');
    }
}