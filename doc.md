# LibreSpeed 中文版

> 作者 v6ole
> 基于 Federico Dossena 的 LibreSpeed 5.4.1 版本
> [https://github.com/librespeed/speedtest/](https://github.com/librespeed/speedtest/)

## 简介

本项目是一个您可以部署在自己服务器上的自由开源的HTML5速度测试程序。

__功能特性:__

*   下载速度测试
*   上传速度测试
*   Ping 和抖动测试
*   IP地址、运营商和地理位置检测
*   遥测（可选，用于记录测试结果）
*   结果分享（可选）
*   多测试点（可选）

__浏览器支持:__
本测试支持所有支持 XHR Level 2 和 Web Workers 的现代浏览器，并需要启用 JavaScript。

## 安装

### 单服务器, PHP

服务器端需求:

*   Apache 2 (同样支持 nginx 和 IIS)。需要有较快的网络连接（建议千兆），并且Web服务器必须接受大的POST请求（最大20MB）。
*   PHP 5.4 或更高版本（为获得最佳的ISP和距离检测功能，建议使用PHP 8.0），强烈推荐使用64位版本。
*   OpenSSL 及其PHP模块。
*   如果您希望存储测试结果（遥测功能），需要以下之一：
    *   MySQL/MariaDB 及其 PHP PDO 模块
    *   PostgreSQL 及其 PHP PDO 模块
    *   SQLite 3 及其 PHP PDO 模块
*   如果您希望启用结果分享功能，需要：
    *   FreeType 2 及其 PHP 模块
    *   PHP gd 库

#### IP信息检测

本测速项目可以通过调用公开API来检测用户的ISP（运营商）和国家信息。这是一个可选功能，默认启用。

#### 遥测与结果分享

本测试支持存储测试结果，并可以生成用户可以分享的图片。

要使用此功能，您需要一个数据库。支持 MySQL, PostgreSQL 和 SQLite 作为后端。

##### 创建数据库

此步骤仅适用于 MySQL 和 PostgreSQL。如果您想使用 SQLite，请跳到下一步。

登录到您的数据库管理工具（如 phpMyAdmin），创建一个新的数据库。在 `results` 文件夹中，您会找到 `telemetry_mysql.sql` 和 `telemetry_postgresql.sql`，它们分别是MySQL和PostgreSQL的模板。导入您需要的模板，您将在数据库中看到一个 `speedtest_users` 表。

##### 配置遥测

打开 `results/telemetry_settings.php` 文件。将 `$db_type` 设置为 `mysql`、`postgresql` 或 `sqlite`。

如果您选择使用 SQLite，您可能需要更改 `$Sqlite_db_file` 为您希望存储数据库文件的路径。请确保该文件不能被用户通过Web直接下载。

如果您选择使用 MySQL 或 PostgreSQL，您必须设置您的数据库凭据。

##### 结果分享

此功能会生成一张包含下载、上传、Ping、抖动和ISP信息（如果启用）的图片，用户可以分享它。

默认情况下，遥测功能为每个测试生成一个连续的ID。为了避免用户能够猜测其他测试的ID，您可以启用ID混淆功能。

要启用ID混淆，请编辑 `results/telemetry_settings.php` 并将 `$enable_id_obfuscation` 设置为 `true`。此功能目前仅在64位PHP上有效！

同时，您可能还希望将 `$redact_ip_addresses` 设置为 `true`，这样所有的IP地址将从遥测数据中移除，以更好地保护隐私。

##### 查看结果

一个用于可视化和搜索测试结果的基本前端界面位于 `results/stats.php`。
访问此界面需要登录。**重要提示**：请务必在 `results/telemetry_settings.php` 中更改默认密码。

#### 隐私

遥测数据包含个人信息（根据GDPR定义），因此尊重国家和国际法律来处理这些数据非常重要。

默认的 `index.html` 包含一个服务的隐私政策：您**必须**阅读它，必要时进行修改，并添加您的电子邮件地址以处理数据删除请求。

## 自定义前端

本节说明如何在您的网页中使用 speedtest.js。

最佳的学习方式是查看 `examples` 文件夹中提供的示例。

### 初始化

要在您的页面中使用此测速工具，首先需要加载它：

```xml
<script type="text/javascript" src="speedtest.js"></script>
```

加载后，您可以初始化测试：

```js
var s = new Speedtest();
```

### 事件处理器

现在，您可以设置事件处理器来更新您的UI：

```js
s.onupdate = function(data){
    // 在这里更新您的UI
}
s.onend = function(aborted){
    // 测试结束
    if(aborted){
        // 如果测试被中止而不是正常结束，可以在这里处理
    }
}
```

`onupdate` 事件处理器会由测试程序定期调用，并传入来自测速工作线程的数据。`data` 参数是一个包含以下内容的对象：

*   __testState__: 一个-1到5之间的整数
    *   `-1` = 测试未开始
    *   `0` = 测试正在开始
    *   `1` = 下载测试进行中
    *   `2` = Ping + 抖动测试进行中
    *   `3` = 上传测试进行中
    *   `4` = 测试完成
    *   `5` = 测试已中止
*   __dlStatus__: 下载速度（Mbps）
*   __ulStatus__: 上传速度（Mbps）
*   __pingStatus__: Ping延迟（ms）
*   __clientIp__: 客户端IP地址
*   __jitterStatus__: 抖动（ms）
*   __dlProgress__: 下载测试进度（0-1）
*   __ulProgress__: 上传测试进度（0-1）
*   __pingProgress__: Ping测试进度（0-1）

### 测试参数

在开始测试之前，您可以更改一些默认设置。

```js
s.setParameter("parameter_name", value);
```

例如，要启用遥测功能：

```js
s.setParameter("telemetry_level", "basic");
```

__主要参数:__

*   __`time_dl_max`__: 下载测试的最大持续时间（秒）。默认: `15`
*   __`time_ul_max`__: 上传测试的最大持续时间（秒）。默认: `15`
*   __`count_ping`__: Ping测试的次数。默认: `10`
*   __`test_order`__: 测试执行的顺序。
    *   `I`: 获取IP
    *   `D`: 下载测试
    *   `U`: 上传测试
    *   `P`: Ping + 抖动测试
    *   `_`: 延迟1秒
    *   默认顺序: `IP_D_U`

## 技术实现细节

### `backend` 文件

#### `garbage.php`

使用 OpenSSL 生成一个不可压缩的垃圾数据流，用于下载测试。

#### `empty.php`

一个空文件，用于上传和Ping测试。它仅发送头部信息以创建连接。

#### `getIP.php`

返回客户端的IP、ISP和与服务器的距离。

## 许可证

本软件遵循 GNU LGPL 许可证，版本3或更新。

简而言之：您可以自由地使用、学习、修改和重新分发本软件及其修改版本，无论是免费还是收费。
您也可以在专有软件中使用它，但对本软件的所有更改必须保留在相同的 GNU LGPL 许可证下。
