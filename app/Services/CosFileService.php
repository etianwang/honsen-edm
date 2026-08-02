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

    /**
     * 上传时就把 Content-Disposition（含原始文件名）写进对象元数据，下载/预览时才能
     * 显示正确的文件名，而不是存储用的 UUID 文件名。腾讯云 COS 的预签名 URL 不支持
     * 临时覆盖响应头（overtrue/qcloud-cos-client 的 getObjectSignedUrl 只签 sign 参数，
     * 传给 temporaryUrl() 的 ResponseContentDisposition 选项会被静默忽略），所以只能
     * 在上传时把 disposition 写进对象本身的元数据，之后每次访问都固定生效。
     * $inline=true 用于 PDF 图纸想在浏览器里直接打开查看的场景。
     */
    public function store(UploadedFile $file, string $directory, bool $inline = false): array
    {
        $originalName = $file->getClientOriginalName();
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $filename, [
            'disk' => $this->disk,
            'headers' => ['Content-Disposition' => $this->contentDispositionHeader($inline ? 'inline' : 'attachment', $originalName)],
        ]);

        return [
            'path' => $path,
            'size' => $file->getSize(),
            'original_name' => $originalName,
        ];
    }

    /**
     * 存储路径用的是 UUID 文件名（避免中文/特殊字符路径问题、避免重名覆盖），
     * 下载时要让浏览器看到原始文件名，只能靠 Content-Disposition 里的 filename。
     * 同时给 ASCII 兜底（filename=）和 UTF-8 编码（filename*=，RFC 5987），
     * 兼容不同浏览器对中文文件名的处理。
     */
    public function contentDispositionHeader(string $type, string $originalName): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $originalName);
        $ascii = str_replace('"', "'", $ascii);

        return sprintf('%s; filename="%s"; filename*=UTF-8\'\'%s', $type, $ascii, rawurlencode($originalName));
    }

    /**
     * 一次性维护/补救用：把已上传对象的 Content-Disposition 元数据重写为正确的原始文件名。
     * 只对 COS 生效（本地磁盘的临时链接走 Laravel 自己的签名机制，不存在这个问题）。
     * COS 预签名 URL 不支持临时覆盖响应头，只能用 CopyObject 重写对象元数据本身。
     */
    public function refreshContentDisposition(string $path, string $originalName, bool $inline = false): bool
    {
        if ($this->disk !== 'cos') {
            return true;
        }

        $diskConfig = config('filesystems.disks.cos');
        $source = sprintf(
            '%s-%s.cos.%s.myqcloud.com/%s',
            $diskConfig['bucket'],
            $diskConfig['app_id'],
            $diskConfig['region'],
            ltrim($path, '/')
        );

        $client = Storage::disk('cos')->getAdapter()->getObjectClient();
        $mimeType = Storage::disk('cos')->mimeType($path) ?: 'application/octet-stream';

        $response = $client->copyObject($path, [
            'x-cos-copy-source' => $source,
            'x-cos-metadata-directive' => 'Replaced',
            'Content-Type' => $mimeType,
            'Content-Disposition' => $this->contentDispositionHeader($inline ? 'inline' : 'attachment', $originalName),
        ]);

        return $response->isSuccessful();
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
     *
     * 下载文件名不是靠这个 URL 决定的：COS 预签名 URL 不支持临时覆盖 Content-Disposition，
     * 真正生效的是上传时写进对象元数据里的文件名（见 store() 的说明）。
     */
    public function signedDownloadUrl(string $path): string
    {
        // 本地磁盘和 cos 磁盘目前都是同一套 temporaryUrl 调用；本地磁盘用 Laravel
        // 内置的签名临时链接，避免把 storage 目录设成公开可读
        return Storage::disk($this->disk)->temporaryUrl(
            $path,
            now()->addSeconds((int) config('services.cos.sign_url_ttl', 600))
        );
    }

    /**
     * 带有效期的签名"预览" URL：用于 PDF 在浏览器里直接打开查看（inline，不触发下载）。
     * 是否真的 inline 取决于上传时 store() 有没有传 $inline=true 把 Content-Disposition
     * 写进了对象元数据——这里同样不能临时覆盖，见 store() 的说明。
     */
    public function signedViewUrl(string $path): string
    {
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
