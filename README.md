调试器组件 (可选组件)
============

扩展框架的调试能力，支持在各个方法处埋点，获得请求完整的调用链信息，方便排查调试

```php

$t = new \EasySwoole\Trace\TrackerManager();

$tracker = $t->getTracker('test');

$tracker->addAttribute('userName','用户1');
$tracker->addAttribute('userToken','userToken');

//sql one
$caller = $tracker->addCaller('查询用户余额','sql statement one');
//模拟sql one执行
//$mode->func();
usleep(3000);
$caller->endCall();

//curl api
$caller = $tracker->addCaller('消息api查询','请求第三方api');
//模拟curl执行 timeout
//$mode->func();
sleep(1);
$caller->endCall($caller::STATUS_FAIL,'xxxx超时');

echo $tracker->toString();
```