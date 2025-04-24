# kassisV2

phase1

Ubuntu 24.04 LTS
PHP 8.3 + Mysql 8.0 で動作
E2Eテスト用に node.js 18.9.1

sudo mysql -u root
CREATE USER 'kassisv2'@'localhost' IDENTIFIED BY 'kassisv2passwd';
CREATE DATABASE kassisv2db;
GRANT ALL ON kassisv2db.* TO 'kassisv2'@'localhost';

sudo apt install nodejs npm
npm init playwright@latest

npx playwright test 


ページを追加する
https://symfony.com/doc/current/page_creation.html

## Creating a Page: Route and Controller
php bin/console make:controller

## Create a route
https://symfony.com/doc/current/routing.html

コントローラーのアクションの上部に追加する。

#[Route('/api/posts/{id}', methods: ['GET', 'HEAD'])]


## ルーティングを確認する
php bin/console debug:router

## テンプレートキャッシュをクリアする

php bin/console cache:clear --no-warmup
php bin/console cache:warmup
