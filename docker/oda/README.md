# ODA File Converter（Docker 构建用）

`docker build` 时会自动 COPY 并安装这个目录下的 `.deb` 包（`Dockerfile` 里的 `WITH_ODA` 逻辑）。

由于 ODA File Converter 需要在官网人工注册账号才能下载，无法在 Dockerfile 里自动 `wget`，请按以下步骤操作：

1. 打开 [opendesign.com/guestfiles/oda_file_converter](https://www.opendesign.com/guestfiles/oda_file_converter)，注册/登录后下载 **Linux x64** 版本的 `.deb` 包（例如 `ODAFileConverter_QT6_lnxX64_8.3dll_27.1.deb`）
2. 把下载好的 `.deb` 文件放到这个目录（`docker/oda/`）下
3. 正常 `docker build` / `docker compose build`，Dockerfile 会自动检测并安装它，同时装好 `xvfb` 及所需的 Qt/xcb 依赖库

如果这个目录下没有 `.deb` 文件，镜像仍然能正常构建，只是 DWG 交互式预览功能会被跳过（`isAvailable()` 返回 false，用户只能下载原始 DWG，不影响其他功能）。

`.deb` 文件本身受 ODA 的下载许可协议约束，不要提交进 git 仓库（已在 `.gitignore` 里排除）。
