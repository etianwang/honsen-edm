<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 文件存储封装：本地开发默认走 local 磁盘，生产环境把 FILESYSTEM_DISK 切到 cos 即可切换到腾讯云 COS，
 * 上层代码（Controller）不需要关心具体存的是哪个磁盘。
 *
 * 安全要点（见 docs/COS接入说明.md）：
 * - 前端不持有 COS 密钥，所有上传都经过本服务中转，或者由 issueUploadCredentials() 签发的临时 STS 凭证完成直传
 * - 下载走后端中转的流式下载（streamDownload()），不是 redirect 到 COS 签名 URL，
 *   这样才能在响应头上带上正确的原始文件名，COS 桶也不用设公开读
 */
class CosFileService
{
    protected string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default');
    }

    /**
     * $inline=true 时会在 COS 对象上设置 Content-Disposition: inline（比如 PDF 图纸）。
     * 只能传这种不含分号/引号/空格的"裸词"值——overtrue/qcloud-cos-client 的签名算法
     * （Signature::getHeadersToBeSigned 用 http_build_query 编码待签名的请求头）在值里有
     * 分号、双引号、单引号、空格时，客户端算出来的签名和 COS 服务端重新计算的对不上，
     * PUT/CopyObject 会直接 403 SignatureDoesNotMatch（实测踩过，见下载文件名那次事故）。
     * 所以真正带原始文件名的 Content-Disposition 不能通过 COS 对象元数据来设置，
     * 下载改成后端中转流式下载，在 streamDownload() 里由 Laravel 自己的响应头设置，
     * 不经过 COS 签名，没有这个限制。
     */
    public function store(UploadedFile $file, string $directory, bool $inline = false): array
    {
        $originalName = $file->getClientOriginalName();
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $options = ['disk' => $this->disk];
        if ($inline) {
            $options['headers'] = ['Content-Disposition' => 'inline'];
        }
        $path = $file->storeAs($directory, $filename, $options);

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
     * 兼容不同浏览器对中文文件名的处理。这个值只用在我们自己的 HTTP 响应上
     * （streamDownload()），不会经过 COS 签名，没有 store() 注释里说的那个限制。
     */
    public function contentDispositionHeader(string $type, string $originalName): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $originalName);
        $ascii = str_replace('"', "'", $ascii);

        return sprintf('%s; filename="%s"; filename*=UTF-8\'\'%s', $type, $ascii, rawurlencode($originalName));
    }

    /**
     * 下载改走后端中转（而不是 redirect 到 COS 签名 URL），这样可以在我们自己的响应上
     * 设置任意 Content-Disposition（含原始文件名），完全不涉及 COS 的请求签名，
     * 规避 store() 注释里说的那个 SDK 签名限制。
     */
    public function streamDownload(string $path, string $originalName): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $disk = Storage::disk($this->disk);
        $stream = $disk->readStream($path);
        $size = $disk->size($path);
        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => $size,
            'Content-Disposition' => $this->contentDispositionHeader('attachment', $originalName),
        ]);
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

    /**
     * 尽力删除，不让调用方（比如批量清理一个版本名下所有文件的 purgeFiles()）因为
     * 某一个文件删除失败就整个中断。COS 失败时腾讯云 SDK 抛的是它自己的异常类
     * （Overtrue\CosClient\Exceptions\*），跟 Laravel FilesystemAdapter::delete() 内部
     * 期待捕获的 League\Flysystem\UnableToDeleteFile 对不上，会直接冒泡，如果调用方
     * 是在循环里删多个文件、后面还要做数据库清理，一次网络抖动就会导致状态卡在
     * "COS 删了一半、数据库记录一个没删"——这里兜底捕获，记日志留痕，不往外抛，
     * 保证数据库清理一定能继续做下去；真正删除失败的对象需要事后翻日志人工核实。
     */
    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        try {
            Storage::disk($this->disk)->delete($path);
        } catch (\Throwable $e) {
            Log::warning('COS 文件删除失败，需要人工核实是否有孤儿文件残留', [
                'disk' => $this->disk,
                'path' => $path,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
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
