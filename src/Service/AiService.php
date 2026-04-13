<?php

namespace Nopj\Ai\Service;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Arr;

class AiService
{
    protected $settings;
    protected $http;

    public function __construct(SettingsRepositoryInterface $settings, Client $http = null)
    {
        $this->settings = $settings;
        $this->http = $http ?? new Client();
    }

    public function chat(array $messages): ?string
    {
        $endpoint = $this->settings->get('nopj-ai.api_endpoint', 'https://api.openai.com/v1');
        $apiKey = $this->settings->get('nopj-ai.api_key');
        $model = $this->settings->get('nopj-ai.model', 'gpt-3.5-turbo');
        $maxTokens = (int) $this->settings->get('nopj-ai.max_tokens', '1024');
        $temperature = (float) $this->settings->get('nopj-ai.temperature', '0.7');

        if (empty($apiKey)) {
            return null;
        }

        $systemPrompt = $this->settings->get('nopj-ai.system_prompt', 'You are a helpful AI assistant.');

        $apiMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );

        try {
            $response = $this->http->post(rtrim($endpoint, '/') . '/chat/completions', [
                'json' => [
                    'model' => $model,
                    'messages' => $apiMessages,
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 60,
            ]);

            $body = json_decode((string) $response->getBody(), true);

            return Arr::get($body, 'choices.0.message.content');
        } catch (RequestException $e) {
            \Log::error('nopj-ai: API request failed: ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            \Log::error('nopj-ai: Unexpected error: ' . $e->getMessage());
            return null;
        }
    }
}
