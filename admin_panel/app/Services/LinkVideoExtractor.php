<?php

namespace App\Services;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Channel;
use App\Models\Common;
use App\Models\Home_Section;
use App\Models\Language;
use App\Models\Shorts;
use App\Models\Type;
use App\Models\Video;
use Illuminate\Support\Facades\DB;

class LinkVideoExtractor
{
    public Common $common;

    public function __construct()
    {
        $this->common = new Common();
    }

    /**
     * Split single or multi-line input into clean HTTP(S) URLs.
     */
    public function parseUrls(string $rawInput): array
    {
        $rawInput = str_replace(["\r\n", "\r", ","], "\n", $rawInput);
        $parts = preg_split('/[\s\n]+/', trim($rawInput)) ?: [];
        $urls = [];
        $seen = [];

        foreach ($parts as $part) {
            $u = trim($part, " \t\n\r\0\x0B\"'<>");
            if ($u === '') {
                continue;
            }
            if (!preg_match('#^https?://#i', $u)) {
                if (preg_match('#^[a-z0-9.-]+\.[a-z]{2,}(/.*)?$#i', $u)) {
                    $u = 'https://' . $u;
                } else {
                    continue;
                }
            }
            if (filter_var($u, FILTER_VALIDATE_URL) && !isset($seen[$u])) {
                $seen[$u] = true;
                $urls[] = $u;
            }
        }

        return $urls;
    }

    /**
     * Resolve metadata + playable stream from any supported link.
     */
    public function resolveLink(string $url): array
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        // 1. YouTube & YouTube Shorts
        if (preg_match('#(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})#i', $url, $m)) {
            $ytId = $m[1];
            $canonical = 'https://www.youtube.com/watch?v=' . $ytId;
            $title = 'YouTube Video (' . $ytId . ')';
            $thumb = 'https://img.youtube.com/vi/' . $ytId . '/hqdefault.jpg';

            $oembed = $this->httpGet('https://www.youtube.com/oembed?url=' . urlencode($canonical) . '&format=json', 8);
            if ($oembed['ok'] && $oembed['body']) {
                $json = json_decode($oembed['body'], true);
                if (is_array($json)) {
                    if (!empty($json['title'])) {
                        $title = trim((string) $json['title']);
                    }
                    if (!empty($json['thumbnail_url'])) {
                        $thumb = (string) $json['thumbnail_url'];
                    }
                }
            }

            return [
                'success'           => true,
                'source_url'        => $url,
                'video_upload_type' => 'youtube',
                'video_url'         => $canonical,
                'trailer_type'      => 'youtube',
                'trailer_url'       => $canonical,
                'video_extension'   => 'mp4',
                'title'             => $this->cleanTitle($title),
                'description'       => 'Imported from YouTube: ' . $url,
                'thumbnail_url'     => $thumb,
                'is_short_form'     => str_contains(strtolower($url), '/shorts/'),
            ];
        }

        // 2. Vimeo
        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#i', $url, $m)) {
            $vimeoId = $m[1];
            $canonical = 'https://vimeo.com/' . $vimeoId;
            $title = 'Vimeo Video (' . $vimeoId . ')';
            $thumb = '';

            $oembed = $this->httpGet('https://vimeo.com/api/oembed.json?url=' . urlencode($canonical), 8);
            if ($oembed['ok'] && $oembed['body']) {
                $json = json_decode($oembed['body'], true);
                if (is_array($json)) {
                    if (!empty($json['title'])) {
                        $title = trim((string) $json['title']);
                    }
                    if (!empty($json['thumbnail_url'])) {
                        $thumb = (string) $json['thumbnail_url'];
                    }
                }
            }

            return [
                'success'           => true,
                'source_url'        => $url,
                'video_upload_type' => 'vimeo',
                'video_url'         => $canonical,
                'trailer_type'      => 'vimeo',
                'trailer_url'       => $canonical,
                'video_extension'   => 'mp4',
                'title'             => $this->cleanTitle($title),
                'description'       => 'Imported from Vimeo: ' . $url,
                'thumbnail_url'     => $thumb,
                'is_short_form'     => false,
            ];
        }

        // 3. Direct MP4 / M3U8 / WebM / MOV / MKV link
        $pathOnly = strtolower((string) parse_url($url, PHP_URL_PATH));
        if (preg_match('#\.(mp4|m3u8|webm|mov|mkv|mpd)$#i', $pathOnly, $extMatch)) {
            $baseName = pathinfo($pathOnly, PATHINFO_FILENAME);
            $title = $baseName ? ucwords(str_replace(['-', '_'], ' ', $baseName)) : 'Direct Stream Video';
            return [
                'success'           => true,
                'source_url'        => $url,
                'video_upload_type' => 'external',
                'video_url'         => $url,
                'trailer_type'      => 'external',
                'trailer_url'       => $url,
                'video_extension'   => strtolower($extMatch[1]),
                'title'             => $this->cleanTitle($title),
                'description'       => 'Stream link: ' . $url,
                'thumbnail_url'     => '',
                'is_short_form'     => false,
            ];
        }

        // 4. Webpage / Social / Moj / ShareChat / Reels / General OG extractor
        $resp = $this->httpGet($url, 20);
        if (!$resp['ok']) {
            return [
                'success' => false,
                'error'   => 'Failed to open link (' . ($resp['error'] ?: 'HTTP ' . $resp['status']) . ')',
            ];
        }

        $contentType = strtolower((string) ($resp['content_type'] ?? ''));
        $finalUrl = $resp['effective_url'] ?: $url;

        // If server redirected straight to a video stream
        if (str_starts_with($contentType, 'video/') || str_contains($contentType, 'mpegurl')) {
            return [
                'success'           => true,
                'source_url'        => $url,
                'video_upload_type' => 'external',
                'video_url'         => $finalUrl,
                'trailer_type'      => 'external',
                'trailer_url'       => $finalUrl,
                'video_extension'   => str_contains($contentType, 'mpegurl') ? 'm3u8' : 'mp4',
                'title'             => 'Imported Stream Video',
                'description'       => 'Stream link: ' . $url,
                'thumbnail_url'     => '',
                'is_short_form'     => false,
            ];
        }

        $html = (string) $resp['body'];
        $idHint = '';
        if (preg_match('#/(?:video|post|reel|p)/([A-Za-z0-9_-]{5,})#i', $finalUrl, $idMatch)) {
            $idHint = $idMatch[1];
        }

        $videoUrl = $this->extractVideoUrlFromHtml($html, $idHint);
        if ($videoUrl === '') {
            return [
                'success' => false,
                'error'   => 'Could not extract a direct playable video URL from this link. Try a Moj, YouTube, or direct MP4/M3U8 link.',
            ];
        }

        $title = $this->extractMeta($html, ['og:title', 'twitter:title'])
            ?: $this->extractHtmlTitle($html)
            ?: ('Video ' . ($idHint ?: date('M d H:i')));

        $thumb = $this->extractMeta($html, ['og:image:secure_url', 'og:image', 'twitter:image', 'thumbnailUrl']) ?: '';
        $desc  = $this->extractMeta($html, ['og:description', 'description', 'twitter:description']) ?: ('Imported from ' . (parse_url($finalUrl, PHP_URL_HOST) ?: 'link'));

        $lowerHost = strtolower((string) parse_url($finalUrl, PHP_URL_HOST));
        $isShort = str_contains($lowerHost, 'mojapp') || str_contains($lowerHost, 'sharechat') || str_contains(strtolower($finalUrl), 'reel') || str_contains(strtolower($finalUrl), 'short');

        $ext = 'mp4';
        $vPath = strtolower((string) parse_url($videoUrl, PHP_URL_PATH));
        if (preg_match('#\.(mp4|m3u8|webm|mov|mkv)$#i', $vPath, $em)) {
            $ext = strtolower($em[1]);
        }

        return [
            'success'           => true,
            'source_url'        => $url,
            'video_upload_type' => 'external',
            'video_url'         => $videoUrl,
            'trailer_type'      => 'external',
            'trailer_url'       => $videoUrl,
            'video_extension'   => $ext,
            'title'             => $this->cleanTitle($title),
            'description'       => $this->cleanTitle($desc, 400),
            'thumbnail_url'     => $thumb,
            'is_short_form'     => $isShort,
        ];
    }

    /**
     * Resolve URL, download thumbnail, insert into tbl_video (+ tbl_shorts if short),
     * and ensure home feed shows latest added links at the top.
     */
    public function importLinkToLibrary(string $url, ?string $customTitle = null, ?int $categoryId = null, bool $alsoShorts = true): array
    {
        $resolved = $this->resolveLink($url);
        if (empty($resolved['success'])) {
            return $resolved;
        }

        $title = trim((string) ($customTitle ?? ''));
        if ($title === '') {
            $title = $resolved['title'];
        }

        // Ensure Types (Movies = 1, Shorts = 8)
        $moviesType = Type::where('type', 1)->where('status', 1)->first();
        $moviesTypeId = $moviesType ? (int) $moviesType->id : 1;

        $shortsType = Type::where('type', 8)->where('status', 1)->first();
        $shortsTypeId = $shortsType ? (int) $shortsType->id : 0;

        // Category fallback
        if (!$categoryId || $categoryId <= 0) {
            $cat = Category::where('status', 1)->orderBy('sort_order', 'asc')->first();
            $categoryId = $cat ? (int) $cat->id : 1;
        }

        // Language fallback
        $lang = Language::where('status', 1)->orderBy('sort_order', 'asc')->first();
        $languageId = $lang ? (string) $lang->id : '1';

        // Channel fallback
        $channel = Channel::where('status', 1)->first();
        $channelId = $channel ? (int) $channel->id : 0;

        // Save thumbnail locally into storage/app/public/content/
        $thumbFile = $this->downloadThumbnailToContent($resolved['thumbnail_url'] ?? '');
        $landscapeFile = $thumbFile;

        $videoUrl = $resolved['video_url'];

        // Avoid exact duplicate of same video stream URL; update & bump if exists
        $existing = Video::where('video_320', $videoUrl)->first();
        if ($existing) {
            $existing->name = $title;
            if ($thumbFile !== 'action_poster.jpg') {
                $existing->thumbnail = $thumbFile;
                $existing->landscape = $landscapeFile;
            }
            $existing->status = 1;
            $existing->is_premium = 0;
            $existing->is_rent = 0;
            $existing->save();
            $video = $existing;
        } else {
            $video = new Video();
            $video->type_id               = $moviesTypeId;
            $video->video_type            = 1;
            $video->channel_id            = $channelId;
            $video->producer_id           = 0;
            $video->category_id           = (string) $categoryId;
            $video->language_id           = $languageId;
            $video->cast_id               = '';
            $video->name                  = $title;
            $video->storage_type          = 1;
            $video->thumbnail             = $thumbFile;
            $video->landscape             = $landscapeFile;
            $video->description           = $resolved['description'] ?? '';
            $video->video_storage_type    = 1;
            $video->video_upload_type     = $resolved['video_upload_type'];
            $video->video_320             = $videoUrl;
            $video->video_480             = $videoUrl;
            $video->video_720             = $videoUrl;
            $video->video_1080            = $videoUrl;
            $video->video_extension       = $resolved['video_extension'] ?? 'mp4';
            $video->video_duration        = 60000;
            $video->trailer_storage_type  = 1;
            $video->trailer_type          = $resolved['trailer_type'];
            $video->trailer_url           = $resolved['trailer_url'];
            $video->subtitle_storage_type = 1;
            $video->subtitle_type         = 'external';
            $video->subtitle_lang_1       = '';
            $video->subtitle_1            = '';
            $video->subtitle_lang_2       = '';
            $video->subtitle_2            = '';
            $video->subtitle_lang_3       = '';
            $video->subtitle_3            = '';
            $video->release_date          = date('Y-m-d');
            $video->is_premium            = 0;
            $video->is_title              = 1;
            $video->is_download           = 0;
            $video->is_comment            = 1;
            $video->is_like               = 1;
            $video->is_rent               = 0;
            $video->price                 = 0;
            $video->rent_day              = 0;
            $video->avg_rating            = 4.8;
            $video->total_review          = 1;
            $video->total_view            = 1;
            $video->total_like            = 1;
            $video->status                = 1;
            $video->save();
        }

        // Also mirror into tbl_shorts if it's a direct MP4 short-form video (Moj / Reels / ShareChat)
        if ($alsoShorts && ($resolved['is_short_form'] ?? false) && $resolved['video_upload_type'] === 'external') {
            $existingShort = Shorts::where('trailer_url', $videoUrl)->first();
            if (!$existingShort) {
                $short = new Shorts();
                $short->type_id              = $shortsTypeId;
                $short->video_type           = 8;
                $short->producer_id          = 0;
                $short->category_id          = (string) $categoryId;
                $short->language_id          = $languageId;
                $short->cast_id              = '';
                $short->name                 = $title;
                $short->storage_type         = 1;
                $short->thumbnail            = $thumbFile;
                $short->trailer_storage_type = 1;
                $short->trailer_type         = 'external';
                $short->trailer_url          = $videoUrl;
                $short->description          = $resolved['description'] ?? '';
                $short->is_title             = 1;
                $short->is_comment           = 1;
                $short->is_like              = 1;
                $short->total_view           = 1;
                $short->total_like           = 1;
                $short->avg_rating           = 4.8;
                $short->total_review         = 1;
                $short->status               = 1;
                $short->save();
            }
        }

        $this->ensureLatestHomeSection($moviesTypeId);

        // Build full response with resolved URLs
        $videoCopy = clone $video;
        $this->common->add_url_to_array(1, [$videoCopy]);

        return [
            'success' => true,
            'video'   => $videoCopy,
        ];
    }

    /**
     * Ensure a dynamic section ordered by latest uploads sits at the top of Home Screen.
     */
    public function ensureLatestHomeSection(int $moviesTypeId): void
    {
        $exists = Home_Section::where('is_home_screen', 1)
            ->where('video_type', 1)
            ->where('section_type', 0)
            ->where('order_by_upload', 2)
            ->first();

        if (!$exists) {
            DB::table('tbl_home_section')->insert([
                'section_type'    => 0,
                'is_home_screen'  => 1,
                'video_type'      => 1,
                'sub_video_type'  => 0,
                'type_id'         => $moviesTypeId,
                'title'           => 'Latest Added Links',
                'short_title'     => 'Recently Imported Videos',
                'screen_layout'   => 'portrait',
                'content_ids'     => '',
                'category_id'     => 0,
                'language_id'     => 0,
                'channel_id'      => 0,
                'order_by_upload' => 2,
                'order_by_view'   => 0,
                'premium_video'   => 0,
                'no_of_content'   => 20,
                'view_all'        => 1,
                'is_title'        => 1,
                'sort_order'      => 0,
                'status'          => 1,
            ]);
        }
    }

    private function httpGet(string $url, int $timeout = 15): array
    {
        if (!function_exists('curl_init')) {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'header'  => "User-Agent: Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36\r\n",
                ],
            ]);
            $body = @file_get_contents($url, false, $ctx);
            return [
                'ok'            => $body !== false,
                'status'        => $body !== false ? 200 : 0,
                'body'          => $body ?: '',
                'content_type'  => '',
                'effective_url' => $url,
                'error'         => $body === false ? 'HTTP request failed' : '',
            ];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 8,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Linux; Android 13; Mobile) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,video/*,*/*;q=0.8',
                'Accept-Language: en-IN,en-US;q=0.9,en;q=0.8',
            ],
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        $status = (int) ($info['http_code'] ?? 0);
        return [
            'ok'            => $body !== false && $status >= 200 && $status < 400,
            'status'        => $status,
            'body'          => is_string($body) ? $body : '',
            'content_type'  => (string) ($info['content_type'] ?? ''),
            'effective_url' => (string) ($info['url'] ?? $url),
            'error'         => $err,
        ];
    }

    private function extractVideoUrlFromHtml(string $html, string $idHint = ''): string
    {
        // 1. Standard OG / Twitter video meta tags
        $metaKeys = ['og:video:secure_url', 'og:video:url', 'og:video', 'twitter:player:stream'];
        foreach ($metaKeys as $key) {
            $val = $this->extractMeta($html, [$key]);
            if ($val !== '' && preg_match('#^https?://#i', $val)) {
                return $this->decodeUrl($val);
            }
        }

        // 2. JSON-LD contentUrl
        if (preg_match('#"contentUrl"\s*:\s*"(https?:[^"]+)"#i', $html, $m)) {
            return $this->decodeUrl($m[1]);
        }

        // 3. Explicit video/source tags
        if (preg_match('#<(?:video|source)[^>]+src=["\'](https?://[^"\']+)["\']#i', $html, $m)) {
            return $this->decodeUrl($m[1]);
        }

        // 4. Common JSON keys in embedded scripts
        $jsonKeys = ['video_url', 'videoUrl', 'playable_url_quality_hd', 'playable_url', 'playback_url', 'hd_src', 'sd_src', 'mp4Url'];
        foreach ($jsonKeys as $jk) {
            if (preg_match('#"' . preg_quote($jk, '#') . '"\s*:\s*"(https?:[^"]+)"#i', $html, $m)) {
                $candidate = $this->decodeUrl($m[1]);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        // 5. Fallback scan for direct .mp4 / .m3u8 links (prefer URL matching idHint)
        if (preg_match_all('#https?:\\?/\\?/[^"\'<>\s\\\\]+\.(?:mp4|m3u8)(?:\?[^"\'<>\s\\\\]*)?#i', $html, $matches)) {
            $candidates = array_map(fn ($u) => $this->decodeUrl($u), $matches[0]);
            if ($idHint !== '') {
                foreach ($candidates as $c) {
                    if (str_contains($c, $idHint)) {
                        return $c;
                    }
                }
            }
            if (!empty($candidates[0])) {
                return $candidates[0];
            }
        }

        return '';
    }

    private function extractMeta(string $html, array $keys): string
    {
        foreach ($keys as $key) {
            $q = preg_quote($key, '#');
            if (preg_match('#<meta[^>]+(?:property|name|itemprop)\s*=\s*["\']' . $q . '["\'][^>]+content\s*=\s*["\']([^"\']*)["\']#i', $html, $m)) {
                return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if (preg_match('#<meta[^>]+content\s*=\s*["\']([^"\']*)["\'][^>]+(?:property|name|itemprop)\s*=\s*["\']' . $q . '["\']#i', $html, $m)) {
                return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        return '';
    }

    private function extractHtmlTitle(string $html): string
    {
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            return html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return '';
    }

    private function decodeUrl(string $url): string
    {
        $url = str_replace(['\/', '\\u0026', '\\u002F'], ['/', '&', '/'], $url);
        return html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function cleanTitle(string $title, int $maxLen = 180): string
    {
        $title = preg_replace('/\s+/', ' ', trim($title)) ?: 'Imported Video';
        if (mb_strlen($title) > $maxLen) {
            $title = mb_substr($title, 0, $maxLen - 3) . '...';
        }
        return $title;
    }

    private function downloadThumbnailToContent(string $thumbUrl): string
    {
        $fallback = 'action_poster.jpg';
        if ($thumbUrl === '' || !preg_match('#^https?://#i', $thumbUrl)) {
            return $fallback;
        }

        try {
            $dir = storage_path('app/public/content/');
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $resp = $this->httpGet($thumbUrl, 10);
            if ($resp['ok'] && strlen((string) $resp['body']) > 500) {
                $ct = strtolower((string) $resp['content_type']);
                $ext = 'jpg';
                if (str_contains($ct, 'png')) {
                    $ext = 'png';
                } elseif (str_contains($ct, 'webp')) {
                    $ext = 'webp';
                }
                $filename = 'link_' . date('Y_m_d_') . uniqid() . '.' . $ext;
                if (@file_put_contents($dir . $filename, $resp['body']) !== false) {
                    return $filename;
                }
            }
        } catch (\Throwable $e) {
            // Fall back cleanly
        }

        return $fallback;
    }
}
