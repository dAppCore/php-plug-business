<?php

declare(strict_types=1);

namespace Core\Plug\Business\GoogleMyBusiness;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Listable;
use Core\Plug\Response;

/**
 * Google My Business locations listing.
 */
class Locations implements Listable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const BUSINESS_API_URL = 'https://mybusinessbusinessinformation.googleapis.com/v1';

    private ?string $accountName = null;

    /**
     * Set the account to list locations for.
     */
    public function forAccount(string $accountName): self
    {
        $this->accountName = $accountName;

        return $this;
    }

    /**
     * List business locations.
     */
    public function listEntities(): Response
    {
        $accessToken = $this->accessToken();
        if (! $accessToken) {
            return $this->error('Access token is required');
        }

        if (! $this->accountName) {
            return $this->error('Account name is required');
        }

        $response = $this->http()
            ->withToken($accessToken)
            ->get(self::BUSINESS_API_URL."/{$this->accountName}/locations", [
                'readMask' => 'name,title,storefrontAddress,metadata,phoneNumbers,websiteUri',
            ]);

        return $this->fromHttp($response, function ($data) {
            return [
                'locations' => array_map(fn ($location) => [
                    'id' => $location['name'] ?? '',
                    'title' => $location['title'] ?? '',
                    'address' => $this->formatAddress($location['storefrontAddress'] ?? []),
                    'place_id' => $location['metadata']['placeId'] ?? '',
                    'maps_url' => $location['metadata']['mapsUri'] ?? '',
                    'phone' => $location['phoneNumbers']['primaryPhone'] ?? null,
                    'website' => $location['websiteUri'] ?? null,
                ], $data['locations'] ?? []),
            ];
        });
    }

    /**
     * Get a specific location.
     */
    public function get(string $locationName): Response
    {
        $accessToken = $this->accessToken();
        if (! $accessToken) {
            return $this->error('Access token is required');
        }

        $response = $this->http()
            ->withToken($accessToken)
            ->get(self::BUSINESS_API_URL."/{$locationName}", [
                'readMask' => 'name,title,storefrontAddress,metadata,phoneNumbers,websiteUri,regularHours,specialHours',
            ]);

        return $this->fromHttp($response, function ($data) {
            return [
                'id' => $data['name'] ?? '',
                'title' => $data['title'] ?? '',
                'address' => $this->formatAddress($data['storefrontAddress'] ?? []),
                'place_id' => $data['metadata']['placeId'] ?? '',
                'maps_url' => $data['metadata']['mapsUri'] ?? '',
                'phone' => $data['phoneNumbers']['primaryPhone'] ?? null,
                'website' => $data['websiteUri'] ?? null,
                'regular_hours' => $data['regularHours'] ?? null,
                'special_hours' => $data['specialHours'] ?? null,
            ];
        });
    }

    /**
     * Format address array to string.
     */
    private function formatAddress(array $address): string
    {
        $parts = array_filter([
            $address['addressLines'][0] ?? '',
            $address['locality'] ?? '',
            $address['administrativeArea'] ?? '',
            $address['postalCode'] ?? '',
            $address['regionCode'] ?? '',
        ]);

        return implode(', ', $parts);
    }
}
