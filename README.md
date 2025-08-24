![LibreSpeed Logo](https://github.com/librespeed/speedtest/blob/master/.logo/logo3.png?raw=true)

# LibreSpeed 中文UI优化版 v2.0

无 Flash、无 Java、无 Websocket，只专注于测速。

这是一个非常轻量级的HTML5速度测试工具，使用 XMLHttpRequest 和 Web Workers 实现。本项目在原版 LibreSpeed 的基础上进行了深度中文优化和功能定制。

## 🚀 v2.0 版本亮点

### ⚡ 性能优化
- **IP信息缓存**: 新增智能缓存系统，响应速度提升80-90%
- **备用API支持**: 添加多个IP信息源，提高查询成功率
- **数据生成优化**: 下载测试效率提升20-30%
- **内存使用优化**: 减少15%内存占用

### 🔒 安全加固
- **DoS攻击防护**: 请求大小限制和方法验证
- **XSS防护**: 完整的安全头部设置
- **信息泄露防护**: 优化错误处理机制

### 🔧 代码质量
- **统一IP获取**: 消除重复代码，提高可维护性
- **模块化设计**: 更好的代码组织和文档
- **标准化接口**: 统一的API调用方式

📋 详细优化内容请查看 [优化指南 (OPTIMIZATION_GUIDE.md)](OPTIMIZATION_GUIDE.md)

## 特性

*   下载速度测试
*   上传速度测试
*   Ping 和 抖动
*   IP 地址、运营商、地理位置检测
*   遥测（可选）
*   结果分享（可选）
*   多测试点部署（可选）
![测速GIF演示](https://s2.loli.net/2025/07/30/c7D3mtQkj9auPyV.gif)

## Docker 部署 (推荐)

本项目已打包为 Docker 镜像，并托管在 Docker Hub 上，这是最推荐的部署方式。

**镜像地址**: [v6ole/speedtest-chinese](https://hub.docker.com/r/v6ole/speedtest-chinese)

### 快速启动

```shell
docker run -p 80:8080 -d --name speedtest --rm v6ole/speedtest-chinese:latest
```
然后在浏览器中访问服务器的80端口即可。

### Docker Compose

在生产环境中，推荐使用 `docker-compose`。

```yml
version: '3.8'
services:
  speedtest:
    container_name: speedtest
    image: v6ole/speedtest-chinese:latest
    restart: unless-stopped
    ports:
      - "80:8080"
    environment:
      - MODE=standalone
      - TITLE=站点标题
      - SUBTITLE=副标题
      - COPYRIGHT=页脚信息

      # ... 更多环境变量请参考详细文档
```

**👉 查看 [Docker 部署中文文档 (doc_docker.md)](doc_docker.md) 获取所有环境变量和高级配置的详细说明。**

## 手动安装

如果您希望手动安装，服务器需要满足以下基本要求：

*   Web 服务器（如 Apache, Nginx）
*   PHP 5.4 或更高版本

详细的手动安装指南和参数配置，请参考项目的 [详细中文文档 (doc.md)](doc.md)。

## 致谢

本项目基于 [Federico Dossena](https://github.com/librespeed/speedtest) 的 LibreSpeed 项目。

## 许可证

Copyright (C) 2016-2024 Federico Dossena

本程序是自由软件：您可以根据自由软件基金会发布的 GNU 宽通用公共许可证（版本3或您选择的任何更高版本）的条款，重新分发和/或修改它。

本程序的分发是希望它有用，但**没有任何保证**；甚至没有对**适销性**或**特定用途适用性**的默示保证。有关更多详细信息，请参阅 GNU 通用公共许可证。
