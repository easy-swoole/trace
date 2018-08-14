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
    private $args;
    private $category;

    final function __construct(string $name,array $args,$category)
    {
        $this->callerName = $name;
        $this->startTime = microtime(true);
        $this->args = $args;
        $this->category = $category;
    }

    function endCall(int $status = self::STATUS_SUCCESS,string $msg = null)
    {
        $this->status = $status;
        $this->endTime = microtime(true);
        $this->endMsg = $msg;
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
    public function getEndMsg()
    {
        return $this->endMsg;
    }

    /**
     * @return array
     */
    public function getArgs(): array
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
            'Category'=>$this->category,
            'Caller'=>$this->callerName,
            'Status'=>$status,
            'TakeTime'=>$t,
            'StartTime'=>$this->startTime,
            'Args'=>$this->args,
            'EndMsg'=>$this->endMsg
        ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}