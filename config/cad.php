<?php

return [
    // ODA File Converter 可执行文件的绝对路径，例如：
    // Windows: C:\Program Files\ODA\ODAFileConverter\ODAFileConverter.exe
    // Linux:   /usr/bin/ODAFileConverter
    // 未配置或文件不存在时，自动跳过 DWG→DXF 转换，交互式看图功能优雅降级为仅下载
    'oda_converter_path' => env('ODA_CONVERTER_PATH'),

    // 输出 DXF 的 CAD 版本，需与 dxf-viewer 底层 dxf-parser 兼容，ASCII DXF
    'oda_output_version' => env('ODA_OUTPUT_VERSION', 'ACAD2013'),

    // 单次转换超时时间（秒）
    'oda_timeout' => env('ODA_TIMEOUT', 60),

    // ODA File Converter 本质是 Qt 图形界面程序，Linux 无显示器的服务器上需要 xvfb 起虚拟显示器
    // （sudo apt install xvfb）才能跑，命令行前会自动加上 xvfb-run -a；Windows 本地开发不要开
    'oda_use_xvfb' => env('ODA_USE_XVFB', false),
];
