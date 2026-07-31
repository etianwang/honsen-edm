#!/bin/sh
set -e

cd /var/www/html

php artisan package:discover --ansi

# 容器无状态、每次重启都是新文件系统，不在这里自动 key:generate——那样每次重启都会换一把新
# 密钥，会让所有已加密的会话/数据失效。APP_KEY 必须已经写在 .env 里（本地跑一次
# `php artisan key:generate --show` 把输出粘贴进 .env，和裸机部署共用同一份 .env 逻辑一致）。
if [ -z "$APP_KEY" ]; then
    echo "错误：APP_KEY 未设置。请先在 .env 里配置 APP_KEY（可用 php artisan key:generate --show 生成）。" >&2
    exit 1
fi

if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
fi

php artisan storage:link || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

case "$1" in
    worker)
        exec php artisan queue:work --tries=3 --timeout=90 --sleep=3
        ;;
    web|*)
        exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
        ;;
esac
