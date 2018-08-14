<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/8/14
 * Time: 下午12:39
 */

namespace EasySwoole\Trace\AbstractInterface;


use EasySwoole\Trace\Bean\Location;

interface TriggerInterface
{
    public static function error($msg,Location $location);
    public static function throwable(\Throwable $throwable);
}