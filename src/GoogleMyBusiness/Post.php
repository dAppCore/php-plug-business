<?php

declare(strict_types=1);

namespace Core\Plug\Business\GoogleMyBusiness;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Postable;
use Core\Plug\Response;
use Illuminate\Support\Collection;

/**
 * Google My Business local post publishing.
 */
class Post implements Postable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://mybusiness.googleapis.com/v4';

    /**
     * Publish a local post to GMB.
     *
     * @param  string  $text  Post summary text
     * @param  Collection  $media  Media items (photos only, no video support)
     * @param  array  $params  location (required), post_type, cta_type, cta_url, event_*, coupon_code, etc.
     */
    public function publish(string $text, Collection $media, array $params = []): Response
    {
        $accessToken = $this->accessToken();
        if (! $accessToken) {
            return $this->error('Access token is required');
        }

        $location = $params['location'] ?? '';
        if (! $location) {
            return $this->error('Location is required for Google My Business posts');
        }

        $postType = $params['post_type'] ?? 'STANDARD';
        $payload = $this->buildPayload($text, $media, $postType, $params);

        $response = $this->http()
            ->withToken($accessToken)
            ->post(self::API_URL."/{$location}/localPosts", $payload);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['name'] ?? '',
            'state' => $data['state'] ?? 'LIVE',
            'url' => $data['searchUrl'] ?? null,
        ]);
    }

    /**
     * Build post payload based on type.
     */
    private function buildPayload(string $text, Collection $media, string $postType, array $params): array
    {
        $payload = [
            'languageCode' => $params['language'] ?? 'en',
            'summary' => $text,
            'topicType' => $postType,
        ];

        // Add media (GMB only supports photos)
        if ($media->isNotEmpty()) {
            $mediaItem = $media->first();
            $url = is_array($mediaItem) ? ($mediaItem['url'] ?? '') : $mediaItem;

            if ($url) {
                $payload['media'] = [
                    'mediaFormat' => 'PHOTO',
                    'sourceUrl' => $url,
                ];
            }
        }

        // Call to action
        if (! empty($params['cta_type']) && ! empty($params['cta_url'])) {
            $payload['callToAction'] = [
                'actionType' => $params['cta_type'], // BOOK, ORDER, SHOP, LEARN_MORE, SIGN_UP, CALL
                'url' => $params['cta_url'],
            ];
        }

        // Event details
        if ($postType === 'EVENT' && ! empty($params['event_title'])) {
            $payload['event'] = [
                'title' => $params['event_title'],
                'schedule' => [],
            ];

            if (! empty($params['event_start'])) {
                $payload['event']['schedule']['startDate'] = $this->formatDate($params['event_start']);
                $payload['event']['schedule']['startTime'] = $this->formatTime($params['event_start']);
            }

            if (! empty($params['event_end'])) {
                $payload['event']['schedule']['endDate'] = $this->formatDate($params['event_end']);
                $payload['event']['schedule']['endTime'] = $this->formatTime($params['event_end']);
            }
        }

        // Offer details
        if ($postType === 'OFFER') {
            $payload['offer'] = [];

            if (! empty($params['coupon_code'])) {
                $payload['offer']['couponCode'] = $params['coupon_code'];
            }

            if (! empty($params['redeem_url'])) {
                $payload['offer']['redeemOnlineUrl'] = $params['redeem_url'];
            }

            if (! empty($params['terms'])) {
                $payload['offer']['termsConditions'] = $params['terms'];
            }
        }

        return $payload;
    }

    /**
     * Format datetime to GMB date array.
     */
    private function formatDate(string $datetime): array
    {
        $date = new \DateTime($datetime);

        return [
            'year' => (int) $date->format('Y'),
            'month' => (int) $date->format('m'),
            'day' => (int) $date->format('d'),
        ];
    }

    /**
     * Format datetime to GMB time array.
     */
    private function formatTime(string $datetime): array
    {
        $date = new \DateTime($datetime);

        return [
            'hours' => (int) $date->format('H'),
            'minutes' => (int) $date->format('i'),
            'seconds' => 0,
            'nanos' => 0,
        ];
    }

    /**
     * GMB posts don't have public URLs.
     */
    public static function externalPostUrl(string $locationName, string $postId): string
    {
        return '';
    }
}
