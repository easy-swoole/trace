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

class Trigger
{
    private static $handler = null;
    static function setHandler(TriggerInterface $trigger):void
    {
        self::$handler = $trigger;
    }

    public static function error($msg,Location $location = null)
    {
        if($location == null){
            $location = new Location();
            $debugTrace = debug_backtrace();
            $caller = array_shift($debugTrace);
            $location->setLine($caller['line']);
            $location->setFile($caller['file']);
        }


        $func = self::$handler;
        if($func instanceof TriggerInterface){
            $func::error($msg,$location);
        }else{
            $debug = "Error at file[{$location->getFile()}] line[{$location->getLine()}] message:[{$msg}]";
            Logger::getInstance()->log($debug,'debug');
            Logger::getInstance()->console($debug,false);
        }
    }

    public static function throwable(\Throwable $throwable)
    {
        $func = self::$handler;
        if($func instanceof TriggerInterface){
            $func::throwable($throwable);
        }else{
            $debug = "Exception at file[{$throwable->getFile()}] line[{$throwable->getLine()}] message:[{$throwable->getMessage()}]";
            Logger::getInstance()->log($debug,'debug');
            Logger::getInstance()->console($debug,false);
        }
    }

    private static function getDebugTrace()
    {
        $bean = new Location();
        $dd = debug_backtrace ();
        $caller = next ($dd);
        while (isset ($caller) &&  $caller["file"] == __FILE__ ){
            $caller = next($dd);
        }
        $bean->setFile($caller['file']);
        $bean->setLine($caller['line']);
        return $bean;
    }
}