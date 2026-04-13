<?php

use Flarum\Api\Serializer\BasicUserSerializer;
use Flarum\Extend;
use Flarum\Post\Event\Posted;
use Nopj\Ai\Listener\TriggerAiReplyListener;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/resources/less/admin.less'),

    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/resources/less/forum.less'),

    new Extend\Locales(__DIR__ . '/resources/locale'),

    (new Extend\Settings())
        ->default('nopj-ai.ai_user_id', '')
        ->default('nopj-ai.api_endpoint', 'https://api.openai.com/v1')
        ->default('nopj-ai.api_key', '')
        ->default('nopj-ai.model', 'gpt-3.5-turbo')
        ->default('nopj-ai.system_prompt', "You are a helpful AI assistant integrated in a Flarum forum. Answer questions concisely and helpfully based on the discussion context provided.")
        ->default('nopj-ai.max_tokens', '1024')
        ->default('nopj-ai.temperature', '0.7')
        ->default('nopj-ai.context_posts_count', '5')
        ->serializeToForum('nopj-ai.ai_user_id', 'nopj-ai.ai_user_id'),

    (new Extend\Event())
        ->listen(Posted::class, TriggerAiReplyListener::class),

    (new Extend\Routes('api'))
        ->post('/nopj-ai/settings', 'nopj-ai.settings', \Nopj\Ai\Api\Controller\SaveAiSettingsController::class),
];
