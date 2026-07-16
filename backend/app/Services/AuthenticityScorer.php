<?php

namespace App\Services;

class AuthenticityScorer
{
    /**
     * Heuristic authenticity score in [0.0, 1.0].
     *
     * Rules (kept intentionally simple / explainable):
     * - Start at 0.55 (mildly authentic by default).
     * - Natural-length body (80–400 chars): +0.25.
     * - Very short (< 20 chars): -0.20; excessively long (> 800): -0.10.
     * - More than 3 hashtags: -0.15 (spam / engagement-bait signal).
     * - High emoji density (> 8% of characters): -0.15.
     * - image_url with ≥ 4 query params: -0.20 (heavy CDN/filter transforms).
     */
    public function score(string $text, ?string $imageUrl = null): float
    {
        $score = 0.55;
        $length = mb_strlen($text);

        if ($length >= 80 && $length <= 400) {
            $score += 0.25;
        } elseif ($length < 20) {
            $score -= 0.20;
        } elseif ($length > 800) {
            $score -= 0.10;
        }

        preg_match_all('/#\w+/u', $text, $hashtags);
        if (count($hashtags[0]) > 3) {
            $score -= 0.15;
        }

        preg_match_all('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $text, $emojis);
        $emojiCount = count($emojis[0]);
        if ($length > 0 && ($emojiCount / $length) > 0.08) {
            $score -= 0.15;
        }

        if ($imageUrl !== null && $imageUrl !== '') {
            $query = parse_url($imageUrl, PHP_URL_QUERY);
            if (is_string($query) && $query !== '') {
                parse_str($query, $params);
                if (count($params) >= 4) {
                    $score -= 0.20;
                }
            }
        }

        return round(max(0.0, min(1.0, $score)), 4);
    }
}
