<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\ComputedFields\ProviderPeople;

use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;

/**
 * The clinical provider lens's "more resources" destination (UAMS-Web/uams-statamic-archive#1692): the
 * `clinical_resources` archive, pre-filtered to this provider.
 *
 * UAMS-Web/uams-statamic-archive#1684 shipped the lens's related-resources section with NO "more" CTA, because
 * the destination did not exist - the archive could only be narrowed by resource
 * type, so the link would have landed the reader on an unfiltered list of every
 * clinical resource on the site. `ClinicalResourcesForProvider` supplies the
 * filter; this supplies the URL.
 *
 * The archive root is read from the collection's MOUNT rather than hard-coded as
 * `/health/resources`, so moving or renaming the landing page cannot leave the
 * profile pointing at a 404. An unmounted or unpublished landing yields '', and
 * the template renders no CTA - the same "no link beats a broken link" call UAMS-Web/uams-statamic-archive#1684
 * made.
 */
final readonly class ProviderResourcesArchiveUrlResolver
{
    /**
     * Handle of the collection the archive lists.
     */
    private const string RESOURCES_COLLECTION = 'clinical_resources';

    /**
     * Query parameter the archive's provider filter reads.
     */
    private const string PROVIDER_PARAM = 'provider';

    /**
     * The filtered archive URL for one provider, or '' when there is nowhere to
     * link.
     */
    public function resolve(Entry $entry): string
    {
        $slug = $entry->slug();

        if (! \is_string($slug) || $slug === '') {
            return '';
        }

        $root = $this->archiveRootUrl();

        if ($root === '') {
            return '';
        }

        return \sprintf('%s?%s=%s', $root, self::PROVIDER_PARAM, rawurlencode($slug));
    }

    /**
     * The URL of the resources collection's mount landing, or '' when unmounted
     * or when that landing is not itself published.
     *
     * An unpublished mount would 302 the reader onto a 404, so it is treated as
     * absent - the same precedence `OrphanedAncestorResolver::mountRootUrl()`
     * applies.
     */
    private function archiveRootUrl(): string
    {
        $collection = Collection::findByHandle(self::RESOURCES_COLLECTION);

        if (! $collection instanceof CollectionContract) {
            return '';
        }

        $mount = $collection->mount();

        if (! $mount instanceof Entry || $mount->status() !== 'published') {
            return '';
        }

        $url = $mount->url();

        return \is_string($url) ? $url : '';
    }
}
