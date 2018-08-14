<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/8/14
 * Time: 下午1:04
 */

namespace EasySwoole\Trace\Bean;


class Tracker
{
    private $attribute = [];
    private $stack = [];


    function addAttribute($key,$val):Tracker
    {
        $this->attribute[$key] = $val;
        return $this;
    }

    function getAttribute($key)
    {
        if(isset($this->attribute[$key])){
            return $this->attribute[$key];
        }else{
            return null;
        }
    }

    function getAttributes():array
    {
        return $this->attribute;
    }
}