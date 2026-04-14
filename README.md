# Nopj AI

[![Packagist Version](https://img.shields.io/packagist/v/nopj/ai?style=flat-square)](https://packagist.org/packages/nopj/ai)
[![Packagist Downloads](https://img.shields.io/packagist/dt/nopj/ai?style=flat-square)](https://packagist.org/packages/nopj/ai)
[![GitHub Stars](https://img.shields.io/github/stars/chao2hang/nopj-ai?style=flat-square)](https://github.com/chao2hang/nopj-ai/stargazers)
[![License](https://img.shields.io/github/license/chao2hang/nopj-ai?style=flat-square)](https://github.com/chao2hang/nopj-ai/blob/main/LICENSE)

AI assistant integration for Flarum with multi-turn conversation, async replies, and native reply-style mentions.

## Features

- Async AI replies that do not block normal user posting
- Reply-style mention rendering using Flarum native `@"Display Name"#pPOST_ID` format
- Multi-turn discussion memory via persisted AI sessions and messages
- Configurable API endpoint, model, system prompt, context depth, and temperature
- Works with Flarum `flarum/mentions`

## Requirements

- Flarum `^1.8`
- PHP 8.x
- `flarum/mentions`
- A compatible chat completions API endpoint

## Installation

Install with Composer:

```bash
composer require nopj/ai
```

Then enable the extension in the Flarum admin panel.

## Configuration

In the admin panel, configure:

- AI user
- API endpoint
- API key
- Model
- System prompt
- Max tokens
- Temperature
- Context posts count
- Streaming mode

## How It Works

1. A user replies to the AI user, or replies to a post authored by the AI user.
2. The plugin immediately creates a typing placeholder post.
3. The actual AI request runs asynchronously.
4. The placeholder post is updated into the final AI response after completion.

This keeps normal posting responsive while preserving native Flarum mention rendering and notifications.

## Notes

- Best production behavior is achieved with a real async queue worker.
- If the forum is still using Flarum's sync queue, the extension falls back to post-response background execution.
- The extension currently targets reply-style post mentions rather than `@user` mention notifications.

## Development

Build frontend assets from the extension directory:

```bash
cd js
npm install
npm run build
```

## Links

- Source: https://github.com/chao2hang/nopj-ai
- Issues: https://github.com/chao2hang/nopj-ai/issues
- Packagist: https://packagist.org/packages/nopj/ai

## License

MIT
