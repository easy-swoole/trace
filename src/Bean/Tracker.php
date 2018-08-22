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

    /**
     * 添加参数
     * @param string|array $key
     * @param mixed $val
     * @return Tracker
     */
    function addAttribute($key, $val): Tracker
    {
        if (is_array($key)) {
            $this->stack = array_merge($this->stack, $key);
        } else {
            $this->attribute[$key] = $val;
        }
        return $this;
    }

    /**
     * 获取参数
     * @param string $key
     * @return mixed|null
     */
    function getAttribute($key)
    {
        if (isset($this->attribute[$key])) {
            return $this->attribute[$key];
        } else {
            return null;
        }
    }

    /**
     * 获取全部参数
     * @return array
     */
    function getAttributes(): array
    {
        return $this->attribute;
    }

    /**
     * 获取调用栈
     * @return array
     */
    function getStacks(): array
    {
        return $this->stack;
    }

    /**
     * 添加跟踪点
     * @param string $callName
     * @param mixed $args
     * @param string $category
     * @return TrackerCaller
     */
    function addCaller(string $callName, $args, $category = 'default'): TrackerCaller
    {
        $t = new TrackerCaller($callName, $args, $category);
        array_push($this->stack, $t);
        return $t;
    }

    /**
     * 转为字符串
     * @return string
     */
    function __toString()
    {
        // TODO: Implement __toString() method.
        $msg = "Attribute:\n";
        foreach ($this->attribute as $key => $value) {
            $msg .= "\t{$key}:{$value}\n";
        }
        $msg .= $this->stackToString($this->stack);
        return $msg;
    }

    /**
     * 按分类转为字符串
     * @param null|string $category
     * @return string
     */
    function toString($category = null)
    {
        if ($category) {
            $msg = "Attribute:\n\t" . json_encode($this->attribute, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            $list = [];
            foreach ($this->stack as $item) {
                if ($item->getCategory() == $category) {
                    array_push($list, $item);
                }
            }
            $msg .= $this->stackToString($list);
            return $msg;
        } else {
            return $this->__toString();
        }
    }

    /**
     * 调用栈转为字符串
     * @param array $stack
     * @return string
     * @author: eValor < master@evalor.cn >
     */
    private function stackToString(array $stack)
    {
        $msg = "Stack:\n";
        foreach ($stack as $item) {
            $msg .= "\t" . (string)$item . "\n";
        }
        return $msg;
    }
}