
## php-aria2 【只适配window】

### 安装
~~~
composer require ibibicloud/php-aria2
~~~

### 用法
~~~
<?php

// composer自动加载
require __DIR__ . '/vendor/autoload.php';

use \ibibicloud\facade\Aria2;

// 下载文件 + 重命名 + 指定保存目录
var_dump(Aria2::addDownload(
    'https://www.douyin.com/aweme/v1/play/?video_id=v0300fg10000d777g0fog65nduuq9scg&line=0&file_id=fe222b60c54a4ce88a0d10d3470e2b2d',
    '乡土中国味 - 老菜单里的功夫菜！.mp4',		// 重命名
    'D:/下载'								// 保存目录
));

// 获取单个任务状态
var_dump(Aria2::getTaskStatus('b9ab58d193b74449'));

// 获取所有任务状态
var_dump(Aria2::getAllTasks());

// 关闭
var_dumpAria2::stop());
~~~

