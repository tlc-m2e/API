<?php

namespace TLC\Hook\Services;

use TLC\Core\Config;

class AIService
{
    private string $provider;
    private string $apiKey;

    public function __construct()
    {
        $this->provider = strtolower(Config::get('AI_PROVIDER', 'openai'));
        $this->apiKey = Config::get('AI_API_KEY', '');
    }

    public function analyzeWorkout($workoutData): array
    {
        // Convert BSONDocument to array if needed
        if (is_object($workoutData) && method_exists($workoutData, 'getArrayCopy')) {
            $workoutData = $workoutData->getArrayCopy();
        } else {
            $workoutData = (array) $workoutData;
        }

        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error' => 'AI API Key is not configured.'
            ];
        }

        $prompt = $this->buildPrompt($workoutData);

        if ($this->provider === 'gemini') {
            return $this->callGemini($prompt);
        } else {
            // Default to OpenAI
            return $this->callOpenAI($prompt);
        }
    }

    private function buildPrompt(array $workoutData): string
    {
        $locationsCount = count($workoutData['locations'] ?? []);
        $distance = $workoutData['distance'] ?? 0;
        $steps = $workoutData['steps'] ?? 0;

        $durationStr = "unknown";
        if (isset($workoutData['start_time']) && isset($workoutData['end_time'])) {
            $start = $workoutData['start_time'];
            $end = $workoutData['end_time'];
            if ($start instanceof \MongoDB\BSON\UTCDateTime && $end instanceof \MongoDB\BSON\UTCDateTime) {
                $durationSec = ($end->toDateTime()->getTimestamp() - $start->toDateTime()->getTimestamp());
                $durationStr = $durationSec . " seconds";
            }
        }

        $prompt = "You are an expert sports data analyst and anti-cheat system for a Move-to-Earn running application.\n";
        $prompt .= "Analyze the following workout data and determine if the runner is cheating (e.g., in a car, spoofing GPS) and calculate realistic metrics.\n";
        $prompt .= "Data:\n";
        $prompt .= "- Distance logged: {$distance} meters\n";
        $prompt .= "- Steps logged: {$steps}\n";
        $prompt .= "- Duration: {$durationStr}\n";
        $prompt .= "- Number of GPS points: {$locationsCount}\n";

        $locations = $workoutData['locations'] ?? [];
        if (is_object($locations) && method_exists($locations, 'getArrayCopy')) {
            $locations = $locations->getArrayCopy();
        } else {
            $locations = (array) $locations;
        }

        if ($locationsCount > 0 && $locationsCount <= 100) {
            $prompt .= "- GPS points (sample): " . json_encode($locations) . "\n";
        } else if ($locationsCount > 100) {
            $prompt .= "- GPS points (first 50): " . json_encode(array_slice($locations, 0, 50)) . "\n";
        }

        $prompt .= "\nReturn a JSON response strictly with the following format:\n";
        $prompt .= '{"is_valid": true|false, "confidence": 0-100, "estimated_distance_meters": <number>, "average_speed_kmh": <number>, "anomalies": ["<list of issues>"], "summary": "<brief summary>"}';

        return $prompt;
    }

    private function callOpenAI(string $prompt): array
    {
        $url = 'https://api.openai.com/v1/chat/completions';

        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a strict data analysis API. Always respond in valid JSON format.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => ['type' => 'json_object']
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return [
                'success' => false,
                'error' => 'OpenAI API request failed. HTTP Code: ' . $httpCode
            ];
        }

        $result = json_decode($response, true);
        $content = $result['choices'][0]['message']['content'] ?? '{}';

        return [
            'success' => true,
            'provider' => 'openai',
            'analysis' => json_decode($content, true)
        ];
    }

    private function callGemini(string $prompt): array
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $this->apiKey;

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return [
                'success' => false,
                'error' => 'Gemini API request failed. HTTP Code: ' . $httpCode
            ];
        }

        $result = json_decode($response, true);
        $content = $result['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

        return [
            'success' => true,
            'provider' => 'gemini',
            'analysis' => json_decode($content, true)
        ];
    }
}
