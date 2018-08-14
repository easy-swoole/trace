<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/8/14
 * Time: 下午12:46
 */

namespace EasySwoole\Trace;


use EasySwoole\Trace\AbstractInterface\LoggerWriterInterface;

class Logger
{
    private $loggerWriter;
    private $logDir;

    function __construct(string $logDir = null)
    {
        if(empty($logDir)){
            $logDir = getcwd();
        }
        $this->logDir = $logDir;
    }

    function setLoggerWriter(LoggerWriterInterface $loggerWriter):void
    {
        $this->loggerWriter = $loggerWriter;
    }

    public function log(string $str,$category = 'default'):Logger
    {
        if($this->loggerWriter instanceof LoggerWriterInterface){
            $this->loggerWriter->writeLog($str,$category,time());
        }else{
            $str = date("y-m-d H:i:s").":{$str}\n";
            $filePrefix = $category."_".date('ym');
            $filePath = $this->logDir."/{$filePrefix}.log";
            file_put_contents($filePath,$str,FILE_APPEND|LOCK_EX);
        }
        return $this;
    }

    public function console(string $str,$saveLog = 1){
        echo $str . "\n";
        if($saveLog){
            $this->log($str,'console');
        }
    }
}