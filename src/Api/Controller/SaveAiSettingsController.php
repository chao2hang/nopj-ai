<?php

namespace Nopj\Ai\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class SaveAiSettingsController extends AbstractListController
{
    public $serializer = null;

    protected $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        RequestUtil::getActor($request)->assertAdmin();

        $body = $request->getParsedBody();

        $settingsMap = [
            'ai_user_id' => 'nopj-ai.ai_user_id',
            'api_endpoint' => 'nopj-ai.api_endpoint',
            'api_key' => 'nopj-ai.api_key',
            'model' => 'nopj-ai.model',
            'system_prompt' => 'nopj-ai.system_prompt',
            'max_tokens' => 'nopj-ai.max_tokens',
            'temperature' => 'nopj-ai.temperature',
            'context_posts_count' => 'nopj-ai.context_posts_count',
        ];

        foreach ($settingsMap as $inputKey => $settingKey) {
            if (Arr::has($body, "settings.{$inputKey}")) {
                $this->settings->set($settingKey, Arr::get($body, "settings.{$inputKey}"));
            }
        }

        return $this->settings->all();
    }
}
