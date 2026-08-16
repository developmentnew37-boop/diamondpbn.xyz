<?php

namespace App\Jobs;


use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignArticle;
use App\Models\Admin\CampaignPost;
use App\Models\Admin\CampaignDomain;
use App\Models\Admin\Article;
use App\Services\CampaignPostContentBuilder;
use Throwable;

class PublishCampaignPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $campaignPostId)
    {
        $this->onQueue('campaigns');
    }

    public function handle(): void
    {
        $lockTtlSec  = 180; // 3 minutes
        $maxAttempts = 5;
        $baseBackoff = 60;

        $lockToken = (string) Str::uuid();

        // 🔐 STEP 1: Claim the campaign_post safely
        $post = DB::transaction(function () use ($lockToken, $lockTtlSec) {

            $p = CampaignPost::query()
                ->with('campaign')
                ->lockForUpdate()
                ->find($this->campaignPostId);

            if (!$p) return null;

            if (in_array($p->status, ['success', 'failed'], true)) return null;
            if (in_array($p->campaign->status, ['paused', 'cancelled'], true)) return null;

            if ($p->next_retry_at && $p->next_retry_at->isFuture()) return null;

            if ($p->locked_at && $p->locked_at->gt(now()->subSeconds($lockTtlSec))) {
                return null;
            }

            $p->status     = 'publishing';
            $p->locked_at  = now();
            $p->lock_token = $lockToken;
            $p->save();

            return $p;
        });

        if (!$post) return;

        // Load everything needed
        $post->load([
            'campaign',
            'campaignDomain.domain',
            'campaignArticle.article',
        ]);



        try {
            // 🧠 STEP 2: Build content
            [$title, $content] = CampaignPostContentBuilder::build($post);

            // 🌐 STEP 3: Send to WordPress
            $remote = $this->postToWordPress($post, $title, $content);

            // ✅ STEP 4: Mark success
            DB::transaction(function () use ($post, $remote) {

                // Lock campaign post
                $fresh = CampaignPost::lockForUpdate()->find($post->id);
                if (!$fresh || $fresh->lock_token !== $post->lock_token) {
                    return;
                }

                // Update post status
                $fresh->update([
                    'status'        => 'success',
                    'remote_id'     => $remote['post_id'] ?? null,
                    'remote_title'  => $post->campaignArticle->article->name,
                    'remote_url'    => $remote['remote_url'] ?? null,
                    'published_at'  => now(),
                    'last_error'    => null,
                    'next_retry_at' => null,
                    'locked_at'     => null,
                    'lock_token'    => null,
                ]);

                // 🔒 LOCK campaign row FIRST
                $campaign = Campaign::lockForUpdate()->find($fresh->campaign_id);
                if (!$campaign) {
                    return;
                }

                // ➕ Increment locally
                $campaign->completed_targets++;

                // ✅ Check completion
                if ($campaign->completed_targets >= $campaign->total_targets) {
                    $campaign->status = 'completed';
                }

                if ($campaign->completed_targets + $campaign->failed_targets > $campaign->total_targets) {
                    $campaign->failed_targets--;
                }

                // 💾 Save once
                $campaign->save();

                // 🗑️ Remove article
                Article::find($fresh->campaignArticle->article_id)?->delete();
            });
        } catch (Throwable $e) {

            // ❌ STEP 5: Retry or fail
            DB::transaction(function () use ($post, $e, $maxAttempts, $baseBackoff) {

                $fresh = CampaignPost::lockForUpdate()->find($post->id);
                if (!$fresh || $fresh->lock_token !== $post->lock_token) return;

                $fresh->attempt_count++;
                $fresh->last_error = $e->getMessage();

                if ($fresh->attempt_count < $maxAttempts) {

                    $fresh->status = 'queued';

                    $delay = (int) ($baseBackoff * (2 ** ($fresh->attempt_count - 1)));
                    $delay = min($delay, 3600);

                    $fresh->next_retry_at = now()->addSeconds($delay);
                    $fresh->locked_at  = null;
                    $fresh->lock_token = null;
                    $fresh->save();

                    // ✅ IMPORTANT: re-dispatch job with delay
                    PublishCampaignPostJob::dispatch($fresh->id)
                        ->onQueue('campaigns')
                        ->delay(now()->addSeconds($delay));
                } else {

                    $fresh->status = 'failed';
                    $fresh->next_retry_at = null;
                    $fresh->locked_at  = null;
                    $fresh->lock_token = null;
                    $fresh->save();
                    // first mark post as failed, then increment failed_targets in campaign
                    $campaign = Campaign::lockForUpdate()->find($fresh->campaign_id);

                    if (!$campaign) return;

                    $currentTotal = $campaign->completed_targets + $campaign->failed_targets; //

                    // Only increment if it will not exceed total_targets
                    if ($currentTotal < $campaign->total_targets) {

                        $campaign->failed_targets++;

                        // Optional status update
                        if ($campaign->completed_targets > 0) {
                            $campaign->status = 'semi_failed';
                        } else {
                            $campaign->status = 'failed';
                        }

                        $campaign->save();
                    }
                }
            });
        } finally {
            $this->finalizeCampaignIfDone($post->campaign_id);
        }
    }


    // /**
    //  * 🔗 Build title & content with STRICT sequential keyword+url
    //  */
    // private function buildContent(CampaignPost $post): array
    // {
    //     $article = $post->campaignArticle->article;

    //     if (!$article) {
    //         throw new \Exception("Article not found. campaign_post_id={$post->id}");
    //     }

    //     $title = trim((string) $article->name);
    //     $html  = trim((string) $article->description);

    //     if ($title === '' || $html === '') {
    //         throw new \Exception("Article missing content");
    //     }

    //     // --------------------------------------------------
    //     // 1) Collect keyword + url pairs (SEQUENTIAL)
    //     // --------------------------------------------------
    //     $ca = $post->campaignArticle;

    //     $keywords = $ca->keyword_type === 'json'
    //         ? json_decode($ca->keyword, true)
    //         : [$ca->keyword];

    //     $urls = $ca->url_type === 'json'
    //         ? json_decode($ca->url, true)
    //         : [$ca->url];

    //     if (!is_array($keywords) || !is_array($urls)) {
    //         throw new \Exception("Invalid keyword/url format");
    //     }

    //     // normalize + pair sequentially
    //     $pairs = [];
    //     $max = min(count($keywords), count($urls));

    //     for ($i = 0; $i < $max; $i++) {
    //         $kw  = trim((string) $keywords[$i]);
    //         $url = trim((string) $urls[$i]);

    //         if ($kw !== '' && $url !== '') {
    //             $pairs[] = [$kw, $url];
    //         }
    //     }

    //     if (count($pairs) === 0) {
    //         throw new \Exception("No valid keyword/url pairs");
    //     }

    //     // --------------------------------------------------
    //     // 2) Split content into paragraphs
    //     // --------------------------------------------------
    //     preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $html, $matches);

    //     $paragraphs = $matches[0];

    //     // Fallback: no <p> tags → wrap entire content
    //     if (count($paragraphs) === 0) {
    //         $paragraphs = ['<p>' . $html . '</p>'];
    //     }

    //     $paraCount   = count($paragraphs);
    //     $anchorCount = count($pairs);

    //     // --------------------------------------------------
    //     // 3) Distribute anchors across paragraphs
    //     // --------------------------------------------------
    //     // Example: 5 anchors, 3 paras → 2 | 2 | 1
    //     $base      = intdiv($anchorCount, $paraCount);
    //     $remainder = $anchorCount % $paraCount;

    //     $pairIndex = 0;
    //     $nofollow  = (bool) $ca->nofollow;
    //     $relAttr   = $nofollow ? 'nofollow noopener' : 'noopener';

    //     for ($p = 0; $p < $paraCount && $pairIndex < $anchorCount; $p++) {

    //         $insertCount = $base + ($p < $remainder ? 1 : 0);
    //         if ($insertCount <= 0) continue;

    //         $paraHtml = $paragraphs[$p];

    //         // strip <p> wrapper for clean insertion
    //         $inner = preg_replace('/^<p\b[^>]*>|<\/p>$/i', '', $paraHtml);

    //         // avoid inserting at very beginning
    //         $offset = max(20, (int) (strlen($inner) * 0.4));

    //         for ($k = 0; $k < $insertCount && $pairIndex < $anchorCount; $k++) {

    //             [$kw, $url] = $pairs[$pairIndex++];

    //             $anchor = '<a href="' . e($url) . '" target="_blank" rel="' . $relAttr . '">' . e($kw) . '</a>';

    //             // insert anchor at a natural position
    //             $inner = substr($inner, 0, $offset)
    //                 . ' ' . $anchor . ' '
    //                 . substr($inner, $offset);

    //             // move offset forward for next anchor
    //             $offset += strlen($anchor) + 30;
    //         }

    //         // re-wrap paragraph
    //         $paragraphs[$p] = '<p>' . $inner . '</p>';
    //     }

    //     // --------------------------------------------------
    //     // 4) Rebuild HTML
    //     // --------------------------------------------------
    //     $html = implode("\n", $paragraphs);

    //     return [$title, $html];
    // }

    /**
     * 🌐 Send article to WordPress
     */
    // private function postToWordPress(CampaignPost $post, string $title, string $content): array
    // {
    //     $domain = $post->campaignDomain->domain->name;

    //     $endpoint = rtrim($domain, '/') . '/wp-json/external/v1/posts/create';

    //     $res = Http::withoutVerifying()
    //         ->timeout(180)->post($endpoint, [
    //             'title'     => $title,
    //             'content'   => $content,
    //             'status'    => 'publish',
    //             'post_type' => 'post',
    //             'api_key' => $post->campaignDomain->domain->api_key
    //         ]);

    //     if (!$res->successful()) {
    //         throw new \Exception("WP API failed: " . $res->body());
    //     }

    //     return $res->json();
    // }


    private function buildContent(CampaignPost $post): array
    {
        $article = $post->campaignArticle->article;

        if (!$article) {
            throw new \Exception("Article not found. campaign_post_id={$post->id}");
        }

        $title = trim((string) $article->name);
        $html  = trim((string) $article->description);

        if ($title === '' || $html === '') {
            throw new \Exception("Article missing content");
        }

        // --------------------------------------------------
        // 1) Collect keyword + url pairs (SEQUENTIAL)
        // --------------------------------------------------
        $ca = $post->campaignArticle;

        $keywords = $ca->keyword_type === 'json'
            ? json_decode($ca->keyword, true)
            : [$ca->keyword];

        $urls = $ca->url_type === 'json'
            ? json_decode($ca->url, true)
            : [$ca->url];

        if (!is_array($keywords) || !is_array($urls)) {
            throw new \Exception("Invalid keyword/url format");
        }

        // normalize + pair sequentially
        $pairs = [];
        $max = min(count($keywords), count($urls));

        for ($i = 0; $i < $max; $i++) {
            $kw  = trim((string) ($keywords[$i] ?? ''));
            $url = trim((string) ($urls[$i] ?? ''));

            if ($kw !== '' && $url !== '') {
                $pairs[] = [$kw, $url];
            }
        }

        if (count($pairs) === 0) {
            throw new \Exception("No valid keyword/url pairs");
        }

        // --------------------------------------------------
        // 2) Split content into paragraphs
        // --------------------------------------------------
        preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $html, $matches);
        $paragraphs = $matches[0] ?? [];

        // Fallback: no <p> tags → wrap entire content
        if (count($paragraphs) === 0) {
            $paragraphs = ['<p>' . $html . '</p>'];
        }

        $paraCount   = count($paragraphs);
        $anchorCount = count($pairs);

        // --------------------------------------------------
        // 3) Distribute anchors across paragraphs
        // Example: 5 anchors, 3 paras → 2 | 2 | 1
        // --------------------------------------------------
        $base      = intdiv($anchorCount, $paraCount);
        $remainder = $anchorCount % $paraCount;

        $pairIndex = 0;
        $nofollow  = (bool) ($ca->nofollow ?? false);
        $relAttr   = $nofollow ? 'nofollow noopener' : 'noopener';

        for ($p = 0; $p < $paraCount && $pairIndex < $anchorCount; $p++) {

            $insertCount = $base + ($p < $remainder ? 1 : 0);
            if ($insertCount <= 0) continue;

            $paraHtml = $paragraphs[$p];

            // Keep the original <p ...> opening tag if present
            preg_match('/^<p\b[^>]*>/i', $paraHtml, $openTagMatch);
            $openTag = $openTagMatch[0] ?? '<p>';

            // Strip <p> wrapper for inner HTML
            $inner = preg_replace('/^<p\b[^>]*>|<\/p>$/i', '', $paraHtml);

            // If paragraph is too short in real text, skip it (try next paragraphs)
            // $plainLen = mb_strlen(trim(strip_tags($inner)));
            $plainLen = mb_strlen(trim(strip_tags($inner))); // new
            // if ($plainLen < 40 && $paraCount > 1) {
            //     // Try next paragraph, DO NOT consume anchors here
            //     continue;
            // }

            // Start around 40% into the paragraph (not at start)
            // $target = (int) max(20, floor(strlen($inner) * 0.40));

            $target = (int) max(20, floor(strlen($inner) * 0.20)); // new one

            for ($k = 0; $k < $insertCount && $pairIndex < $anchorCount; $k++) {

                [$kw, $url] = $pairs[$pairIndex++];

                $anchor = '<a href="' . e($url) . '" target="_blank" rel="' . $relAttr . '">' . e($kw) . '</a>';

                // 🔥 Find a SAFE insertion point in HTML (not inside tag, not inside word)
                $safePos = $this->findSafeHtmlInsertPos($inner, $target);

                // Insert with spaces around it
                $inner = substr($inner, 0, $safePos)
                    . ' ' . $anchor . ' '
                    . substr($inner, $safePos);

                // Move forward for next insertion in the same paragraph
                $target = $safePos + strlen($anchor) + 40;
            }

            $paragraphs[$p] = $openTag . $inner . '</p>';
        }


        // --------------------------------------------------
        // code for Handling short paragraph and Anchor skipping thing
        // --------------------------------------------------

        // 🚑 SAFETY NET — force insert remaining anchors
        if ($pairIndex < $anchorCount) {

            // Use the longest paragraph as fallback
            usort($paragraphs, function ($a, $b) {
                return mb_strlen(strip_tags($b)) <=> mb_strlen(strip_tags($a));
            });

            $fallback = $paragraphs[0];

            preg_match('/^<p\b[^>]*>/i', $fallback, $openTagMatch);
            $openTag = $openTagMatch[0] ?? '<p>';

            $inner = preg_replace('/^<p\b[^>]*>|<\/p>$/i', '', $fallback);

            while ($pairIndex < $anchorCount) {
                [$kw, $url] = $pairs[$pairIndex++];

                $anchor = '<a href="' . e($url) . '" target="_blank" rel="' . $relAttr . '">' . e($kw) . '</a>';

                $inner .= ' ' . $anchor;
            }

            $paragraphs[0] = $openTag . $inner . '</p>';
        }





        // --------------------------------------------------
        // 4) Rebuild HTML
        // --------------------------------------------------
        $html = implode("\n", $paragraphs);

        return [$title, $html];
    }

    /**
     * Finds a safe insertion index inside an HTML string:
     * - NOT inside a tag: <...>
     * - NOT in the middle of a word (must land on boundary: space/punct)
     */
    // private function findSafeHtmlInsertPos(string $html, int $start): int
    // {
    //     $len = strlen($html);
    //     if ($len === 0) return 0;

    //     $start = max(0, min($start, $len));

    //     // boundary chars where insertion is "safe"
    //     $isBoundary = function ($ch) {
    //         return $ch === ' ' || $ch === "\n" || $ch === "\t"
    //             || $ch === '.' || $ch === ',' || $ch === ';' || $ch === ':'
    //             || $ch === '!' || $ch === '?' || $ch === ')' || $ch === '(';
    //     };

    //     // helper: check if index is inside an HTML tag
    //     $insideTagAt = function (int $pos) use ($html) {
    //         $before = substr($html, 0, $pos);
    //         $lastLt = strrpos($before, '<');
    //         if ($lastLt === false) return false;

    //         $lastGt = strrpos($before, '>');
    //         // if last '<' comes after last '>', we're inside a tag
    //         return $lastGt === false || $lastLt > $lastGt;
    //     };

    //     // scan outward from start to find nearest safe boundary
    //     for ($d = 0; $d < 200; $d++) {
    //         $right = $start + $d;
    //         if ($right < $len && !$insideTagAt($right)) {
    //             // $ch = $html[$right];
    //             $ch = substr($html, $right, 1);
    //             if ($isBoundary($ch)) {
    //                 // insert AFTER boundary (so we don't split punctuation)
    //                 return min($right + 1, $len);
    //             }
    //         }

    //         $left = $start - $d;
    //         if ($left > 0 && !$insideTagAt($left)) {
    //             // $ch = $html[$left];
    //             $ch = substr($html, $left, 1);
    //             if ($isBoundary($ch)) {
    //                 return min($left + 1, $len);
    //             }
    //         }
    //     }

    //     // fallback: if nothing found, append near end (but not inside tag)
    //     $pos = min($start, $len);
    //     while ($pos < $len && $insideTagAt($pos)) $pos++;
    //     return min($pos, $len);
    // }

    private function findSafeHtmlInsertPos(string $html, int $start): int
    {
        $len = strlen($html);
        if ($len === 0) return 0;

        $start = max(0, min($start, $len));

        // boundary chars where insertion is safe
        $isBoundary = function ($ch) {
            return $ch === ' ' || $ch === "\n" || $ch === "\t"
                || $ch === '.' || $ch === ',' || $ch === ';' || $ch === ':'
                || $ch === '!' || $ch === '?' || $ch === ')' || $ch === '(';
        };

        // check if position is inside an HTML tag
        $insideTagAt = function (int $pos) use ($html) {
            $before = substr($html, 0, $pos);
            $lastLt = strrpos($before, '<');
            if ($lastLt === false) return false;

            $lastGt = strrpos($before, '>');
            return $lastGt === false || $lastLt > $lastGt;
        };

        // scan outward from start
        for ($d = 0; $d < 200; $d++) {

            $right = $start + $d;
            if ($right < $len && !$insideTagAt($right)) {
                $ch = substr($html, $right, 1); // ✅ SAFE
                if ($ch !== '' && $isBoundary($ch)) {
                    return min($right + 1, $len);
                }
            }

            $left = $start - $d;
            if ($left > 0 && !$insideTagAt($left)) {
                $ch = substr($html, $left, 1); // ✅ SAFE
                if ($ch !== '' && $isBoundary($ch)) {
                    return min($left + 1, $len);
                }
            }
        }

        // fallback: append safely near end
        $pos = min($start, $len);
        while ($pos < $len && $insideTagAt($pos)) {
            $pos++;
        }

        return min($pos, $len);
    }


    private function postToWordPress(CampaignPost $post, string $title, string $content): array
    {
        $domain = trim((string) $post->campaignDomain->domain->name);

        // ✅ Ensure scheme
        if (!preg_match('~^https?://~i', $domain)) {
            $domain = 'https://' . $domain;
        }

        $endpoint = rtrim($domain, '/') . '/wp-json/external/v1/posts/create';

        $payload = [
            'title'     => $title,
            'content'   => $content,
            'status'    => 'publish',
            'post_type' => 'post',
            'is_sticky' => $post->is_sticky,   // ✅ ADD THIS LINE
            'api_key'   => (string) $post->campaignDomain->domain->api_key,
        ];

        $res = Http::withoutVerifying() // keep if you must for bad SSL domains
            ->timeout(180)
            ->acceptJson()
            ->asJson()
            ->post($endpoint, $payload);

        if (!$res->successful()) {
            throw new \Exception("WP API failed ({$res->status()}): " . $res->body());
        }

        $json = $res->json();
        if (!is_array($json)) {
            throw new \Exception("WP API returned non-JSON response: " . $res->body());
        }

        return $json;
    }


    /**
     * 🏁 Finalize campaign if all posts processed
     */
    private function finalizeCampaignIfDone(int $campaignId): void
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) return;

        $totalDone = $campaign->completed_targets + $campaign->failed_targets;

        if ($totalDone < $campaign->total_targets) return;

        $campaign->finished_at = now();

        if ($campaign->failed_targets === 0) {
            $campaign->status = 'completed';
        } elseif ($campaign->completed_targets > 0) {
            $campaign->status = 'semi_failed';
        } else {
            $campaign->status = 'failed';
        }

        $campaign->save();
    }
}
