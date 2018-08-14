<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/8/14
 * Time: 下午12:38
 */

namespace EasySwoole\Trace\AbstractInterface;


interface LoggerWriterInterface
{
    function writeLog($obj,$logCategory,$timeStamp);
}