<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApkDownloadController extends Controller
{
    private const ASSETS = [
        'arm64' => [
            'filename' => 'AT-Player-arm64-v8a-release.apk',
            'label'    => 'AT Player (64-bit Phones — arm64-v8a)',
            'url'      => 'https://github.com/akashtyagi534-tech/Dtlive/releases/download/at-player-latest/AT-Player-arm64-v8a-release.apk',
        ],
        'armeabi' => [
            'filename' => 'AT-Player-armeabi-v7a-release.apk',
            'label'    => 'AT Player (32-bit Phones & TV — armeabi-v7a)',
            'url'      => 'https://github.com/akashtyagi534-tech/Dtlive/releases/download/at-player-latest/AT-Player-armeabi-v7a-release.apk',
        ],
    ];

    public function page()
    {
        $arm64Url = url('/download/arm64');
        $armeabiUrl = url('/download/armeabi');
        $ghReleaseUrl = 'https://github.com/akashtyagi534-tech/Dtlive/releases/tag/at-player-latest';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Download AT Player APK</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: #0b0d12;
      color: #f4f6fa;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .card {
      width: 100%;
      max-width: 460px;
      background: #161922;
      border: 1px solid #262b38;
      border-radius: 18px;
      padding: 24px;
      box-shadow: 0 18px 40px rgba(0,0,0,0.45);
    }
    .badge {
      display: inline-block;
      background: rgba(186, 250, 52, 0.14);
      color: #bafa34;
      border: 1px solid rgba(186, 250, 52, 0.4);
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 12px;
    }
    h1 {
      margin: 0 0 8px;
      font-size: 24px;
      font-weight: 800;
    }
    p.sub {
      margin: 0 0 20px;
      color: #a4abbd;
      font-size: 14px;
      line-height: 1.5;
    }
    .btn {
      display: block;
      width: 100%;
      text-decoration: none;
      text-align: left;
      padding: 15px 16px;
      border-radius: 12px;
      margin-bottom: 12px;
      font-weight: 700;
    }
    .btn-primary {
      background: #bafa34;
      color: #0b0d12;
    }
    .btn-secondary {
      background: #202533;
      color: #ffffff;
      border: 1px solid #32394c;
    }
    .btn .title {
      display: block;
      font-size: 15.5px;
    }
    .btn .meta {
      display: block;
      font-size: 12px;
      opacity: 0.78;
      margin-top: 3px;
      font-weight: 500;
    }
    .note {
      margin-top: 14px;
      padding: 12px;
      background: #11141c;
      border-radius: 10px;
      font-size: 12.5px;
      color: #9ba3b6;
      line-height: 1.5;
    }
    .note a { color: #bafa34; }
  </style>
</head>
<body>
  <div class="card">
    <span class="badge">DIRECT APK INSTALL</span>
    <h1>AT Player</h1>
    <p class="sub">Direct Android APK download karein — kisi ZIP file ko extract karne ki zarurat nahi hai.</p>

    <a class="btn btn-primary" href="{$arm64Url}" download="AT-Player-arm64-v8a-release.apk">
      <span class="title">Download APK (64-bit Phones - Recommended)</span>
      <span class="meta">AT-Player-arm64-v8a-release.apk - ~130 MB</span>
    </a>

    <a class="btn btn-secondary" href="{$armeabiUrl}" download="AT-Player-armeabi-v7a-release.apk">
      <span class="title">Download APK (32-bit Phones &amp; Android TV)</span>
      <span class="meta">AT-Player-armeabi-v7a-release.apk - ~164 MB</span>
    </a>

    <div class="note">
      <strong>Tip:</strong> Download ke baad file par tap karke seedha Install karein. Agar browser puche toh <em>Download anyway</em> aur <em>Allow from this source</em> on karein.<br><br>
      Backup link: <a href="{$ghReleaseUrl}" target="_blank" rel="noopener">GitHub Release Page</a>
    </div>
  </div>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function stream(Request $request, string $arch)
    {
        if (!isset(self::ASSETS[$arch])) {
            abort(404, 'Unknown APK architecture');
        }

        $asset = self::ASSETS[$arch];
        $filename = $asset['filename'];
        $sourceUrl = $asset['url'];
        $rangeHeader = $request->header('Range');

        $reqHeaders = ['User-Agent: ATPlayerDownloader/1.0'];
        if (!empty($rangeHeader)) {
            $reqHeaders[] = 'Range: ' . $rangeHeader;
        }

        // Probe metadata & final CDN URL first so Symfony gets exact headers before streaming
        $probeHeaders = [];
        $headCh = curl_init($sourceUrl);
        curl_setopt_array($headCh, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 8,
            CURLOPT_HTTPHEADER     => $reqHeaders,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADERFUNCTION => function ($curl, $line) use (&$probeHeaders) {
                if (preg_match('/^HTTP\//i', $line)) {
                    $probeHeaders = [];
                } else {
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $probeHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                    }
                }
                return strlen($line);
            },
        ]);
        curl_exec($headCh);
        $statusCode = (int) curl_getinfo($headCh, CURLINFO_HTTP_CODE);
        $effectiveUrl = (string) curl_getinfo($headCh, CURLINFO_EFFECTIVE_URL);
        curl_close($headCh);

        if ($statusCode < 200 || $statusCode >= 400 || empty($effectiveUrl)) {
            return redirect()->away($sourceUrl);
        }

        $respHeaders = [
            'Content-Type'           => 'application/vnd.android.package-archive',
            'Content-Disposition'    => 'attachment; filename="' . $filename . '"',
            'Accept-Ranges'          => 'bytes',
            'Cache-Control'          => 'no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if (!empty($probeHeaders['content-length'])) {
            $respHeaders['Content-Length'] = $probeHeaders['content-length'];
        }
        if (!empty($probeHeaders['content-range'])) {
            $respHeaders['Content-Range'] = $probeHeaders['content-range'];
        }

        if ($request->isMethod('HEAD')) {
            return response('', $statusCode, $respHeaders);
        }

        return response()->stream(function () use ($effectiveUrl, $reqHeaders) {
            @set_time_limit(0);
            @ini_set('zlib.output_compression', '0');
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            $ch = curl_init($effectiveUrl);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 4,
                CURLOPT_HTTPHEADER     => $reqHeaders,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_WRITEFUNCTION  => function ($curl, $chunk) {
                    if (connection_aborted()) {
                        return 0;
                    }
                    echo $chunk;
                    flush();
                    return strlen($chunk);
                },
            ]);
            curl_exec($ch);
            curl_close($ch);
        }, $statusCode, $respHeaders);
    }
}
