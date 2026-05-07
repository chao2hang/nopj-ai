# Nopj AI

Flarum 的 AI 助手集成扩展，支持多轮对话、异步回复和原生回复式提及。

[![Packagist Version](https://img.shields.io/packagist/v/nopj/ai?style=flat-square)](https://packagist.org/packages/nopj/ai)
[![Packagist Downloads](https://img.shields.io/packagist/dt/nopj/ai?style=flat-square)](https://packagist.org/packages/nopj/ai)
[![GitHub Stars](https://img.shields.io/github/stars/chao2hang/nopj-ai?style=flat-square)](https://github.com/chao2hang/nopj-ai/stargazers)
[![License](https://img.shields.io/github/license/chao2hang/nopj-ai?style=flat-square)](https://github.com/chao2hang/nopj-ai/blob/main/LICENSE)

## 功能特性

- 异步 AI 回复，不会阻塞用户正常发帖
- 使用 Flarum 原生 `@"显示名称"#p帖子ID` 格式渲染回复式提及
- 通过持久化的 AI 会话和消息实现多轮讨论记忆
- 可配置 API 端点、模型、系统提示、上下文深度和温度
- 与 Flarum `flarum/mentions` 扩展完美兼容

## 环境要求

- Flarum `^1.8`
- PHP 8.x
- `flarum/mentions`
- 兼容的 Chat Completions API 端点

## 安装

使用 Composer 安装：

```bash
composer require nopj/ai
```

然后在 Flarum 管理面板中启用该扩展。

## 配置

在管理面板中配置以下选项：

- AI 用户
- API 端点
- API 密钥
- 模型
- 系统提示
- 最大令牌数
- 温度
- 上下文帖子数量
- 流式模式

## 工作原理

1. 用户回复 AI 用户，或回复由 AI 用户发布的帖子。
2. 插件立即创建一个“正在输入”的占位帖子。
3. 实际的 AI 请求异步运行。
4. 占位帖子在完成后更新为最终的 AI 回复。

这样可以保持正常发帖的响应性，同时保留原生的 Flarum 提及渲染和通知机制。

## 注意事项

- 最佳生产环境表现需要搭配真实的异步队列 worker 使用。
- 如果论坛仍在使用 Flarum 的同步队列，扩展会自动回退到响应后后台执行。
- 该扩展目前主要针对回复式帖子提及，而非传统的 `@user` 提及通知。

## 开发

从扩展目录构建前端资源：

```bash
cd js
npm install
npm run build
```

## 链接

- 源码：https://github.com/chao2hang/nopj-ai
- 问题反馈：https://github.com/chao2hang/nopj-ai/issues
- Packagist：https://packagist.org/packages/nopj/ai

## 许可证

MIT