<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 文件存储封装：本地开发默认走 local 磁盘，生产环境把 FILESYSTEM_DISK 切到 cos 即可切换到腾讯云 COS，
 * 上层代码（Controller）不需要关心具体存的是哪个磁盘。
 *
 * 安全要点（见 docs/COS接入说明.md）：
 * - 前端不持有 COS 密钥，所有上传都经过本服务中转，或者由 issueUploadCredentials() 签发的临时 STS 凭证完成直传
 * - 下载一律走带有效期的签名 URL，COS 桶不设公开读
 */
class CosFileService
{
    protected string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default');
    }

    public function store(UploadedFile $file, string $directory): array
    {
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $filename, $this->disk);

        return [
            'path' => $path,
            'size' => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * 把服务器本地磁盘上已经存在的文件（比如 DWG→DXF 转换出来的临时文件）存进当前磁盘
     */
    public function storeFromPath(string $absolutePath, string $directory, string $filename): array
    {
        $path = trim($directory, '/').'/'.$filename;
        Storage::disk($this->disk)->put($path, file_get_contents($absolutePath));

        return [
            'path' => $path,
            'size' => filesize($absolutePath),
        ];
    }

    public function getContents(string $path): string
    {
        return Storage::disk($this->disk)->get($path);
    }

    public function replace(?string $oldPath, UploadedFile $file, string $directory): array
    {
        $result = $this->store($file, $directory);

        if ($oldPath) {
            Storage::disk($this->disk)->delete($oldPath);
        }

        return $result;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk($this->disk)->delete($path);
        }
    }

    /**
     * 带有效期的签名下载 URL。本地磁盘没有真正的签名机制，退化为走后端中转的下载路由。
     */
    public function signedDownloadUrl(string $path, string $downloadName): string
    {
        if ($this->disk === 'cos') {
            return Storage::disk('cos')->temporaryUrl(
                $path,
                now()->addSeconds((int) config('services.cos.sign_url_ttl', 600)),
                ['ResponseContentDisposition' => 'attachment; filename="'.$downloadName.'"']
            );
        }

        // 本地磁盘用 Laravel 内置的签名临时链接，避免把 storage 目录设成公开可读
        return Storage::disk($this->disk)->temporaryUrl(
            $path,
            now()->addSeconds((int) config('services.cos.sign_url_ttl', 600))
        );
    }

    /**
     * 预留：前端直传所需的腾讯云 STS 临时密钥签发。
     * 需要正式的 COS_SECRET_ID / COS_SECRET_KEY / COS_STS_ROLE_ARN 才能真正调用腾讯云 STS 接口，
     * 目前 .env 中是占位值，先返回未配置提示，等拿到真实密钥后接入 qcloud/cos-sdk-v5 的 Sts 类即可。
     */
    public function issueUploadCredentials(): array
    {
        if (config('services.cos.secret_id') === 'REPLACE_ME_SECRET_ID') {
            return [
                'ready' => false,
                'message' => 'COS 密钥尚未配置，当前所有上传通过后端中转完成',
            ];
        }

        // TODO: 接入真实密钥后，调用 \Qcloud\Cos\Sts 签发临时密钥并返回给前端直传使用
        return ['ready' => false, 'message' => '前端直传尚未启用'];
    }
}
