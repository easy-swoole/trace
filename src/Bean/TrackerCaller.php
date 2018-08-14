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
    const STATUS_NOT_END = -1;

    private $callerName;
    private $startTime;
    private $endTime;
    private $status = self::STATUS_NOT_END;
    private $endMsg;

    final function __construct(string $name,array $args)
    {
        $this->callerName = $name;
        $this->startTime = microtime(true);
        $this->args = $args;
    }

    function endCall(int $status = self::STATUS_SUCCESS,string $msg = null)
    {
        $this->status = $status;
        $this->endTime = microtime(true);
        $this->endMsg = $msg;
    }

    function __toString()
    {
        // TODO: Implement __toString() method.
        $status = null;
        switch ($this->status){
            case self::STATUS_SUCCESS:{
                $status = 'SUCCESS';
                break;
            }
            case self::STATUS_FAIL:{
                $status = 'FAIL';
                break;
            }
            default:{
                $status = 'NOT_END';
                break;
            }
        }
        $t = round($this->endTime - $this->startTime,3);
        if($t > 1000000){
            $t = -1;
        }
        return json_encode([
            'Caller'=>$this->callerName,
            'Status'=>$status,
            'StartTime'=>$this->startTime,
            'Args'=>$this->args,
            'EndMsg'=>$this->endMsg
        ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}