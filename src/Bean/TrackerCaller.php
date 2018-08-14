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
    private $endArgs;
    private $args;
    private $category;
    private $file;
    private $line;

    final function __construct(string $name,$args,$category)
    {
        $trace = debug_backtrace();
        $this->file = $trace[1]['file'];
        $this->line = $trace[1]['line'];
        $this->callerName = $name;
        $this->startTime = microtime(true);
        $this->args = $args;
        $this->category = $category;
    }

    function endCall(int $status = self::STATUS_SUCCESS,$endArg = null)
    {
        $this->status = $status;
        $this->endTime = microtime(true);
        $this->endArgs = $endArg;
    }

    /**
     * @return string
     */
    public function getCallerName(): string
    {
        return $this->callerName;
    }

    /**
     * @return mixed
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * @return mixed
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return mixed
     */
    public function getEndArgs()
    {
        return $this->endArgs;
    }

    public function getArgs()
    {
        return $this->args;
    }

    /**
     * @return mixed
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @return mixed
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @return mixed
     */
    public function getLine()
    {
        return $this->line;
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
            'category'=>$this->category,
            'caller'=>$this->callerName,
            'status'=>$status,
            'takeTime'=>$t,
            'file'=>$this->file,
            "line"=>$this->line,
            'startTime'=>$this->startTime,
            'args'=>$this->args,
            'endArgs'=>$this->endArgs
        ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}