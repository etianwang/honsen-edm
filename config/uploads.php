<?php

// 这里改了上限，PHP 层面的 upload_max_filesize / post_max_size（php.ini，宝塔面板在
// "网站设置 → PHP 配置" 里可以直接改）也要同步调大，否则大文件在到达 Laravel 验证之前
// 就已经被 PHP 直接截断，报的错误信息还会比较让人摸不着头脑。post_max_size 建议比
// dwg_max_kb 再大一点（留出多语言字段和其他表单字段的空间）。

return [
    // 单位：KB，对应 Laravel 验证规则里的 max
    'dwg_max_kb' => env('UPLOAD_DWG_MAX_KB', 200 * 1024),
    'doc_max_kb' => env('UPLOAD_DOC_MAX_KB', 20 * 1024),

    'dwg_extensions' => ['dwg', 'dxf'],
    'doc_extensions' => ['doc', 'docx', 'xls', 'xlsx', 'pdf'],
];
