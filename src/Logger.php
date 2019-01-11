<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/8/14
 * Time: 下午12:46
 */

namespace EasySwoole\Trace;


use EasySwoole\Trace\AbstractInterface\LoggerInterface;

class Logger implements LoggerInterface
{
    private $logDir;

    function __construct(string $logDir = null)
    {
        if(empty($logDir)){
            $logDir = getcwd();
        }
        $this->logDir = $logDir;
    }

    public function log(string $str, $logCategory,int $timestamp = null)
    {
        // TODO: Implement log() method.
        if($timestamp == null){
            $timestamp = time();
        }
        $date = date('Y-m-d h:i:s',$timestamp);
        $filePrefix = $logCategory.'-'.date('Y-m-d',$timestamp);
        $filePath = $this->logDir."/{$filePrefix}.log";
        file_put_contents($filePath,"[$date][{$logCategory}]{$str}\n",FILE_APPEND|LOCK_EX);
    }

    public function console(string $str, $category = null, $saveLog = true)
    {
        // TODO: Implement console() method.
        if(empty($category)){
            $category = 'console';
        }
        $time = time();
        $date = date('Y-m-d h:i:s',$time);
        echo "[{$date}][{$category}]{$str}\n";
        if($saveLog){
            $this->log($str,$category,$time);
        }
    }
}