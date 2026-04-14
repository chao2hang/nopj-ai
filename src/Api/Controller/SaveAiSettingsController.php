<?php

namespace Nopj\Ai\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SaveAiSettingsController implements RequestHandlerInterface
{
    protected $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $body = $request->getParsedBody();
        $settings = Arr::get($body, 'settings', []);

        $settingsMap = [
            'ai_user_id' => 'nopj-ai.ai_user_id',
            'api_endpoint' => 'nopj-ai.api_endpoint',
            'api_key' => 'nopj-ai.api_key',
            'model' => 'nopj-ai.model',
            'system_prompt' => 'nopj-ai.system_prompt',
            'max_tokens' => 'nopj-ai.max_tokens',
            'temperature' => 'nopj-ai.temperature',
            'context_posts_count' => 'nopj-ai.context_posts_count',
            'streaming' => 'nopj-ai.streaming',
        ];

        foreach ($settingsMap as $inputKey => $settingKey) {
            if (isset($settings[$inputKey])) {
                $this->settings->set($settingKey, $settings[$inputKey]);
            }
        }

        return new EmptyResponse(204);
    }
}
