# 使用 Docker 镜像

本项目的 Docker 镜像托管在 Docker Hub 上： [v6ole/speedtest-chinese](https://hub.docker.com/r/v6ole/speedtest-chinese)

## 快速入门

如果您只是想快速体验，最便捷的方式是：

```shell
docker run -p 80:8080 -d --name speedtest --rm v6ole/speedtest-chinese:latest
```

然后在您的浏览器中访问服务器的80端口即可。如果80端口已被占用，请调整上述命令中 `80:8080` 的第一个数字。
默认以独立模式（standalone）运行。

## Docker Compose

在生产环境中，我们推荐使用 docker-compose 进行部署。

您可以使用以下 `docker-compose.yml` 配置来启动容器：

```yml
version: '3.8'
services:
  speedtest:
    container_name: speedtest
    image: v6ole/speedtest-chinese:latest
    restart: unless-stopped
    environment:
      MODE: standalone
      TITLE: "宽带测试"
      SUBTITLE: "测试您的网络连接速度"
      LOGO_TEXT: "SpeedTest"
      COPYRIGHT: "© 2024 Your Company"
      TELEMETRY: "false"
      # 根据您的需求调整更多环境变量
    ports:
      - "80:8080" # 端口映射 (主机:容器)
```

请根据您的预期操作模式调整环境变量。

## 独立模式 (Standalone mode)

如果您想在单台服务器上安装测速服务，您需要将其配置为独立模式。为此，请将 `MODE` 环境变量设置为 `standalone`。

测速服务默认可以通过80端口访问。

以下是此模式下可用的环境变量列表：

*   __`TITLE`__: 测速页面的主标题。
*   __`SUBTITLE`__: 测速页面的副标题。
*   __`LOGO_TEXT`__: 测速页面Logo区域的文字。
*   __`COPYRIGHT`__: 页面底部的版权信息。
*   __`TELEMETRY`__: 是否启用遥测（记录测试结果）。如果启用，您可能希望持久化存储数据。详见下文。默认值: `false`
*   __`ENABLE_ID_OBFUSCATION`__: 启用遥测后，将测试ID进行混淆，以避免暴露数据库内部的连续ID。默认值: `false`
*   __`REDACT_IP_ADDRESSES`__: 启用遥测后，从收集的数据中隐去IP地址和主机名，以更好地保护隐私。默认值: `false`
*   __`DB_TYPE`__: 当设置为支持的数据库后端之一时，将使用此数据库而不是默认的sqlite数据库。`TELEMETRY` 必须设置为 `true`。您还必须按照 [doc.md](doc.md) 中的说明创建数据库。支持的后端类型有：
    *   `sqlite` - 无需额外设置
    *   `mysql`, `postgresql` - 需要设置额外的环境变量:
        *   `DB_HOSTNAME` - 数据库服务器的名称或IP
        *   `DB_PORT` (仅mysql) - 数据库运行的端口
        *   `DB_NAME` - 遥测数据库的名称
        *   `DB_USERNAME`, `DB_PASSWORD` - 对数据库具有读写权限的用户的凭据
*   __`PASSWORD`__: 访问统计页面的密码。如果未设置，统计页面将不允许访问。
*   __`EMAIL`__: 用于GDPR（数据保护）请求的电子邮件地址。启用遥测时必须指定。
*   __`WEBPORT`__: 允许为内置的Web服务器选择一个自定义端口。默认值: `8080`。请注意，您将需要通过docker的 `-p` 参数来暴露它。这**不是**服务在docker外部暴露的端口！

如果启用了遥测，可以在 `http://your.server/results/stats.php` 访问统计页面，但必须指定密码。

### 持久化 sqlite 数据库

默认的数据库驱动是 sqlite。数据库文件被写入到 `/database/db.sql`。

因此，如果您希望在镜像更新后数据仍然保留，您必须挂载一个卷，例如 `-v $PWD/db-dir:/database`。

#### 示例：带遥测功能的独立模式

此命令以独立模式启动测速服务，启用持久化遥测、ID混淆和统计密码，并监听86端口：

```shell
docker run -e MODE=standalone -e TELEMETRY=true -e ENABLE_ID_OBFUSCATION=true -e PASSWORD="yourPasswordHere" -e WEBPORT=86 -p 86:86 -v $PWD/db-dir/:/database -it v6ole/speedtest-chinese:latest
```

## 多测试点模式 (Multiple Points of Test)

对于多服务器部署，您需要设置1个或多个LibreSpeed后端，以及1个LibreSpeed前端。

### 后端模式 (Backend mode)

在后端模式下，LibreSpeed仅提供一个没有UI的测试点。为此，请将 `MODE` 环境变量设置为 `backend`。

可以通过80端口访问以下后端文件：`garbage.php`, `empty.php`, `getIP.php`

此模式下可用的额外环境变量：

*   __`IPINFO_APIKEY`__: 用于 [ipinfo.io](https://ipinfo.io) 的API密钥。可选，但如果您想使用完整的 [ipinfo.io](https://ipinfo.io) API（例如距离测量），则为必需。如果未提供API密钥，将使用离线数据库。

#### 示例：后端模式

此命令以默认设置在80端口启动一个后端模式的测速服务：

```shell
docker run -e MODE=backend -p 80:8080 -it v6ole/speedtest-chinese:latest
```

### 前端模式 (Frontend mode)

在前端模式下，LibreSpeed为客户端提供Web UI和服务器列表。为此，您需要：

*   将 `MODE` 环境变量设置为 `frontend`
*   创建一个包含您的测试点的 `servers.json` 文件。语法如下：

    ```jsonc
    [
        {
            "name": "服务器1的友好名称",
            "server" :"//server1.mydomain.com/",
            "dlURL" :"garbage.php",
            "ulURL" :"empty.php",
            "pingURL" :"empty.php",
            "getIpURL" :"getIP.php"
        },
        //...更多服务器...
    ]
    ```
*   将此文件挂载到容器内的 `/servers.json` (示例见文末)

此模式下可用的环境变量与[独立模式](#standalone-mode)相同。

#### 示例：前端模式

此命令以前端模式启动测速服务，使用给定的 `servers.json` 文件，并启用遥测、ID混淆、统计密码和持久化sqlite数据库：

```shell
docker run -e MODE=frontend -e TELEMETRY=true -e ENABLE_ID_OBFUSCATION=true -e PASSWORD="yourPasswordHere" -v $PWD/servers.json:/servers.json -v $PWD/db-dir/:/database -p 80:80 -it v6ole/speedtest-chinese:latest
```

### 双重模式 (Dual mode)

在双重模式下，LibreSpeed作为一个独立的服务器运行，同时也可以连接到其他测试点。
为此，您需要：

*   将 `MODE` 环境变量设置为 `dual`
*   遵循前端模式的 `servers.json` 说明
*   `servers.json` 中的第一个条目应该是本地服务器，使用客户端可以访问的服务器端点地址。
