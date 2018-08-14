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

    function addCaller(string $callName,$args,$category = 'default'):TrackerCaller
    {
        $t = new TrackerCaller($callName,$args,$category);
        array_push($this->stack,$t);
        return $t;
    }

    function __toString()
    {
        // TODO: Implement __toString() method.
        $msg = "Attribute:\n\t".json_encode($this->attribute,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
        $msg .= $this->stackToString($this->stack);
        return $msg;
    }


    function toString($category = null)
    {
        if($category){
            $msg = "Attribute:\n\t".json_encode($this->attribute,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
            $list = [];
            foreach ($this->stack as $item){
                if($item->getCategory() == $category){
                    array_push($list,$item);
                }
            }
            $msg .= $this->stackToString($list);
            return $msg;
        }else{
            return $this->__toString();
        }
    }

    private function stackToString(array $stack)
    {
        $msg = "Stack:\n";
        foreach ($stack as $item){
            $msg .= "\t".(string)$item."\n";
        }
        return $msg;
    }
}