<?php

namespace Nece\Brawl\FileSystem\Cos;

use Exception;
use Nece\Brawl\ConfigAbstract;
use Nece\Brawl\FileSystem\FileSystemAbstract;
use Nece\Brawl\FileSystem\FileSystemException;
use Qcloud\Cos\Client;
use Throwable;

class FileSystem extends FileSystemAbstract
{
    private $client;
    private $bucket;
    private $region;

    /**
     * 设置配置
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-18
     *
     * @param ConfigAbstract $config
     *
     * @return void
     */
    public function setConfig(ConfigAbstract $config)
    {
        parent::setConfig($config);

        $this->bucket = $this->getConfigValue('bucket');
        $this->region = $this->getConfigValue('region');
    }

    /**
     * 获取客户端
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-18
     *
     * @return \Qcloud\Cos\Client
     */
    private function getClient()
    {
        if (!$this->client) {

            $secretId = $this->getConfigValue('secret_id');
            $secretKey = $this->getConfigValue('secret_key');
            $schema = $this->getConfigValue('schema', 'https', false);
            $timeout = $this->getConfigValue('timeout', 3600, false);
            $connect_timeout = $this->getConfigValue('connect_timeout', 3600, false);
            $proxy = $this->getConfigValue('proxy');

            $conf = array(
                'region' => $this->region, //用户的 region，已创建桶归属的 region 可以在控制台查看，https://console.cloud.tencent.com/cos5/bucket
                'schema' => $schema,
                'timeout' => $timeout,
                'connect_timeout' => $connect_timeout,
                'proxy' => $proxy,

                'credentials' => array(
                    'secretId'  => $secretId, //用户的 SecretId，建议使用子账号密钥，授权遵循最小权限指引，降低使用风险。子账号密钥获取可参考https://cloud.tencent.com/document/product/598/37140
                    'secretKey' => $secretKey //用户的 SecretKey，建议使用子账号密钥，授权遵循最小权限指引，降低使用风险。子账号密钥获取可参考https://cloud.tencent.com/document/product/598/37140
                )
            );

            $this->client = new Client($conf);
        }
        return $this->client;
    }

    /**
     * 写文件内容
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-17
     *
     * @param string $path 相对路径
     * @param string $content
     *
     * @return void
     */
    public function write(string $path, string $content): void
    {
        $object_key = $this->buildPathWithSubPath($path);
        try {
            // 使用追加的方式写入，让以后还可以追加内容
            // $this->getClient()->putObject();
            $this->getClient()->appendObject(array(
                'Bucket' => $this->bucket, //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
                'Key' => $object_key,
                'Position' => 0,
                'Body' => $content
            ));
        } catch (Throwable $e) {
            $this->error_message = $e->getMessage();
            throw new FileSystemException('写文件内容失败');
        }

        $this->setUri($object_key);
    }

    /**
     * 追加文件内容
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-17
     *
     * @param string $path 相对路径（已存在的文件）
     * @param string $content
     *
     * @return void
     */
    public function append(string $path, string $content): void
    {
        try {
            $position = 0;
            $object_key = $path;
            if ($this->exists($object_key)) {
                // 先取原有文件长度
                $result = $this->cosHead($object_key);
                $position = intval($result['ContentLength']);
            }

            // 追加内容
            $this->getClient()->appendObject(array(
                'Bucket' => $this->bucket, //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
                'Key' => $object_key,
                'Position' => $position,
                'Body' => $content
            ));
        } catch (Throwable $e) {
            $this->error_message = $e->getMessage();
            throw new FileSystemException('追加文件内容失败');
        }

        $this->setUri($object_key);
    }

    /**
     * 复制文件
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-17
     *
     * @param string $source 相对路径
     * @param string $destination 相对路径
     *
     * @return void
     */
    public function copy(string $source, string $destination): void
    {
        $object_key = $this->buildPathWithSubPath($destination);

        try {
            $this->cosCopy($source, $object_key);
        } catch (Throwable $e) {
            $this->error_message = $e->getMessage();
            throw new FileSystemException('复制文件失败');
        }

        $this->setUri($object_key);
    }

    /**
     * 移动文件
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-17
     *
     * @param string $source 相对路径
     * @param string $destination 相对路径
     *
     * @return void
     */
    public function move(string $source, string $destination): void
    {
        $object_key = $this->buildPathWithSubPath($destination);

        try {
            $this->cosCopy($source, $object_key);
            $this->cosDelete($source);
        } catch (Throwable $e) {
            $this->error_message = $e->getMessage();
            throw new FileSystemException('移动文件失败');
        }

        $this->setUri($object_key);
    }

    /**
     * 上传文件
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-17
     *
     * @param string $local 绝对路径（硬盘路径）
     * @param string $to 相对路径
     *
     * @return void
     */
    public function upload(string $local, string $to): void
    {
        $object_key = $this->buildPathWithSubPath($to);

        try {
            $this->getClient()->upload(
                $this->bucket, //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
                $object_key, //此处的 key 为对象键
                fopen($local, 'rb')
            );
        } catch (Throwable $e) {
            $this->error_message = $e->getMessage();
            throw new FileSystemException('上传文件失败');
        }

        $this->setUri($object_key);
    }

    /**
     * 文件是否存在
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-17
     *
     * @param string $path 相对路径
     *
     * @return boolean
     */
    public function exists(string $path): bool
    {
        try {
            return $this->getClient()->doesObjectExist(
                $this->bucket, //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
                $path //对象名
            );
        } catch (Throwable $e) {
            $this->error_message = $e->getMessage();
            throw new FileSystemException('文件存在判断失败');
        }
    }

    /**
     * 读取文件内容
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-17
     *
     * @param string $path 相对路径
     *
     * @return string
     */
    public function read(string $path): string
    {
        try {
            $result = $this->getClient()->getObject(array(
                'Bucket' => $this->bucket, //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
                'Key' => $path,
                'Range' => 'bytes=first-last'
            ));

            if (isset($result['Body'])) {
                return $result['Body']->getContents();
            } else {
                throw new Exception('请求结果返回不正确');
            }
        } catch (Throwable $e) {
            $this->error_message = $e->getMessage();
            throw new FileSystemException('读取文件内容失败');
        }
    }

    /**
     * 删除文件
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-17
     *
     * @param string $path 相对路径
     *
     * @return void
     */
    public function delete(string $path): void
    {
        try {
            $this->cosDelete($path);
        } catch (Throwable $e) {
            $this->error_message = $e->getMessage();
            throw new FileSystemException('删除文件失败');
        }
    }

    /**
     * 创建目录
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-17
     *
     * @param string $path 相对路径
     *
     * @return void
     */
    public function mkDir(string $path): void
    {
        try {
            $this->getClient()->putObject(array(
                'Bucket' => $this->bucket, //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
                'Key' => $path,
                'Body' => '',
            ));
        } catch (Throwable $e) {
            $this->error_message = $e->getMessage();
            throw new FileSystemException('创建目录失败');
        }
    }

    /**
     * 获取最后更新时间
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-17
     *
     * @param string $path 相对路径
     *
     * @return integer
     */
    public function lastModified(string $path): int
    {
        try {
            $result = $this->cosHead($path);
            return strtotime($result['LastModified']);
        } catch (Throwable $e) {
            $this->error_message = $e->getMessage();
            throw new FileSystemException('获取最后更新时间失败');
        }
    }

    /**
     * 获取文件大小(字节数)
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-17
     *
     * @param string $path 相对路径
     *
     * @return integer
     */
    public function fileSize(string $path): int
    {
        try {
            $result = $this->cosHead($path);
            return intval($result['ContentLength']);
        } catch (Throwable $e) {
            $this->error_message = $e->getMessage();
            throw new FileSystemException('获取最后更新时间失败');
        }
    }

    /**
     * 列表
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-17
     *
     * @param string $path 相对路径
     *
     * @return array
     */
    public function readDir(string $path): array
    {
        try {
            $list = array();
            $result = $this->cosListObjects($path);
            foreach ($result['Contents'] as $row) {
                $list[] = $row['Key'];
            }

            return $list;
        } catch (Throwable $e) {
            $this->error_message = $e->getMessage();
            throw new FileSystemException('列表失败');
        }
    }

    /**
     * 构建URL
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-18
     *
     * @param string $uri
     * @param int $expires 过期时间（秒）
     *
     * @return string
     */
    public function buildUrl(string $uri, $expires = null)
    {
        if ($expires) {
            return $this->getClient()->getObjectUrl($this->bucket, $uri, '+' . intval($expires / 60) . ' minutes');
        } else {
            return $this->getClient()->getObjectUrlWithoutSign($this->bucket, $uri);
            // return parent::buildUrl($uri);
        }
    }

    /**
     * 获取签名url
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-17
     * 
     * @param string $path 相对路径
     * @param int $expires 过期时间（秒）
     *
     * @return string
     */
    public function buildPreSignedUrl(string $path, $expires = null): string
    {
        $conf = array(
            'Bucket' => $this->bucket, //存储桶，格式：BucketName-APPID
            'Key' => $path, //对象在存储桶中的位置，即对象键
            'Params' => array(), //http 请求参数，传入的请求参数需与实际请求相同，能够防止用户篡改此HTTP请求的参数,默认为空
            'Headers' => array(), //http 请求头部，传入的请求头部需包含在实际请求中，能够防止用户篡改签入此处的HTTP请求头部,默认已签入host
        );

        if ($expires) {
            $signedUrl = $this->getClient()->getPreSignedUrl('getObject', $conf, '+' . intval($expires / 60) . ' minutes'); //签名的有效时间
        } else {
            $signedUrl = $this->getClient()->getPreSignedUrl('getObject', $conf);
        }

        return $signedUrl;
    }

    /**
     * 复制
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-18
     *
     * @param string $source_object_key
     * @param string $object_key
     *
     * @return \GuzzleHttp\Command\Result
     */
    private function cosCopy($source_object_key, $object_key)
    {
        $result = $this->getClient()->Copy(
            $this->bucket, //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
            $object_key, //此处的 key 为对象键
            array(
                'Region' => $this->region,
                'Bucket' => $this->bucket,
                'Key' => $source_object_key,
            )
        );

        // print_r($result);

        return $result;
    }

    /**
     * 删除
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-18
     *
     * @param string $source_object_key
     *
     * @return \GuzzleHttp\Command\Result
     */
    private function cosDelete($source_object_key)
    {
        $result = $this->getClient()->deleteObject(array(
            'Bucket' => $this->bucket, //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
            'Key' => $source_object_key,
        ));

        // print_r($result);

        return $result;
    }

    /**
     * 取文件元数据
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-18
     *
     * @param string $object_key
     *
     * @return \GuzzleHttp\Command\Result
     */
    private function cosHead($object_key)
    {
        $result = $this->getClient()->headObject(array(
            'Bucket' => $this->bucket, //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
            'Key' => $object_key,
        ));

        // print_r($result);
        return $result;
    }

    /**
     * 查询对象列表
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-18
     *
     * @param string $path
     * @param integer $limit
     * @param string $delimiter
     * @param string $marker
     *
     * @return \GuzzleHttp\Command\Result
     */
    private function cosListObjects(string $path, $limit = 1000, $delimiter = '', $marker = null)
    {
        $params = array(
            'Bucket' => $this->bucket, //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
            'Delimiter' => $delimiter, //Delimiter表示分隔符, 设置为/表示列出当前目录下的object, 设置为空表示列出所有的object
            'EncodingType' => 'url', //编码格式，对应请求中的 encoding-type 参数
            'Prefix' => $path, //Prefix表示列出的object的key以prefix开始
            'MaxKeys' => $limit, // 设置最大遍历出多少个对象, 一次listObjects最大支持1000
        );

        if ($marker) {
            $params['Marker'] = $marker;
        }

        return $this->getClient()->listObjects($params);
    }
}
