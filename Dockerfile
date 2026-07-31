# syntax=docker/dockerfile:1

# ---- 前端静态资源构建 ----
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install
COPY resources resources
COPY vite.config.js ./
RUN npm run build

# ---- PHP 依赖安装 ----
# 只拷贝运行时真正需要的目录（不要 docker/、tests/、docs/、.git 这些），避免它们被垃圾进最终镜像；
# --no-scripts 跳过 post-autoload-dump（会调用 artisan package:discover，构建阶段没有 .env/APP_KEY，
# 放到 entrypoint.sh 里在容器启动时补跑一次）。
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock artisan ./
COPY app app
COPY bootstrap bootstrap
COPY config config
COPY database database
COPY resources resources
COPY routes routes
COPY public public
COPY storage storage
RUN composer install --no-dev --no-scripts --optimize-autoloader --prefer-dist

# ---- 运行时镜像：php-fpm + nginx + supervisor，内含 ODA File Converter ----
FROM php:8.4-fpm-bookworm AS runtime

ARG WITH_ODA=true

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor \
        libpq-dev libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libonig-dev libcurl4-openssl-dev \
        unzip git curl \
        xvfb gdebi-core \
        libgl1 libegl1 libxkbcommon0 libdbus-1-3 libfontconfig1 libfreetype6 \
        libxcb-cursor0 libxcb-icccm4 libxcb-image0 libxcb-keysyms1 libxcb-randr0 \
        libxcb-render-util0 libxcb-shape0 libxcb-xinerama0 libxcb-xfixes0 \
    && rm -rf /var/lib/apt/lists/*

# mbstring 是 Laravel 框架硬依赖，curl 是 Guzzle/腾讯云 COS SDK 硬依赖，
# 官方 php-fpm 基础镜像默认都不带，必须显式装
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql gd intl zip bcmath opcache mbstring curl

# Ubuntu/Debian 较新版本常缺这个符号链接，ODA File Converter (Qt) 运行时会报 libxcb-util.so.0 找不到
RUN if [ -e /usr/lib/x86_64-linux-gnu/libxcb-util.so.1 ] && [ ! -e /usr/lib/x86_64-linux-gnu/libxcb-util.so.0 ]; then \
        ln -s /usr/lib/x86_64-linux-gnu/libxcb-util.so.1 /usr/lib/x86_64-linux-gnu/libxcb-util.so.0; \
    fi

# ODA File Converter 需从官网人工注册下载（见 docs/DWG看图接入说明.md），
# 构建前把 .deb 放到 docker/oda/ 目录下，本 Dockerfile 会自动找到并安装。
# 用整目录 COPY（而不是通配符 *.deb）是因为目录下没有 .deb 时通配符会导致 COPY 直接构建失败。
COPY docker/oda/ /tmp/oda/
RUN if [ "$WITH_ODA" = "true" ] && ls /tmp/oda/*.deb >/dev/null 2>&1; then \
        apt-get update && \
        (dpkg -i /tmp/oda/*.deb || true) && \
        apt-get install -y -f --no-install-recommends && \
        rm -rf /var/lib/apt/lists/*; \
    else \
        echo "未找到 ODA File Converter .deb，跳过安装（DWG 交互式预览将不可用，见 docker/oda/README.md）"; \
    fi; \
    rm -rf /tmp/oda

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/nginx/site.conf /etc/nginx/sites-enabled/default
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
        storage/logs storage/app/public bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["web"]
