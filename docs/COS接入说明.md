# 腾讯云 COS 接入说明

## 1. 现状

真实密钥已经配置并**实测验证通过**（上传、读取、生成签名下载 URL、删除，全流程跑通），本地开发默认仍然用 `local` 磁盘，`.env` 把 `FILESYSTEM_DISK` 改成 `cos` 才会真正切到腾讯云。

## 2. 用到的 `.env` 变量

| 变量 | 说明 |
|---|---|
| `COS_SECRET_ID` / `COS_SECRET_KEY` | 腾讯云 API 密钥，**只放在 `.env` 里，不要提交到仓库**（`.env` 已在 `.gitignore` 里） |
| `COS_BUCKET` | 存储桶名称（不带 APPID 后缀），如 `honsen-drawing` |
| `COS_APP_ID` | 腾讯云账号 APPID，从存储桶域名里能看到，如 `honsen-drawing-1308426049` 里的 `1308426049` |
| `COS_REGION` | 存储桶所在地域，配了 `COS_DOMAIN` 之后这个值只是兜底，不参与实际拼接 |
| `COS_DOMAIN` | 可选，指定后 SDK 直接用这个域名发请求，不再按 `<bucket>-<appid>.cos.<region>.myqcloud.com` 模板拼接。**本项目配置的是全球加速域名**（`*.cos.accelerate.myqcloud.com`），弘盛非洲这边访问国内的桶走全球加速比走固定地域域名更快 |
| `COS_SIGN_URL_TTL` | 签名下载 URL 的有效期（秒） |

对应代码：`config/filesystems.php` 的 `cos` 磁盘、`app/Providers/AppServiceProvider.php` 里注册的 `Storage::extend('cos', ...)`。

## 3. 全球加速域名是怎么接进去的

`overtrue/qcloud-cos-client` 的 `Client::configureDomain()` 会优先读取配置里的 `domain` 键，如果给了就直接拿来当请求域名，完全跳过 `<bucket>-<appid>.cos.<region>.myqcloud.com` 的默认拼接逻辑。所以只要在 `.env` 填 `COS_DOMAIN=你的加速域名`，`region` 就不再影响实际请求（仍然建议填一个真实值，作为没配 `COS_DOMAIN` 时的兜底）。

## 4. 本地跑通踩的一个坑：Windows PHP 没有根证书

第一次跑真实上传时报错：

```
cURL error 60: SSL certificate OpenSSL verify result: self-signed certificate in certificate chain
```

原因是 winget 装的 PHP 8.4（`php.net` 官方 Windows 构建）没有自带根证书列表，`php.ini` 里 `curl.cainfo` / `openssl.cafile` 默认是空的。解决方式：

1. 下载 curl 项目维护的证书包：`https://curl.se/ca/cacert.pem`，放到 PHP 安装目录下
2. `php.ini` 里配置：
   ```ini
   curl.cainfo = "C:/path/to/php/cacert.pem"
   openssl.cafile="C:/path/to/php/cacert.pem"
   ```
3. 重新跑一遍即可

这是 Windows 本地开发机特有的坑，Ubuntu 生产服务器一般会通过系统的 `ca-certificates` 包正常提供根证书，通常不会遇到这个问题；如果部署后也报类似错误，先确认服务器执行过 `sudo apt install ca-certificates`。

## 5. 怎么验证

写一个一次性脚本（用完删掉，不要留在仓库里），依次调用 `Storage::disk('cos')` 的 `put()` / `exists()` / `get()` / `temporaryUrl()` / `delete()`，确认全部符合预期即可。不要把真实密钥写进任何会被提交的文件。

## 6. 前端直传（STS）仍未开启

`COS_STS_ROLE_ARN` 还是占位值，`CosFileService::issueUploadCredentials()` 会返回"未配置"提示。当前所有上传都走后端中转（`CosFileService::store()`），STS 直传是后续可选的性能优化，不是必须项。
