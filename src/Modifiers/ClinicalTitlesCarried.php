<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Modifiers;

use Statamic\Entries\Entry;
use Statamic\Fields\Value;
use Statamic\Modifiers\Modifier;
use UAMSWeb\Aether\People\Support\ListNormalizer;

/**
 * Antlers modifier: the distinct `clinical_specializations` entry ids a list of providers carries.
 *
 * The Find-a-Doc Providers block applies it to its own provider list and hands the result to the
 * filter partial, so the clinical title select offers only titles that return at least one of
 * the block's providers (UAMS-Web/aether-people#119, decided on UAMS-Web/uams-statamic#258).
 *
 * Use:
 *   {{ items | clinical_titles_carried }}
 *
 * Each item may be an entry or an augmented array, and each title an id, an entry or an array
 * carrying an `id`. Anything else is dropped. The ids come back in first-seen order, without repeats.
 */
class ClinicalTitlesCarried extends Modifier
{
    /**
     * The people field holding a provider's clinical specializations.
     */
    private const string FIELD = 'clinical_specializations';

    /**
     * The distinct title ids the providers carry.
     *
     * @param  mixed  $value  A list of provider entries or augmented provider arrays.
     * @param  array<int|string, mixed>  $params  Unused
     * @param  array<string, mixed>  $context  Unused
     * @return list<string> The distinct clinical specialization entry ids.
     */
    public function index($value, array $params, array $context): array
    {
        $ids = [];

        foreach ((new ListNormalizer)->normalize($value) as $provider) {
            foreach ((new ListNormalizer)->normalize($this->titlesOf($provider)) as $title) {
                $id = $this->idOf($title);

                if ($id !== null) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * The clinical specializations value a provider carries, unwrapped.
     *
     * @param  mixed  $provider  An entry or an augmented array.
     */
    private function titlesOf(mixed $provider): mixed
    {
        $titles = null;

        if ($provider instanceof Entry) {
            $titles = $provider->get(self::FIELD);
        } elseif (\is_array($provider)) {
            $titles = $provider[self::FIELD] ?? null;
        }

        // A field value that is still wrapped gives up its stored value, which is what holds the ids
        return $titles instanceof Value ? $titles->raw() : $titles;
    }

    /**
     * The id of one title, or null when it names none.
     *
     * @param  mixed  $title  An id, an entry or an array carrying an `id`.
     */
    private function idOf(mixed $title): ?string
    {
        if ($title instanceof Entry) {
            $title = $title->id();
        } elseif (\is_array($title)) {
            $title = $title['id'] ?? null;
        }

        if (\is_int($title) || (\is_string($title) && $title !== '')) {
            return (string) $title;
        }

        return null;
    }
}
