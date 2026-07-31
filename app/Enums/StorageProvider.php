<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

enum StorageProvider: string implements LabeledEnum
{
    case Generic = 'generic';
    case Aws = 'aws';
    case R2 = 'r2';
    case Aliyun = 'aliyun';
    case Tencent = 'tencent';
    case Baidu = 'baidu';
    case Qiniu = 'qiniu';
    case Huawei = 'huawei';
    case Ucloud = 'ucloud';
    case Rustfs = 'rustfs';

    public function label(): string
    {
        return match ($this) {
            self::Generic => __('storage_settings.providers.generic'),
            self::Aws => __('storage_settings.providers.aws'),
            self::R2 => __('storage_settings.providers.r2'),
            self::Aliyun => __('storage_settings.providers.aliyun'),
            self::Tencent => __('storage_settings.providers.tencent'),
            self::Baidu => __('storage_settings.providers.baidu'),
            self::Qiniu => __('storage_settings.providers.qiniu'),
            self::Huawei => __('storage_settings.providers.huawei'),
            self::Ucloud => __('storage_settings.providers.ucloud'),
            self::Rustfs => __('storage_settings.providers.rustfs'),
        };
    }

    public function getHelpLink(): string
    {
        return match ($this) {
            self::Generic => 'https://docs.aws.amazon.com/AmazonS3/latest/API/Welcome.html',
            self::Aws => 'https://docs.aws.amazon.com/general/latest/gr/s3.html',
            self::R2 => 'https://developers.cloudflare.com/r2/api/s3/api/',
            self::Aliyun => 'https://help.aliyun.com/zh/oss/user-guide/regions-and-endpoints',
            self::Tencent => 'https://cloud.tencent.com/document/product/436/6224',
            self::Baidu => 'https://cloud.baidu.com/doc/BOS/s/xjwvyq9l4',
            self::Qiniu => 'https://developer.qiniu.com/kodo/4088/s3-access-domainname',
            self::Huawei => 'https://console.huaweicloud.com/apiexplorer/#/endpoint/OBS',
            self::Ucloud => 'https://docs.ucloud.cn/ufile/s3/s3_introduction',
            self::Rustfs => 'https://docs.rustfs.com.cn/developer/sdk/javascript.html',
        };
    }

    /** @return list<array{id: string, name: string, endpoint: string, internal_endpoint?: string}> */
    public function getRegions(): array
    {
        return match ($this) {
            self::Generic => [
                [
                    'id' => 'us-east-1',
                    'name' => 'US East (N. Virginia)',
                    'endpoint' => 'https://s3.us-east-1.amazonaws.com',
                ],
            ],
            self::Aws => [
                [
                    'id' => 'us-east-1',
                    'name' => 'US East (N. Virginia)',
                    'endpoint' => 'https://s3.us-east-1.amazonaws.com',
                ],
                [
                    'id' => 'us-west-2',
                    'name' => 'US West (Oregon)',
                    'endpoint' => 'https://s3.us-west-2.amazonaws.com',
                ],
                [
                    'id' => 'eu-west-1',
                    'name' => 'Europe (Ireland)',
                    'endpoint' => 'https://s3.eu-west-1.amazonaws.com',
                ],
                [
                    'id' => 'ap-southeast-1',
                    'name' => 'Asia Pacific (Singapore)',
                    'endpoint' => 'https://s3.ap-southeast-1.amazonaws.com',
                ],
            ],
            self::R2 => [
                [
                    'id' => 'auto',
                    'name' => 'auto',
                    'endpoint' => 'https://ACCOUNT_ID.r2.cloudflarestorage.com',
                ],
            ],
            self::Aliyun => [
                [
                    'id' => 'cn-hangzhou',
                    'name' => '华东1（杭州）',
                    'endpoint' => 'https://oss-cn-hangzhou.aliyuncs.com',
                    'internal_endpoint' => 'https://oss-cn-hangzhou-internal.aliyuncs.com',
                ],
                [
                    'id' => 'cn-shanghai',
                    'name' => '华东2（上海）',
                    'endpoint' => 'https://oss-cn-shanghai.aliyuncs.com',
                    'internal_endpoint' => 'https://oss-cn-shanghai-internal.aliyuncs.com',
                ],
                [
                    'id' => 'cn-shenzhen',
                    'name' => '华南1（深圳）',
                    'endpoint' => 'https://oss-cn-shenzhen.aliyuncs.com',
                    'internal_endpoint' => 'https://oss-cn-shenzhen-internal.aliyuncs.com',
                ],
                [
                    'id' => 'ap-southeast-1',
                    'name' => '新加坡',
                    'endpoint' => 'https://oss-ap-southeast-1.aliyuncs.com',
                    'internal_endpoint' => 'https://oss-ap-southeast-1-internal.aliyuncs.com',
                ],
            ],
            self::Tencent => [
                [
                    'id' => 'ap-beijing',
                    'name' => '北京',
                    'endpoint' => 'https://cos.ap-beijing.myqcloud.com',
                ],
                [
                    'id' => 'ap-shanghai',
                    'name' => '上海',
                    'endpoint' => 'https://cos.ap-shanghai.myqcloud.com',
                ],
                [
                    'id' => 'ap-guangzhou',
                    'name' => '广州',
                    'endpoint' => 'https://cos.ap-guangzhou.myqcloud.com',
                ],
                [
                    'id' => 'ap-singapore',
                    'name' => '新加坡',
                    'endpoint' => 'https://cos.ap-singapore.myqcloud.com',
                ],
            ],
            self::Baidu => [
                [
                    'id' => 's3.bj',
                    'name' => '北京',
                    'endpoint' => 'https://s3.bj.bcebos.com',
                ],
                [
                    'id' => 's3.gz',
                    'name' => '广州',
                    'endpoint' => 'https://s3.gz.bcebos.com',
                ],
                [
                    'id' => 's3.hkg',
                    'name' => '香港',
                    'endpoint' => 'https://s3.hkg.bcebos.com',
                ],
            ],
            self::Qiniu => [
                [
                    'id' => 'cn-east-1',
                    'name' => '华东-浙江',
                    'endpoint' => 'https://s3.cn-east-1.qiniucs.com',
                ],
                [
                    'id' => 'cn-north-1',
                    'name' => '华北-河北',
                    'endpoint' => 'https://s3.cn-north-1.qiniucs.com',
                ],
                [
                    'id' => 'cn-south-1',
                    'name' => '华南-广东',
                    'endpoint' => 'https://s3.cn-south-1.qiniucs.com',
                ],
            ],
            self::Huawei => [
                [
                    'id' => 'cn-north-4',
                    'name' => '华北-北京四',
                    'endpoint' => 'https://obs.cn-north-4.myhuaweicloud.com',
                ],
                [
                    'id' => 'cn-east-2',
                    'name' => '华东-上海二',
                    'endpoint' => 'https://obs.cn-east-2.myhuaweicloud.com',
                ],
            ],
            self::Ucloud => [
                [
                    'id' => 'cn-bj',
                    'name' => '北京',
                    'endpoint' => 'https://s3-cn-bj.ufileos.com',
                ],
                [
                    'id' => 'cn-sh',
                    'name' => '上海',
                    'endpoint' => 'https://s3-cn-sh.ufileos.com',
                ],
            ],
            self::Rustfs => [
                [
                    'id' => 'us-east-1',
                    'name' => '自定义区域',
                    'endpoint' => 'http://127.0.0.1:9000',
                ],
            ],
        };
    }
}
