<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 之前修的三个法语翻译缺口（团队/专业页副标题、专业页空状态、主布局六处），本质都是
 * 同一类问题：某个 __('中文') 调用的 key，在 resources/lang/fr.json 里没有对应词条，
 * 静默 fallback 成中文，不会报错、也不会在页面上"看起来像坏了"，很容易漏掉。
 * 这个测试直接扫描所有主要内容页的 blade 文件，把每一个 __('...') 调用的 key 和
 * fr.json 的 key 做全量比对，锁定"以后不会再悄悄漏翻译"。
 *
 * 后台管理页面（resources/views/admin/**）是有意不做国际化的（全中文），排除在外。
 */
class FrenchTranslationCoverageTest extends TestCase
{
    public function test_every_static_translation_key_used_in_main_content_views_exists_in_the_french_dictionary(): void
    {
        $viewsPath = resource_path('views');
        $frDictionary = json_decode(file_get_contents(resource_path('lang/fr.json')), true, flags: JSON_THROW_ON_ERROR);

        $missing = [];

        $files = collect(glob("{$viewsPath}/**/*.blade.php"))
            ->merge(glob("{$viewsPath}/*.blade.php"))
            ->filter(fn ($path) => ! str_contains(str_replace('\\', '/', $path), '/views/admin/'));

        // Blade 视图目录是嵌套的，glob 的 ** 在部分平台/PHP 版本不递归，这里手动递归一遍兜底
        $files = $files->merge($this->allBladeFilesRecursively($viewsPath))
            ->unique()
            ->filter(fn ($path) => ! str_contains(str_replace('\\', '/', $path), '/views/admin/'));

        foreach ($files as $file) {
            $content = file_get_contents($file);

            if (! preg_match_all('/__\(\s*([\'"])((?:(?!\1).)*)\1/u', $content, $matches)) {
                continue;
            }

            foreach ($matches[2] as $key) {
                // 只关心含中文字符的 key（纯英文/变量插值之外的静态文案）
                if (! preg_match('/\p{Han}/u', $key)) {
                    continue;
                }

                if (! array_key_exists($key, $frDictionary)) {
                    $missing["{$key}"][] = str_replace($viewsPath.'/', '', $file);
                }
            }
        }

        $report = collect($missing)
            ->map(fn ($files, $key) => "\"{$key}\" (in: ".implode(', ', array_unique($files)).')')
            ->implode("\n");

        $this->assertEmpty($missing, "以下 key 在 fr.json 里没有对应翻译，法语用户会看到中文:\n{$report}");
    }

    private function allBladeFilesRecursively(string $dir): array
    {
        $result = [];
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = "{$dir}/{$entry}";
            if (is_dir($path)) {
                $result = array_merge($result, $this->allBladeFilesRecursively($path));
            } elseif (str_ends_with($entry, '.blade.php')) {
                $result[] = $path;
            }
        }

        return $result;
    }
}
