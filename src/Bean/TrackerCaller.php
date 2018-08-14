<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/8/14
 * Time: 下午1:04
 */

namespace EasySwoole\Trace\Bean;


class TrackerCaller
{
    const STATUS_SUCCESS = 1;
    const STATUS_FAIL = 0;

    private $callerName;
    private $startTime;
    private $endTime;
    private $status = -1;
    private $startMsg;
    private $endMsg;

    function __construct(string $name,string $msg)
    {
        $this->callerName = $name;
        $this->startTime = microtime(true);
        $this->startMsg;
    }


    function endCall(int $status = self::STATUS_SUCCESS,string $msg = null)
    {
        $this->status = $status;
        $this->endTime = microtime(true);
        $this->endMsg = $msg;
    }
}