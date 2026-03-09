<?php

declare(strict_types=1);

namespace Core\Plug\Business\GoogleMyBusiness;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Deletable;
use Core\Plug\Response;

/**
 * Google My Business local post deletion.
 */
class Delete implements Deletable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://mybusiness.googleapis.com/v4';

    /**
     * Delete a local post.
     *
     * @param  string  $id  Full post resource name (e.g., accounts/xxx/locations/xxx/localPosts/xxx)
     */
    public function delete(string $id): Response
    {
        $accessToken = $this->accessToken();
        if (! $accessToken) {
            return $this->error('Access token is required');
        }

        $response = $this->http()
            ->withToken($accessToken)
            ->delete(self::API_URL."/{$id}");

        // GMB returns 200 with empty body on success
        if ($response->successful()) {
            return $this->ok([
                'deleted' => true,
                'id' => $id,
            ]);
        }

        return $this->fromHttp($response, fn ($data) => [
            'deleted' => false,
            'error' => $data['error']['message'] ?? 'Failed to delete post',
        ]);
    }
}
