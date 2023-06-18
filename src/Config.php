<?php

namespace Nece\Brawl\FileSystem\Cos;

use Nece\Brawl\ConfigAbstract;

class Config extends ConfigAbstract
{
    public function buildTemplate()
    {
        $this->addTemplate(true, 'secret_id', 'secretId', '请登录访问管理控制台进行查看和管理，https://console.cloud.tencent.com/cam/capi');
        $this->addTemplate(true, 'secret_key', 'secretKey ', '请登录访问管理控制台进行查看和管理，https://console.cloud.tencent.com/cam/capi');
        $this->addTemplate(true, 'bucket', '存储桶', '参考：https://console.cloud.tencent.com/cos5/bucket');
        $this->addTemplate(true, 'region', '区域', '创建桶归属的region', '参考：https://console.cloud.tencent.com/cos5/bucket');
        $this->addTemplate(true, 'base_url', '基础URL', '例：http(s)://xxxxx.com');
        $this->addTemplate(false, 'sub_path', '子目录', '例：a/b');

        $this->addTemplate(false, 'schema', '协议头部', '默认为https');
        $this->addTemplate(false, 'timeout', '请求超时', '秒', '600');
        $this->addTemplate(false, 'connect_timeout', '连接超时', '秒', '60');
        $this->addTemplate(false, 'proxy', '代理', '例：http://xxx:xx');
    }
}
