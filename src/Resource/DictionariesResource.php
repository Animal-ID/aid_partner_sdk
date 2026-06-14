<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Resource;

use AnimalId\PartnerSdk\Model\DictionarySet;

/**
 * GET /v1/partner/dictionaries — reference values (species, sex, countries, ...).
 */
final class DictionariesResource extends AbstractResource
{
    const PATH = '/v1/partner/dictionaries';

    /**
     * Fetches dictionaries. Pass the ETag from a previous call to get a cheap
     * 304 answer when nothing changed (the result reports isNotModified()).
     *
     * @param list<string>|null $include Dictionary keys to return; null/[] => all.
     *                                   Keys: species, sex, sizes, lost_statuses,
     *                                   other_identifiers, procedure_types,
     *                                   countries, languages, cites.
     * @param string|null       $query   Filter entries by localized name.
     * @param string|null       $lang    Project names to a single locale (uk, en, ru, de, es).
     * @param string|null       $etag    Value of getEtag() from a cached DictionarySet.
     */
    public function all(
        ?array $include = null,
        ?string $query = null,
        ?string $lang = null,
        ?string $etag = null
    ): DictionarySet {
        $headers = [];
        if ($etag !== null && $etag !== '') {
            $headers['If-None-Match'] = $etag;
        }

        $response = $this->api->get(self::PATH, [
            'include' => $include !== null && $include !== [] ? implode(',', $include) : null,
            'q' => $query,
            'lang' => $lang,
        ], $headers);

        if ($response->getStatusCode() === 304) {
            $responseEtag = $response->getHeader('etag');

            return DictionarySet::notModified($responseEtag !== null ? $responseEtag : $etag);
        }

        return DictionarySet::fromResponse($response->json());
    }
}
