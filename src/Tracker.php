<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/8/14
 * Time: 下午1:03
 */

namespace EasySwoole\Trace;


class Tracker
{
    private $tokenGenerator;
    private $stack = [];

    function getTracker($token = null)
    {
        $token = $this->token($token);
    }

    function removeTracker($token = null)
    {
        $token = $this->token($token);
    }

    function clear():Tracker
    {
        $this->stack = [];
        return $this;
    }

    function getTrackerStack():array
    {
        return $this->stack;
    }

    private function token($token)
    {
        if($token === null){
            if(is_callable($this->tokenGenerator)){
                $token = call_user_func($this->tokenGenerator);
            }
        }
        return $token;
    }
}