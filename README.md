# kassisV2

kassisV2 は、書籍などを管理するアプリです。

## できること

- ISBNからの取り込み(国立国会図書館サーチ APIからの取得 https://ndlsearch.ndl.go.jp/api/opensearch)
- Amazonの購入履歴ファイル(Your Orders.zip)からの取り込み
- CSVまたはエクセル形式でのインポート
- CSVまたはエクセル形式でのエクスポート
- 検索および検索結果の表示

## 必要なミドルウェア等

PHP 8.3以上, MySQL 8.0以上

## 動作環境の構築方法

### さくらインターネット

エクセルファイルやAmazonの購入履歴ファイル(Your Order.zip)からデータを取り込む場合は、
設定を変更する必要があります。ファイルサイズを確認してください。
該当する php.ini の設定を変更してください。

``` /etc/php/8.4/cli/php.ini
upload_max_filesize = 50M
post_max_size = 60M
```

あわせて、大きめファイルだと処理時間も伸びるので必要に応じて修正してください。

``` /etc/php/8.4/cli/php.ini
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
```

アプリのルートフォルダに .env.local を新規作成して、以下のようにDB接続先を記載。

```
DATABASE_URL="mysql://kassisv2:kassisv2passwd@localhost:3306/kassisv2db?serverVersion=8.0.32&charset=utf8mb4"
```

### Ubuntu 24.04 LTS

sudo apt install php8.4-mysql

sudo apt install php8.4-zip php8.4-intl php8.4-mbstring
sudo apt install php8.4-xml php8.4-xmlwriter php8.4-simplexml php8.4-dom
sudo apt install php8.4-sqlite3 


composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

### DBスキーマおよびインポートマッピング

https://docs.google.com/spreadsheets/d/17KYRc5HVzcFlP_6cMGh88QEuyNG9tBeC0ikRgiFZC74/edit?usp=sharing

### 参考情報として開発環境

Ubuntu 24.04 LTS
PHP 8.4.15 + Mysql 8.0.44 で開発
E2Eテスト用に node.js 18.9.1

## その他開発時メモ

### DBとか
sudo mysql -u root
CREATE USER 'kassisv2'@'localhost' IDENTIFIED BY 'kassisv2passwd';
CREATE DATABASE kassisv2db;
GRANT ALL ON kassisv2db.* TO 'kassisv2'@'localhost';

DROP DATABASE kassisv2db;

sudo apt install nodejs npm
npm init playwright@latest

npx playwright test

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

# PHPでデバッグ

「phpinfo」をブラウザで表示する。

vi phpinfo.php
```
<?php
phpinfo();
?>
```

Xdebugのインストールページへアクセスする。
https://xdebug.org/wizard

さきのphpinfoの内容を全コピーしたものをテキストボックスに貼り付ける。
画面下部の 「Analyze my phpinfo() output」をクリックする。

結果が表示されるので、
Instructions に記載のとおりに対応する。

参考：Ubuntu 24.04 LTS + PHP 8.4 環境の場合
〜〜〜
apt-get install php8.4-dev autoconf automake

xdebug* をダウンロードする。~/temp/phpdebug 等

cd ~/temp/phpdebug
tar -xvzf xdebug-3.5.0.tgz
cd xdebug-3.5.0
phpize
./configure
make
sudo cp modules/xdebug.so /usr/lib/php/20240924/

vi /etc/php/8.4/cli/conf.d/99-xdebug.ini
~~~
zend_extension=xdebug.so
[xdebug]
xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.log=/tmp/xdebug.log
xdebug.log_level=10
xdebug.client_host=127.0.0.1
xdebug.client_port=9003
~~~q

$ php --version
PHP 8.4.15 (cli) (built: Nov 20 2025 17:43:25) (NTS)
Copyright (c) The PHP Group
Built by Debian
Zend Engine v4.4.15, Copyright (c) Zend Technologies
with Zend OPcache v8.4.15, Copyright (c), by Zend Technologies
with Xdebug v3.5.0, Copyright (c) 2002-2025, by Derick Rethans

Xdebug と表示されているので有効化された。

