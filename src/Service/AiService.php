<?php

namespace Nopj\Ai\Service;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Arr;

class AiService
{
    protected $settings;
    protected $http;
    protected $logger;

    public function __construct(SettingsRepositoryInterface $settings, Client $http = null)
    {
        $this->settings = $settings;
        $this->http = $http ?? new Client();
        // 使用 app('log') 获取 Flarum 的日志实例
        if (function_exists('app')) {
            $this->logger = app('log');
        }
    }

    public function chat(array $messages): ?string
    {
        $endpoint = $this->settings->get('nopj-ai.api_endpoint', 'https://api.openai.com/v1');
        $apiKey = $this->settings->get('nopj-ai.api_key');
        $model = $this->settings->get('nopj-ai.model', 'gpt-3.5-turbo');
        $maxTokens = (int) $this->settings->get('nopj-ai.max_tokens', '1024');
        $temperature = (float) $this->settings->get('nopj-ai.temperature', '0.7');
        $streaming = (bool) $this->settings->get('nopj-ai.streaming', false);

        if (empty($apiKey)) {
            $this->logError('[nopj-ai] No API key configured');
            return null;
        }

        $this->logInfo("[nopj-ai] API endpoint: {$endpoint}");
        $this->logInfo("[nopj-ai] Model: {$model}");
        $this->logInfo("[nopj-ai] Streaming: " . ($streaming ? 'enabled' : 'disabled'));

        $systemPrompt = $this->settings->get('nopj-ai.system_prompt', 'You are a helpful AI assistant.');

        // Sanitize messages to prevent UTF-8 encoding errors
        $messages = $this->sanitizeUtf8Array($messages);

        $apiMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );

        $requestData = [
            'model' => $model,
            'messages' => $apiMessages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ];

        if ($streaming) {
            $requestData['stream'] = true;
        }

        try {
            $this->logInfo('[nopj-ai] Sending API request...');

            // Manually encode JSON to handle encoding errors gracefully
            $jsonPayload = json_encode($requestData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($jsonPayload === false) {
                $this->logError('[nopj-ai] Failed to encode request to JSON: ' . json_last_error_msg());
                return null;
            }

            $response = $this->http->post(rtrim($endpoint, '/') . '/chat/completions', [
                'body' => $jsonPayload,
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 60,
            ]);

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();
            
            $this->logInfo("[nopj-ai] API response status: {$statusCode}");
            $this->logInfo("[nopj-ai] Response body length: " . strlen($body));
            
            // 记录前 2000 个字符以便调试
            $this->logInfo("[nopj-ai] Raw response body: " . substr($body, 0, 2000));

            if (empty($body)) {
                $this->logError('[nopj-ai] Empty body received from API');
                return null;
            }

            // 处理流式响应 (SSE 格式)
            if ($streaming || strpos($body, 'data:') !== false) {
                $this->logInfo('[nopj-ai] Detected streaming response, parsing SSE...');
                $result = $this->parseStreamingResponse($body);
                if (!$result) {
                    $this->logError('[nopj-ai] Streaming parse returned empty. Raw body: ' . substr($body, 0, 500));
                }
                return $result;
            }

            // 常规 JSON 响应
            $bodyArray = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logError('[nopj-ai] JSON decode error: ' . json_last_error_msg());
                $this->logError('[nopj-ai] Raw body: ' . substr($body, 0, 2000));
                return null;
            }

            if (isset($bodyArray['error'])) {
                $this->logError('[nopj-ai] API error: ' . json_encode($bodyArray['error']));
                return null;
            }

            $content = Arr::get($bodyArray, 'choices.0.message.content');

            if (empty($content)) {
                $this->logWarning('[nopj-ai] Empty content in API response');
                $this->logWarning('[nopj-ai] Full response: ' . substr($body, 0, 2000));
            }

            return $content;
        } catch (RequestException $e) {
            $errorMessage = $e->getMessage();
            if ($e->hasResponse()) {
                $responseBody = (string) $e->getResponse()->getBody();
                $errorMessage .= ' | Response body: ' . substr($responseBody, 0, 2000);
            }
            $this->logError('[nopj-ai] API request failed: ' . $errorMessage);
            return null;
        } catch (ConnectException $e) {
            $this->logError('[nopj-ai] API connection failed: ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            $this->logError('[nopj-ai] Unexpected error (' . get_class($e) . '): ' . $e->getMessage());
            $this->logError('[nopj-ai] Trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    protected function parseStreamingResponse(string $body): ?string
    {
        $content = '';
        $lines = explode("\n", $body);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (strpos($line, 'data: ') === 0) {
                $data = substr($line, 6);
                if ($data === '[DONE]') break;

                $json = json_decode($data, true);
                if (!$json) continue;

                $delta = Arr::get($json, 'choices.0.delta.content');
                if ($delta) $content .= $delta;
            } else {
                // 尝试解析为常规 JSON（有些 API 返回混合格式）
                $json = json_decode($line, true);
                if ($json) {
                    $delta = Arr::get($json, 'choices.0.delta.content') 
                           ?? Arr::get($json, 'choices.0.message.content');
                    if ($delta) $content .= $delta;
                }
            }
        }

        return empty($content) ? null : $content;
    }

    protected function logInfo($message)
    {
        if ($this->logger) {
            $this->logger->info($message);
        } else {
            error_log($message);
        }
    }

    protected function logError($message)
    {
        if ($this->logger) {
            $this->logger->error($message);
        } else {
            error_log($message);
        }
    }

    protected function logWarning($message)
    {
        if ($this->logger) {
            $this->logger->warning($message);
        } else {
            error_log($message);
        }
    }

    /**
     * Sanitize an array of strings to ensure valid UTF-8
     */
    protected function sanitizeUtf8Array(array $data): array
    {
        array_walk_recursive($data, function (&$item) {
            if (is_string($item)) {
                $item = $this->sanitizeUtf8($item);
            }
        });
        return $data;
    }

    /**
     * Sanitize a single string to ensure valid UTF-8
     * Replaces invalid byte sequences with the replacement character
     */
    protected function sanitizeUtf8(string $string): string
    {
        // Use iconv to strip invalid characters
        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $string);
        
        // If iconv fails or returns empty, use mb_convert_encoding as fallback
        if ($cleaned === false || $cleaned === '') {
            $cleaned = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        }
        
        return $cleaned;
    }
}
