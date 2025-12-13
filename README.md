# kassisV2

kassisV2 は、書籍などを管理するアプリです。

## できること



## 必要なミドルウェア等

PHP 8.3以上, MySQL 8.0以上

## 動作環境の構築方法

### さくらインターネット

.env.dev ファイルを .env.local としてコピーして、接続先を記載。

```
DATABASE_URL="mysql://kassisv2:kassisv2passwd@localhost:3306/kassisv2db?serverVersion=8.0.32&charset=utf8mb4"
```

### Ubuntu 24.04 LTS
sudo mysql -u root
CREATE USER 'kassisv2'@'localhost' IDENTIFIED BY 'kassisv2passwd';
CREATE DATABASE kassisv2db;
GRANT ALL ON kassisv2db.* TO 'kassisv2'@'localhost';

sudo apt install nodejs npm
npm init playwright@latest

npx playwright test 

## その他開発時メモ

簡易Webサーバで起動する
symfony server:start --allow-all-ip
symfony server:start -d --allow-all-ip

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

## DBツール

https://www.heidisql.com/download.php

heidisql &

### DB

https://docs.google.com/spreadsheets/d/17KYRc5HVzcFlP_6cMGh88QEuyNG9tBeC0ikRgiFZC74/edit?usp=sharing

### 参考情報として開発環境

Ubuntu 24.04 LTS
PHP 8.4.15 + Mysql 8.0.44 で開発
E2Eテスト用に node.js 18.9.1

