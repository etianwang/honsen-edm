# DWG 图纸预览接入说明

对应用户提出的问题："DWG 图纸预览、Word/Excel 预览能否接入 iframe API 或者需要本地拉起 OnlyOffice"。结论：Word/Excel 建议后续接腾讯云数据万象（CI）文档预览（本期未做，见文末）；DWG 采用**服务端转 DXF + 浏览器端 WebGL 交互式看图**的方案（方案 B，已落地）。

## 1. 为什么不用 OnlyOffice / Autodesk Forge / Google 文档预览

- **OnlyOffice / Collabora**：本质是 Office 文档编辑器，根本不支持 DWG 格式，解决不了 DWG 预览问题；用来预览 Word/Excel 又比腾讯云 CI 重得多（需要单独部署 Docker 容器、配置 JWT、常驻内存）。
- **Autodesk Platform Services (Forge)**：官方级 DWG 云看图，效果最好，但图纸要上传到 Autodesk 的美国云端转换，涉及工程图纸数据出境问题，且按转换次数持续计费。
- **Google/Microsoft 在线文档预览**：需要文档 URL 公网可访问，这会破坏本系统"COS 桶不公开、只用签名 URL"的安全设计，图纸也会经过第三方服务器。

## 2. 落地方案：ODA File Converter + dxf-viewer

```
上传 DWG
  → 后端调用 ODA File Converter（免费命令行工具）转成 DXF
  → DXF 存进当前文件磁盘（本地 / 腾讯云 COS，跟 DWG 原文件同一套存储）
  → 预览时浏览器通过 /versions/{version}/files/{language}/dxf 拿到 DXF 文本
  → 前端用开源的 dxf-viewer（WebGL/three.js）在浏览器里交互式渲染（缩放、平移）
```

**全程不依赖任何云端 CAD 服务，转换和渲染都在自己的服务器 / 浏览器里完成。**

### 2.1 ODA File Converter（转换器）

- 免费工具，来自 Open Design Alliance，支持 Windows / Linux / macOS，命令行批量转换 DWG↔DXF
- 下载页面：[opendesign.com/guestfiles/oda_file_converter](https://www.opendesign.com/guestfiles/oda_file_converter)，可能需要注册一个免费会员账号（这一步需要人工完成，Claude 不会代替注册第三方账号）
- 装好后把可执行文件的绝对路径填进 `.env` 的 `ODA_CONVERTER_PATH`
- **没配置这个变量、或路径不存在时，系统会自动跳过转换**，该版本只能下载 DWG 原文件、不显示交互式预览按钮触发的图纸，不会报错（见 `App\Services\DwgConverter::isAvailable()`）
- 生产服务器和本地开发机是两台不同的机器，**需要分别安装**，`.env` 里的路径也各自配置各自的

#### Windows（本地开发）

直接下载 `.msi` 安装包运行即可，安装完成后路径一般是：
```
ODA_CONVERTER_PATH=C:\Program Files\ODA\ODAFileConverter\ODAFileConverter.exe
ODA_USE_XVFB=false
```

#### Ubuntu（生产部署，本项目实际的部署目标）

ODA File Converter 本质是个 Qt 图形界面程序，即使走命令行参数调用，在没有显示器的服务器上也需要 `xvfb`（虚拟显示器）才能跑起来，不然会报错或直接卡死不退出。

```bash
# 1. 装虚拟显示器依赖
sudo apt update
sudo apt install -y xvfb gdebi-core

# 2. 下载 deb 包（也可以在 Windows 上下载好用 scp 传过去）
wget "https://www.opendesign.com/guestfiles/get?filename=ODAFileConverter_QT6_lnxX64_8.3dll_27.1.deb" -O ODAFileConverter.deb

# 3. 安装
sudo gdebi ODAFileConverter.deb
# 如果 gdebi 提示缺依赖，用这个兜底：
# sudo dpkg -i ODAFileConverter.deb && sudo apt --fix-broken install -y

# 4. Ubuntu 22/24 等较新版本可能还缺一个符号链接，报 libxcb-util.so.0 找不到的话执行：
cd /usr/lib/x86_64-linux-gnu
sudo ln -s libxcb-util.so.1 libxcb-util.so.0

# 5. 确认装到哪了（一般是 /usr/bin/ODAFileConverter 或 /opt/ODAFileConverter/ODAFileConverter）
which ODAFileConverter || dpkg -L oda-file-converter | grep bin
```

`.env` 里配置：
```
ODA_CONVERTER_PATH=/usr/bin/ODAFileConverter
ODA_USE_XVFB=true
```

`ODA_USE_XVFB=true` 时，`App\Services\DwgConverter` 会自动把实际执行的命令包一层 `xvfb-run -a`，相当于手动执行：
```bash
xvfb-run -a ODAFileConverter <输入目录> <输出目录> ACAD2013 DXF 0 1
```
可以先手动跑一遍这行命令，确认能正常生成 DXF、没有报错弹窗卡住，再配置到 `.env` 里让应用调用。如果 `ODA_USE_XVFB=true` 但服务器上没装 `xvfb-run`，`isAvailable()` 会直接返回 false 并记一条日志，不会尝试瞎跑。

### 2.2 转换触发时机

`App\Services\DwgConverter::convertAndStore()` 在以下两处被调用，转换是同步执行的（内部工具、文件不大，没有引入队列 worker 的必要）：

- `VersionController@store`：上传新版本时，对每个语言（zh/fr/en）里带 DWG 的都转一次
- `VersionFileController@store`：补充或替换某个语言的 DWG 文件时

转换失败（超时、文件损坏、ODA 报错）都会被 `report()` 记录到日志，`version_files.dxf_path` 保持 `null`，不会影响 DWG 原文件的正常上传和下载。

### 2.3 前端看图组件（dxf-viewer）

- 开源项目：[vagran/dxf-viewer](https://github.com/vagran/dxf-viewer)，MIT 协议，WebGL 渲染，性能较好
- **通过 jsDelivr 的 `+esm` CDN 直接以 ES Module 方式引入**（`https://cdn.jsdelivr.net/npm/dxf-viewer/+esm`），jsDelivr 会自动打包并解析它的依赖（three.js、opentype.js 等），**不需要在项目里引入 npm/Vite 构建流程**，和 Alpine.js、Google Fonts 现有的引入方式保持一致的"轻量、无构建步骤"风格
- 代码在 `resources/views/project/partials/preview-modal.blade.php`，`@once` 包裹的 `<script type="module">` 只会输出一次
- 每个语言的 DXF 只在用户真正点开预览、切到对应标签页时才懒加载（避免一次性初始化很多 WebGL 场景）

### 2.4 优雅降级

`VersionFile::hasInteractivePreview()` 返回 `dxf_path` 是否存在。预览弹窗里：
- 有 DXF → 显示可缩放平移的交互画布
- 没有 DXF（转换器没装、转换失败、或者本来就是历史遗留数据）→ 显示"该语言版本暂无可交互预览，可直接下载查看"，仍然可以下载原始 DWG

## 3. Word / Excel / PDF 预览（后续，本期未做）

推荐接腾讯云数据万象（CI）的文档预览能力：COS 对象 URL 后面加 `?ci-process=doc-preview` 相关参数即可返回预览图/HTML，零部署、按量计费，且和现有腾讯云 COS 选型一致。CI 文档预览目前**不支持 DWG**，所以两类文件的预览方案是分开的，不能用同一套。

## 4. 验证方式

因为 ODA File Converter 需要人工注册下载，本地开发环境暂时没有真实装上。验证分两部分：
1. **前端看图组件本身**：`database/seeders/DatabaseSeeder.php` 给"排水"细分类的 V2 版本中文文件塞了一份手写的最小合法 DXF（矩形轮廓 + 圆 + 一条线），装好后登录用陈工账号在该细分类点"预览"就能看到真实渲染出的图形（已用浏览器实测确认 canvas 正确渲染、WebGL 像素非空）
2. **转换流程**：等你装好 ODA File Converter、填好 `.env` 里的路径后，正常上传一次带 DWG 的新变更，检查该版本文件是否自动生成了 `dxf_path`（可以直接查 `version_files` 表，或者看预览按钮是否从"暂无预览"变成真正的看图界面）
