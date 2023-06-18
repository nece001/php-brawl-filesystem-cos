# php-brawl-filesystem-cos
php 文件存储基础服务适配项目（腾讯云COS）

# 依赖

composer require qcloud/cos-sdk-v5

# 示例

```php
    $conf = array(
        'secret_id' => 'xxx',
        'secret_key' => 'xxx',
        'bucket' => 'xxx',
        'region' => 'ap-shanghai',
        'sub_path' => 'uploads/test',
        'base_url' => 'https://xxxxxx'
    );

    $config = FileSystemFactory::createConfig('Cos');
    $config->setConfig($conf);

    $fso = FileSystemFactory::createClient($config);
    try {

        $fso->write('c/' . time() . '.txt', 'test');
        $fso->append('uploads/test/c/1687066427.txt', '[test]');
        $fso->append('uploads/test/c/1687066091.txt', '[test]');

        $fso->copy('uploads/test/c/1687060306.txt', 'a/1.txt');
        $fso->move('uploads/test/c/1687060494.txt', 'a/2.txt');
        $fso->upload('D:\Work\temp\ttt.txt', 'a/3.txt');

        var_dump($fso->exists('uploads/test/c/1687060306.txt'));
        echo $fso->read('uploads/test/c/1687066427.txt');
        $fso->delete('uploads/test/a/3.txt');

        $fso->mkDir('a/d');
        echo $fso->lastModified('uploads/test/c/1687060306.txt');
        echo $fso->fileSize('uploads/test/c/1687066427.txt');
        print_r($fso->readDir('uploads/test/c'));

        echo $fso->getUri(), '<br>';
        echo $fso->getUrl(), '<br>';
        echo $fso->buildPreSignedUrl('uploads/test/c/1687066427.txt');
    } catch (Throwable $e) {
        echo $e->getMessage(), '<br>';
        echo $fso->getErrorMessage();
    }
```