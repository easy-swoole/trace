<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/8/14
 * Time: 下午12:38
 */

namespace EasySwoole\Trace\AbstractInterface;


interface LoggerInterface
{
    public function log(string $str,$logCategory,int $timestamp = null):?string ;
    public function console(string $str,$category = 'console',$saveLog = true):?string ;
}