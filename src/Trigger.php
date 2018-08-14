<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/8/14
 * Time: 下午12:51
 */

namespace EasySwoole\Trace;


use EasySwoole\Trace\AbstractInterface\TriggerInterface;
use EasySwoole\Trace\Bean\Location;
use EasySwoole\Trace\DefaultHandler\TriggerHandler;

class Trigger
{
    private $handler = null;

    function __construct(TriggerInterface $trigger = null)
    {
        if(!$trigger instanceof TriggerInterface){
            $trigger = new TriggerHandler();
        }
        $this->handler = $trigger;
    }

    public function error($msg,Location $location = null)
    {
        if($location == null){
            $location = new Location();
            $debugTrace = debug_backtrace();
            $caller = array_shift($debugTrace);
            $location->setLine($caller['line']);
            $location->setFile($caller['file']);
        }
        $this->handler->error($msg,$location);
    }

    public function throwable(\Throwable $throwable)
    {
        $this->handler->throwable($throwable);
    }
}