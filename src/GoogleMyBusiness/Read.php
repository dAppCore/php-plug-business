<?php

declare(strict_types=1);

namespace Core\Plug\Business\GoogleMyBusiness;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Readable;
use Core\Plug\Response;

/**
 * Google My Business account and location reading.
 */
class Read implements Readable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://mybusiness.googleapis.com/v4';

    private const BUSINESS_API_URL = 'https://mybusinessbusinessinformation.googleapis.com/v1';

    /**
     * Get a specific local post.
     *
     * @param  string  $id  Full post resource name
     */
    public function get(string $id): Response
    {
        $accessToken = $this->accessToken();
        if (! $accessToken) {
            return $this->error('Access token is required');
        }

        $response = $this->http()
            ->withToken($accessToken)
            ->get(self::API_URL."/{$id}");

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['name'] ?? '',
            'summary' => $data['summary'] ?? '',
            'state' => $data['state'] ?? '',
            'topic_type' => $data['topicType'] ?? '',
            'create_time' => $data['createTime'] ?? null,
            'update_time' => $data['updateTime'] ?? null,
            'media' => $data['media'] ?? null,
            'call_to_action' => $data['callToAction'] ?? null,
        ]);
    }

    /**
     * Get current account info.
     */
    public function me(): Response
    {
        $accessToken = $this->accessToken();
        if (! $accessToken) {
            return $this->error('Access token is required');
        }

        $response = $this->http()
            ->withToken($accessToken)
            ->get(self::API_URL.'/accounts');

        return $this->fromHttp($response, function ($data) {
            $accounts = $data['accounts'] ?? [];

            if (empty($accounts)) {
                return [
                    'id' => null,
                    'name' => null,
                    'error' => 'No business accounts found',
                ];
            }

            // Return first account as primary
            $account = $accounts[0];

            return [
                'id' => $account['name'] ?? '',
                'name' => $account['accountName'] ?? 'Google Business Profile',
                'type' => $account['type'] ?? '',
                'role' => $account['role'] ?? '',
                'state' => $account['state']['status'] ?? '',
            ];
        });
    }

    /**
     * List accounts.
     */
    public function list(array $params = []): Response
    {
        $accessToken = $this->accessToken();
        if (! $accessToken) {
            return $this->error('Access token is required');
        }

        $response = $this->http()
            ->withToken($accessToken)
            ->get(self::API_URL.'/accounts');

        return $this->fromHttp($response, function ($data) {
            return [
                'accounts' => array_map(fn ($account) => [
                    'id' => $account['name'] ?? '',
                    'name' => $account['accountName'] ?? '',
                    'type' => $account['type'] ?? '',
                    'role' => $account['role'] ?? '',
                ], $data['accounts'] ?? []),
            ];
        });
    }

    /**
     * List local posts for a location.
     */
    public function posts(string $locationName, array $params = []): Response
    {
        $accessToken = $this->accessToken();
        if (! $accessToken) {
            return $this->error('Access token is required');
        }

        $response = $this->http()
            ->withToken($accessToken)
            ->get(self::API_URL."/{$locationName}/localPosts", [
                'pageSize' => $params['per_page'] ?? 10,
                'pageToken' => $params['page_token'] ?? null,
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'posts' => array_map(fn ($post) => [
                'id' => $post['name'] ?? '',
                'summary' => $post['summary'] ?? '',
                'state' => $post['state'] ?? '',
                'topic_type' => $post['topicType'] ?? '',
                'create_time' => $post['createTime'] ?? null,
            ], $data['localPosts'] ?? []),
            'next_page_token' => $data['nextPageToken'] ?? null,
        ]);
    }
}
