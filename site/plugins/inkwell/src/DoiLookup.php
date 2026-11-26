<?php

namespace Inkwell;

/**
 * Fetches paper metadata for a DOI from free, keyless scholarly APIs:
 *
 *  1. doi.org content negotiation (CSL-JSON) — works for Crossref and
 *     DataCite DOIs (incl. arXiv's 10.48550 DOIs): title, authors, venue,
 *     volume, pages, date, licence, and often the abstract.
 *  2. arXiv API (best effort) — finds a matching preprint and, when the
 *     publisher's abstract isn't openly licensed, its CC0 abstract.
 *
 * Abstracts are only returned when they may be republished: CC BY / CC BY-SA /
 * CC0 publisher abstracts, DataCite metadata (CC0) or arXiv metadata (CC0).
 * Only fixed hosts are contacted; the DOI is validated before use.
 */
class DoiLookup
{
    protected const UA       = 'Inkwell-DOI-Lookup/1.0';
    protected const TIMEOUT  = 6;
    protected const OPEN_LICENSES = [
        'creativecommons.org/licenses/by/'    => 'CC BY',
        'creativecommons.org/licenses/by-sa/' => 'CC BY-SA',
        'creativecommons.org/publicdomain/zero/' => 'CC0',
    ];

    /**
     * Accepts "10.x/y", "doi:10.x/y", "https://doi.org/10.x/y" etc.
     * Returns the bare DOI, or null if it isn't one.
     */
    public static function normalize(string|null $input): string|null
    {
        $doi = trim(rawurldecode((string)$input));
        $doi = preg_replace('#^(?:https?://)?(?:dx\.)?doi\.org/#i', '', $doi);
        $doi = preg_replace('#^doi:\s*#i', '', $doi);
        $doi = rtrim($doi, " .,;");

        return preg_match('#^10\.\d{4,9}/[^\s"<>]+$#', $doi) === 1 ? $doi : null;
    }

    /**
     * @throws \RuntimeException with a user-friendly message
     */
    public function lookup(string $doi): array
    {
        $path = implode('/', array_map('rawurlencode', explode('/', $doi)));
        [$status, $body, $finalUrl] = $this->get('https://doi.org/' . $path, ['Accept: application/vnd.citationstyles.csl+json']);

        if ($status === 404) {
            throw new \RuntimeException('We couldn’t find that DOI. Please check it and try again.');
        }
        if ($status !== 200 || ($csl = json_decode($body, true)) === null) {
            throw new \RuntimeException('The DOI service didn’t respond properly. Please try again in a moment.');
        }

        $isDataCite = str_contains((string)parse_url($finalUrl, PHP_URL_HOST), 'crosscite') || str_contains((string)parse_url($finalUrl, PHP_URL_HOST), 'datacite');
        $title      = $this->text($csl['title'] ?? '');
        $authors    = $this->authors($csl['author'] ?? []);
        $date       = $this->date($csl['issued'] ?? $csl['published'] ?? null);
        $venue      = $this->text($csl['container-title'] ?? '') ?: ($isDataCite ? $this->text($csl['publisher'] ?? '') : '');
        $year       = substr($date, 0, 4);

        // arXiv: from the DOI itself, or by matching the title on arXiv
        $arxiv = null;
        if (preg_match('#^10\.48550/arxiv\.(\d{4}\.\d{4,5})#i', $doi, $m)) {
            $arxiv = ['id' => $m[1], 'abstract' => null];
        } elseif ($title !== '' && $authors !== []) {
            $arxiv = $this->findPreprint($title, $authors[0], $doi);
        }

        // arXiv DOIs only carry the year; the arXiv ID encodes year + month (YYMM)
        if ($isDataCite && $arxiv && str_ends_with($date, '-01-01') && preg_match('/^(\d{2})(\d{2})\./', $arxiv['id'], $ym)) {
            $date = sprintf('20%s-%s-01', $ym[1], $ym[2]);
        }

        // abstract — only when it may be republished
        $abstract = '';
        $source   = '';
        $license  = $this->openLicense($csl['license'] ?? []);

        if (!empty($csl['abstract']) && ($license !== null || $isDataCite)) {
            $abstract = $this->cleanAbstract($csl['abstract']);
            $source   = $isDataCite
                ? 'arXiv via DataCite metadata (CC0)'
                : trim($venue . ($year ? " ({$year})" : '')) . ', licensed under ' . $license . ' 4.0';
        } elseif (!empty($arxiv['abstract'])) {
            $abstract = $arxiv['abstract'];
            $source   = 'arXiv preprint ' . $arxiv['id'] . ' (arXiv metadata, CC0). The published version may differ slightly.';
        }

        $keywords = array_slice(array_values(array_filter(array_map(
            fn ($k) => trim(preg_replace('/\s*\([^)]*\)\s*$/', '', $k)),
            (array)($csl['subject'] ?? (isset($csl['keyword']) ? explode(',', $csl['keyword']) : []))
        ))), 0, 5);

        return [
            'doi'            => $doi,
            'title'          => $title,
            'authors'        => $authors,
            'date'           => $date,
            'venue'          => $venue,
            'volume'         => (string)($csl['volume'] ?? ''),
            'pages'          => (string)($csl['page'] ?? ''),
            'arxiv'          => $arxiv['id'] ?? '',
            'abstract'       => $abstract,
            'abstractSource' => $source,
            'abstractOpen'   => $abstract !== '',
            'keywords'       => $keywords,
            'registry'       => $isDataCite ? 'DataCite' : 'Crossref',
        ];
    }

    /**
     * Looks for the arXiv preprint of a paper. Only accepts a candidate that
     * lists the same DOI or has a near-identical title — never a guess.
     */
    protected function findPreprint(string $title, string $firstAuthor, string $doi): array|null
    {
        $norm   = fn ($s) => trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]/', ' ', strtolower($s))));
        $words  = array_slice(array_values(array_filter(explode(' ', $norm($title)), fn ($w) => strlen($w) > 3)), 0, 6);
        $family = array_slice(explode(' ', $norm($firstAuthor)), -1)[0] ?? '';

        if ($words === [] || $family === '') {
            return null;
        }

        $query = implode(' AND ', array_merge(array_map(fn ($w) => 'ti:' . $w, $words), ['au:' . $family]));
        [$status, $body] = $this->get('https://export.arxiv.org/api/query?' . http_build_query(['search_query' => $query, 'max_results' => 5]));

        if ($status !== 200 || ($xml = @simplexml_load_string($body)) === false) {
            return null; // best effort only
        }

        foreach ($xml->entry as $entry) {
            $candidate = trim(preg_replace('/\s+/', ' ', (string)$entry->title));
            $entryDoi  = strtolower((string)($entry->children('http://arxiv.org/schemas/atom')->doi ?? ''));
            similar_text($norm($candidate), $norm($title), $percent);

            if ($entryDoi === strtolower($doi) || $percent >= 92) {
                preg_match('#abs/(\d{4}\.\d{4,5})#', (string)$entry->id, $m);
                return [
                    'id'       => $m[1] ?? null,
                    'abstract' => trim(preg_replace('/\s+/', ' ', (string)$entry->summary)),
                ];
            }
        }

        return null;
    }

    protected function openLicense(array $licenses): string|null
    {
        foreach ($licenses as $license) {
            foreach (static::OPEN_LICENSES as $needle => $name) {
                if (str_contains(strtolower($license['URL'] ?? ''), $needle)) {
                    return $name;
                }
            }
        }
        return null;
    }

    protected function authors(array $list): array
    {
        $names = [];
        foreach ($list as $a) {
            $name = trim(($a['given'] ?? '') . ' ' . ($a['family'] ?? '')) ?: ($a['literal'] ?? $a['name'] ?? '');
            if (($name = $this->text($name)) !== '') {
                $names[] = $name;
            }
        }
        return $names;
    }

    protected function date(array|null $issued): string
    {
        $parts = $issued['date-parts'][0] ?? [];
        if (empty($parts[0])) {
            return '';
        }
        return sprintf('%04d-%02d-%02d', $parts[0], $parts[1] ?? 1, $parts[2] ?? 1);
    }

    protected function text(mixed $value): string
    {
        $value = is_array($value) ? ($value[0] ?? '') : (string)$value;
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5)));
    }

    /**
     * JATS/HTML → plain text. Citation markers are superscript numbers
     * (<jats:sup>2–6</jats:sup>) that only make sense inside the article, so
     * they're removed. Powers of ten and signed exponents are kept as "10^6"
     * / "s^−1" — a bare number after other words (e.g. "AlphaFold 2 ¹") is a
     * citation, not an exponent.
     */
    protected function cleanAbstract(string $abstract): string
    {
        $abstract = preg_replace('#<jats:title>.*?</jats:title>#s', '', $abstract);
        $sup      = fn ($inner) => '\s*<(?:jats:)?sup>\s*' . $inner . '\s*</(?:jats:)?sup>';
        $abstract = preg_replace('#\b(10)' . $sup('([−-]?\d+)') . '#u', '$1^$2', $abstract);      // 10^6, 10^−3
        $abstract = preg_replace('#' . $sup('[\d,–\s]+') . '#u', '', $abstract);               // citations 1 / 2–6 / 1,2
        $abstract = preg_replace('#' . $sup('(.*?)') . '#us', '^$1', $abstract);                 // anything else, e.g. s^−1
        $abstract = preg_replace('#</(jats:)?p>#', "\n\n", $abstract);
        $abstract = html_entity_decode(strip_tags($abstract), ENT_QUOTES | ENT_HTML5);
        $abstract = preg_replace('/^\s*Abstract\s*/i', '', $abstract);

        $paragraphs = array_filter(array_map(
            fn ($p) => trim(str_replace([' ,', ' .', ' ;', ' :', '( ', ' )'], [',', '.', ';', ':', '(', ')'], preg_replace('/\s+/', ' ', $p))),
            explode("\n\n", $abstract)
        ));
        return implode("\n\n", $paragraphs);
    }

    /**
     * @return array{0: int, 1: string, 2: string} status, body, final URL
     */
    protected function get(string $url, array $headers = []): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_MAXREDIRS       => 5,
            CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT         => static::TIMEOUT,
            CURLOPT_USERAGENT       => static::UA,
            CURLOPT_HTTPHEADER      => $headers,
        ]);

        $body = curl_exec($curl);

        if ($body === false) {
            throw new \RuntimeException('The DOI service didn’t respond in time. Please try again in a moment.');
        }

        return [curl_getinfo($curl, CURLINFO_HTTP_CODE), (string)$body, (string)curl_getinfo($curl, CURLINFO_EFFECTIVE_URL)];
    }
}
