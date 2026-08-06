# 图纸变更管理系统

内部工具 · Honsen Africa（弘盛机电）
用来替代微信群传图纸：按 国家 → 项目 → 团队/专业 → 细分类 → 版本 组织图纸变更，支持中/法/英三语言文件（DWG 原图 + PDF 图纸均可多份）、站内通知；图纸没有在线预览，全部走下载查看（原因见 [架构与实施方案.md](docs/架构与实施方案.md) 3.2 节）。

详细设计和功能说明见 [docs/](docs)：

- [架构与实施方案.md](docs/架构与实施方案.md) —— 权限模型、技术栈、待确认事项的最终结论
- [数据库设计.md](docs/数据库设计.md) —— 表结构
- [COS接入说明.md](docs/COS接入说明.md) —— 腾讯云 COS 文件存储配置
- [DWG看图接入说明.md](docs/DWG看图接入说明.md) —— **已停用**，历史记录：曾经的 DWG→DXF 转换 + 浏览器交互式看图方案，现已改为 PDF 预览
- [验收清单.md](docs/验收清单.md) —— 对照原型的手动验收清单

## 技术栈

| | |
|---|---|
| 后端 | PHP 8.4 + Laravel 13 |
| 数据库 | PostgreSQL |
| 文件存储 | 腾讯云 COS（本地开发可退化为本地磁盘） |
| 前端 | Blade + Alpine.js（无构建步骤），无图纸在线预览，全部走下载查看 |
| 认证 | 工号 / 手机号登录，四级角色（施工方/设计师/管理员/超级管理员） |

## 本地开发

```bash
composer install
cp .env.example .env
php artisan key:generate
# 建好 PostgreSQL 数据库后，把连接信息填进 .env，然后：
php artisan migrate --seed   # --seed 会灌入演示数据和演示账号，仅用于本地开发
php artisan serve
```

跑测试：

```bash
php artisan test   # 用内存 SQLite，不需要额外配置
```

演示账号（密码统一 `honsen2026`，**生产环境不要用这份种子数据**）：

| 登录标识 | 角色 |
|---|---|
| S00001 | 超级管理员 |
| A00001 | 管理员 |
| D00001 | 设计师 |
| C00001 | 施工方 |

---

## 生产环境部署（Docker，推荐）

裸机在 Ubuntu 上装 PHP/Composer/扩展步骤繁琐、容易环境不一致，项目提供了 `Dockerfile` + `docker-compose.yml` 把应用本身（PHP-FPM + Nginx）打包成镜像。**PostgreSQL 仍然沿用宿主机上已经装好的（比如宝塔管理的那个），不放进容器**，架构更简单、也不用额外操心数据卷备份数据库。

### 1. 服务器上装 Docker

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER   # 加完组需要重新登录一次 shell 才生效
```

### 2. 配置 `.env`

```bash
cp .env.example .env
php artisan key:generate --show   # 在本地或任意装了 PHP 的机器上生成一把 key，粘贴进 .env 的 APP_KEY=
```

和裸机部署（见下一节第 6 步）配置基本一致，但两处 Docker 特有的地方：

```ini
# 容器要连宿主机上的 PostgreSQL，用这个特殊域名（docker-compose.yml 里已经配置了对应的 extra_hosts）
DB_HOST=host.docker.internal

# 确认宿主机 PostgreSQL 监听的不只是 127.0.0.1，否则容器连不进来，
# 比如 postgresql.conf 里 listen_addresses = '*'，并在 pg_hba.conf 里放行 Docker 的网段（一般是 172.16.0.0/12）
```

### 3. 构建 + 启动

```bash
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan app:create-admin
```

`app` 容器监听宿主机 `8080` 端口（`docker-compose.yml` 里可改）。

### 4. 宝塔面板：反向代理 + HTTPS

不需要给这个站点选 PHP 版本或伪静态规则了（都在容器里），只需要：网站 → 添加站点 → **反向代理**，目标 URL 填 `http://127.0.0.1:8080`；SSL 证书照常在宝塔面板申请、开启强制 HTTPS。

### 5. 后续更新代码怎么发布

```bash
cd /www/wwwroot/honsen-drawing
git pull
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --force
```

### 6. 上线前确认

和下面裸机部署第 11 步的检查表一样（`.env` 三项、没跑过 `db:seed`、HTTPS、COS 配置、`storage/` 权限），但 `storage/` 权限已经在镜像构建时处理好，不需要手动 `chown`。

---

## 生产环境部署（Ubuntu + 宝塔面板，裸机方式）

以下步骤假设：Ubuntu 22.04/24.04，已经装好宝塔面板（[bt.cn](https://www.bt.cn)），PHP 通过宝塔的多版本 PHP 管理器安装，PostgreSQL 走系统 `apt`（宝塔社区版对 PostgreSQL 的支持不如 MySQL 完善，直接用官方仓库更省心，宝塔面板照样能管理这台服务器上的站点/证书/防火墙）。

### 1. 系统依赖

```bash
sudo apt update
sudo apt install -y postgresql postgresql-contrib git unzip
```

- `postgresql`：数据库

### 2. PHP（宝塔面板操作）

宝塔面板 → **软件商店** → 搜索 "PHP" → 安装 **PHP 8.4**（或面板当前提供的最新 8.x 版本，本项目要求 `^8.3`）。

装好后进入该 PHP 版本的 **设置 → 安装扩展**，勾选安装：

```
openssl, curl, mbstring, pdo_pgsql, pgsql, gd, intl, zip, fileinfo, bcmath
```

同一页面的 **配置修改** 里，把这几项调大（要覆盖 DWG 图纸的体积，`config/uploads.php` 里 DWG 上限是 200MB，doc 上限 20MB，下面两个值要比这个更大）：

```ini
upload_max_filesize = 220M
post_max_size = 240M
max_execution_time = 300
memory_limit = 512M
```

### 3. Composer

宝塔面板 → **软件商店** → 搜索 "Composer" 一键安装；或者手动：

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 4. PostgreSQL：建库建角色

```bash
sudo -u postgres psql <<'SQL'
CREATE ROLE honsen_app LOGIN PASSWORD '换成一个足够复杂的密码';
CREATE DATABASE honsen_drawing OWNER honsen_app;
GRANT ALL PRIVILEGES ON DATABASE honsen_drawing TO honsen_app;
SQL
```

默认只监听 `127.0.0.1`（`postgresql.conf` 里 `listen_addresses = 'localhost'`），不需要额外开放端口，PHP-FPM 和数据库在同一台机器上就够用。

### 5. 拿到代码 + 装依赖

宝塔面板 → **网站** → 添加站点（先随便建一个，后面改文档根目录），或者直接在服务器上用 git：

```bash
cd /www/wwwroot
sudo git clone <你的代码仓库地址> honsen-drawing
cd honsen-drawing
composer install --no-dev --optimize-autoloader
```

### 6. 配置 `.env`

```bash
cp .env.example .env
php artisan key:generate
```

编辑 `.env`，重点确认这几项（生产环境和本地开发不一样的地方）：

```ini
APP_NAME="深圳弘盛图纸管理系统"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://你的域名

APP_LOCALE=zh_CN

LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=honsen_drawing
DB_USERNAME=honsen_app
DB_PASSWORD=第 4 步设置的密码

SESSION_SECURE_COOKIE=true

FILESYSTEM_DISK=cos
COS_SECRET_ID=真实密钥
COS_SECRET_KEY=真实密钥
COS_BUCKET=真实桶名
COS_APP_ID=真实APPID
COS_DOMAIN=真实域名（比如全球加速域名，配置方式见 docs/COS接入说明.md）
```

`APP_DEBUG=false` 这一条尤其重要——开着的话报错会把完整堆栈和部分环境变量直接展示在页面上。`SESSION_SECURE_COOKIE=true` 要求站点必须已经是 HTTPS（见第 9 步），不然登录会一直失败（Cookie 发不出去）。

### 7. 初始化数据库和第一个账号

```bash
php artisan migrate --force
php artisan app:create-admin
```

`app:create-admin` 会交互式问你姓名、登录标识、密码，创建一个超级管理员账号。**生产环境不要跑 `php artisan db:seed`**——那个种子数据是本地开发用的演示数据，账号密码都是固定的 `honsen2026`，跑上去等于给自己开了个后门。

进去之后用这个超级管理员账号登录后台，把公司的团队/专业结构、真实项目、真实账号都建起来。

### 8. 性能优化 + 目录权限

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo chown -R www:www /www/wwwroot/honsen-drawing
sudo chmod -R 775 storage bootstrap/cache
```

（`www` 是宝塔默认的运行用户，如果你的面板配置了别的运行用户，换成对应的名字。以后每次改了 `.env` 或者路由/配置，都要重新跑一次 `config:cache`/`route:cache`，不然改动不会生效。）

### 9. 宝塔面板：网站 + HTTPS

1. **网站 → 站点设置 → 网站目录**：把根目录改成 `/www/wwwroot/honsen-drawing/public`（**注意是 `public` 子目录，不是项目根目录**——这一步最容易出错，指错了会导致 `.env`、`vendor/` 这些敏感文件能被直接下载）
2. **网站 → 设置 → PHP 版本**：选第 2 步装好的 PHP 8.4
3. **网站 → 设置 → 伪静态**：选 "Laravel" 预设规则（面板里一般都有现成的），或者手动加：
   ```nginx
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```
4. **网站 → 设置 → SSL**：申请 Let's Encrypt 免费证书，一键开启，并且勾选"强制 HTTPS"

### 10. 防火墙

宝塔面板 → **安全**：只放行 80、443（网站）和你自己登录用的 SSH 端口；PostgreSQL 只监听本机不用额外开端口；宝塔面板自己的端口（默认 8888）建议只允许你自己的公网 IP 访问。

### 11. 上线前最后确认一遍

对照 [验收清单.md](docs/验收清单.md) 把关键流程（登录、上传变更、语言补充、文件下载、权限隔离、通知）手动过一遍，再对照下面这份检查表：

- [ ] `.env`：`APP_ENV=production`、`APP_DEBUG=false`、`SESSION_SECURE_COOKIE=true`
- [ ] 没有跑过 `php artisan db:seed`，账号都是通过 `app:create-admin` 或后台"账号管理"页面创建的
- [ ] 网站目录指向 `public` 子目录，不是项目根目录
- [ ] HTTPS 证书生效，且强制跳转
- [ ] `FILESYSTEM_DISK=cos` 且已经用真实密钥验证过上传/下载
- [ ] 随便传一份真实 DWG/PDF/说明文件测一下能不能正常下载
- [ ] `storage/` 和 `bootstrap/cache/` 目录属主和权限正确，不然日志写不进去、上传会报 500

### 后续更新代码怎么发布

```bash
cd /www/wwwroot/honsen-drawing
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

这几步也可以写成一个小脚本，或者用宝塔的"计划任务"配合 webhook 触发，具体要不要自动化看团队规模，内部工具量级手动跑一遍完全够用。

## License

[MIT](LICENSE)
