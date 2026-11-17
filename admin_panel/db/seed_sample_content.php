<?php

/**
 * Idempotent OTT sample content seeder & asset syncer for DTLive on Railway.
 * Runs at container startup after auto_init_db.php.
 */

$sampleAssetsDir = __DIR__ . '/sample_assets';
$publicStorageDir = dirname(__DIR__) . '/storage/app/public';

// 1. Always ensure sample media assets exist inside storage/app/public
// (protects against empty mounted volumes shadowing image-baked storage/)
if (is_dir($sampleAssetsDir)) {
    $folders = ['content', 'category', 'cast', 'channel', 'language', 'app'];
    foreach ($folders as $folder) {
        $srcDir = $sampleAssetsDir . '/' . $folder;
        $dstDir = $publicStorageDir . '/' . $folder;
        if (!is_dir($srcDir)) {
            continue;
        }
        if (!is_dir($dstDir)) {
            @mkdir($dstDir, 0775, true);
        }
        foreach (scandir($srcDir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $srcFile = $srcDir . '/' . $file;
            $dstFile = $dstDir . '/' . $file;
            if (is_file($srcFile) && !file_exists($dstFile)) {
                @copy($srcFile, $dstFile);
            }
        }
    }
}

$host = getenv('DB_HOST') ?: '';
$port = (int) (getenv('DB_PORT') ?: 3306);
$db   = getenv('DB_DATABASE') ?: '';
$user = getenv('DB_USERNAME') ?: '';
$pass = getenv('DB_PASSWORD') ?: '';

if ($host === '' || $db === '' || $user === '') {
    exit(0);
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $mysqli = new mysqli($host, $user, $pass, $db, $port);
    $mysqli->set_charset('utf8mb4');

    // Verify core tables exist
    $resTables = $mysqli->query("SHOW TABLES LIKE 'tbl_video'");
    if (!$resTables || $resTables->num_rows === 0) {
        exit(0);
    }

    // Seed only once when no videos exist yet
    $resCount = $mysqli->query('SELECT COUNT(*) AS cnt FROM `tbl_video`');
    $rowCount = $resCount ? $resCount->fetch_assoc() : ['cnt' => 0];
    if ((int) ($rowCount['cnt'] ?? 0) > 0) {
        echo "Sample content already present (" . $rowCount['cnt'] . " videos).\n";
        exit(0);
    }

    $mysqli->begin_transaction();

    // 1. Types: ensure Movies (type=1) exists, plus Shorts (type=8)
    $moviesTypeId = 1;
    $resType = $mysqli->query("SELECT id FROM `tbl_type` WHERE `type` = 1 LIMIT 1");
    if ($resType && $resType->num_rows > 0) {
        $moviesTypeId = (int) $resType->fetch_assoc()['id'];
    } else {
        $mysqli->query("INSERT INTO `tbl_type` (`name`, `type`, `storage_type`, `icon`, `sort_order`, `status`) VALUES ('Movies', 1, 1, '', 1, 1)");
        $moviesTypeId = (int) $mysqli->insert_id;
    }

    $shortsTypeId = 0;
    $resShortsType = $mysqli->query("SELECT id FROM `tbl_type` WHERE `type` = 8 LIMIT 1");
    if ($resShortsType && $resShortsType->num_rows > 0) {
        $shortsTypeId = (int) $resShortsType->fetch_assoc()['id'];
    } else {
        $mysqli->query("INSERT INTO `tbl_type` (`name`, `type`, `storage_type`, `icon`, `sort_order`, `status`) VALUES ('Shorts', 8, 1, '', 2, 1)");
        $shortsTypeId = (int) $mysqli->insert_id;
    }

    // 2. Categories
    $categories = [
        ['Action', 'action.jpg', 1],
        ['Comedy', 'comedy.jpg', 2],
        ['Sci-Fi', 'scifi.jpg', 3],
        ['Thriller & Drama', 'drama.jpg', 4],
        ['Trailers', 'trailers.jpg', 5],
    ];
    $catIds = [];
    $stmtCat = $mysqli->prepare("INSERT INTO `tbl_category` (`name`, `storage_type`, `image`, `sort_order`, `status`) VALUES (?, 1, ?, ?, 1)");
    foreach ($categories as $cat) {
        $stmtCat->bind_param('ssi', $cat[0], $cat[1], $cat[2]);
        $stmtCat->execute();
        $catIds[$cat[0]] = (int) $mysqli->insert_id;
    }
    $stmtCat->close();

    // 3. Languages
    $languages = [
        ['Hindi', 'hindi.jpg', 1],
        ['English', 'english.jpg', 2],
    ];
    $langIds = [];
    $stmtLang = $mysqli->prepare("INSERT INTO `tbl_language` (`name`, `storage_type`, `image`, `sort_order`, `status`) VALUES (?, 1, ?, ?, 1)");
    foreach ($languages as $lang) {
        $stmtLang->bind_param('ssi', $lang[0], $lang[1], $lang[2]);
        $stmtLang->execute();
        $langIds[$lang[0]] = (int) $mysqli->insert_id;
    }
    $stmtLang->close();

    // 4. Cast
    $casts = [
        ['Aarav Malhotra', 'actor_1.jpg', 'Actor', 'Lead action and thriller performer featured in DTLive Originals.'],
        ['Kabir Verma', 'actor_1.jpg', 'Director', 'Filmmaker known for high-octane action, sci-fi, and comedy cinema.'],
    ];
    $castIds = [];
    $stmtCast = $mysqli->prepare("INSERT INTO `tbl_cast` (`name`, `storage_type`, `image`, `type`, `personal_info`, `status`) VALUES (?, 1, ?, ?, ?, 1)");
    foreach ($casts as $cast) {
        $stmtCast->bind_param('ssss', $cast[0], $cast[1], $cast[2], $cast[3]);
        $stmtCast->execute();
        $castIds[] = (int) $mysqli->insert_id;
    }
    $stmtCast->close();
    $castList = implode(',', $castIds);

    // 5. Channels
    $channels = [
        ['DTLive Cinema', 'dtlive_channel.jpg', 'dtlive_channel_wide.jpg'],
        ['Action Zone', 'action_channel.jpg', 'action_channel_wide.jpg'],
        ['Comedy House', 'comedy_channel.jpg', 'comedy_channel_wide.jpg'],
    ];
    $channelIds = [];
    $stmtChan = $mysqli->prepare("INSERT INTO `tbl_channel` (`name`, `storage_type`, `portrait_img`, `landscape_img`, `is_title`, `status`) VALUES (?, 1, ?, ?, 1, 1)");
    foreach ($channels as $chan) {
        $stmtChan->bind_param('sss', $chan[0], $chan[1], $chan[2]);
        $stmtChan->execute();
        $channelIds[$chan[0]] = (int) $mysqli->insert_id;
    }
    $stmtChan->close();

    // 6. Movies & Trailers (tbl_video)
    $allLangs = $langIds['Hindi'] . ',' . $langIds['English'];
    $videos = [
        [
            'name'        => 'Shadow Strike',
            'channel_id'  => $channelIds['Action Zone'],
            'category_id' => $catIds['Action'] . ',' . $catIds['Trailers'],
            'language_id' => $allLangs,
            'thumbnail'   => 'action_poster.jpg',
            'landscape'   => 'action_banner.jpg',
            'description' => 'An elite special-ops agent races against time across neon-lit rooftops to stop a rogue syndicate in this explosive action blockbuster.',
            'video_url'   => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/TearsOfSteel.mp4',
            'trailer_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
            'duration'    => 734000,
            'release'     => '2026-03-15',
            'rating'      => 4.8,
            'views'       => 18450,
            'likes'       => 1420,
        ],
        [
            'name'        => 'Laugh Out Loud',
            'channel_id'  => $channelIds['Comedy House'],
            'category_id' => $catIds['Comedy'] . ',' . $catIds['Trailers'],
            'language_id' => $allLangs,
            'thumbnail'   => 'comedy_poster.jpg',
            'landscape'   => 'comedy_banner.jpg',
            'description' => 'A wild weekend reunion spirals into non-stop slapstick chaos, mistaken identities, and hilarious comedy gold.',
            'video_url'   => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
            'trailer_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerFun.mp4',
            'duration'    => 596000,
            'release'     => '2026-04-02',
            'rating'      => 4.7,
            'views'       => 15290,
            'likes'       => 1290,
        ],
        [
            'name'        => 'Cyber Horizon 2099',
            'channel_id'  => $channelIds['DTLive Cinema'],
            'category_id' => $catIds['Sci-Fi'] . ',' . $catIds['Action'],
            'language_id' => (string) $langIds['English'],
            'thumbnail'   => 'scifi_poster.jpg',
            'landscape'   => 'scifi_banner.jpg',
            'description' => 'In a futuristic orbital metropolis, a lone pilot uncovers an AI conspiracy that threatens humanity.',
            'video_url'   => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/Sintel.mp4',
            'trailer_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4',
            'duration'    => 888000,
            'release'     => '2026-05-10',
            'rating'      => 4.9,
            'views'       => 22410,
            'likes'       => 1980,
        ],
        [
            'name'        => 'Midnight Noir',
            'channel_id'  => $channelIds['DTLive Cinema'],
            'category_id' => $catIds['Thriller & Drama'] . ',' . $catIds['Action'],
            'language_id' => $allLangs,
            'thumbnail'   => 'drama_poster.jpg',
            'landscape'   => 'drama_banner.jpg',
            'description' => 'A rain-soaked crime thriller following a relentless detective unraveling the city’s darkest secrets.',
            'video_url'   => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
            'trailer_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerMeltdowns.mp4',
            'duration'    => 653000,
            'release'     => '2026-02-20',
            'rating'      => 4.6,
            'views'       => 11980,
            'likes'       => 960,
        ],
        [
            'name'        => 'Turbo Chase — Official Trailer',
            'channel_id'  => $channelIds['Action Zone'],
            'category_id' => $catIds['Trailers'] . ',' . $catIds['Action'],
            'language_id' => $allLangs,
            'thumbnail'   => 'action_poster.jpg',
            'landscape'   => 'action_banner.jpg',
            'description' => 'Official high-octane teaser trailer featuring stunt driving, tactical action, and explosive set pieces.',
            'video_url'   => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
            'trailer_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
            'duration'    => 15000,
            'release'     => '2026-06-01',
            'rating'      => 4.5,
            'views'       => 9840,
            'likes'       => 815,
        ],
        [
            'name'        => 'Weekend Chaos — Comedy Trailer',
            'channel_id'  => $channelIds['Comedy House'],
            'category_id' => $catIds['Trailers'] . ',' . $catIds['Comedy'],
            'language_id' => $allLangs,
            'thumbnail'   => 'comedy_poster.jpg',
            'landscape'   => 'comedy_banner.jpg',
            'description' => 'Watch the hilarious official comedy trailer packed with witty banter and unexpected detours.',
            'video_url'   => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerJoy.mp4',
            'trailer_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerJoy.mp4',
            'duration'    => 15000,
            'release'     => '2026-06-12',
            'rating'      => 4.6,
            'views'       => 8670,
            'likes'       => 740,
        ],
    ];

    $videoIds = [];
    $sqlVideo = "INSERT INTO `tbl_video` (
        `type_id`, `video_type`, `channel_id`, `producer_id`, `category_id`, `language_id`, `cast_id`,
        `name`, `storage_type`, `thumbnail`, `landscape`, `description`,
        `video_storage_type`, `video_upload_type`, `video_320`, `video_480`, `video_720`, `video_1080`, `video_extension`, `video_duration`,
        `trailer_storage_type`, `trailer_type`, `trailer_url`,
        `subtitle_storage_type`, `subtitle_type`, `subtitle_lang_1`, `subtitle_1`, `subtitle_lang_2`, `subtitle_2`, `subtitle_lang_3`, `subtitle_3`,
        `release_date`, `is_premium`, `is_title`, `is_download`, `is_comment`, `is_like`, `is_rent`, `price`, `rent_day`,
        `avg_rating`, `total_review`, `total_view`, `total_like`, `status`
    ) VALUES (
        ?, 1, ?, 0, ?, ?, ?,
        ?, 1, ?, ?, ?,
        1, 'external', ?, ?, ?, ?, 'mp4', ?,
        1, 'external', ?,
        1, 'external', '', '', '', '', '', '',
        ?, 0, 1, 0, 1, 1, 0, 0, 0,
        ?, 12, ?, ?, 1
    )";
    $stmtVideo = $mysqli->prepare($sqlVideo);
    foreach ($videos as $v) {
        $stmtVideo->bind_param(
            'iisssssssssssissdii',
            $moviesTypeId,
            $v['channel_id'],
            $v['category_id'],
            $v['language_id'],
            $castList,
            $v['name'],
            $v['thumbnail'],
            $v['landscape'],
            $v['description'],
            $v['video_url'],
            $v['video_url'],
            $v['video_url'],
            $v['video_url'],
            $v['duration'],
            $v['trailer_url'],
            $v['release'],
            $v['rating'],
            $v['views'],
            $v['likes']
        );
        $stmtVideo->execute();
        $videoIds[] = (int) $mysqli->insert_id;
    }
    $stmtVideo->close();

    // 7. Shorts / Quick Trailers (tbl_shorts)
    $shorts = [
        [
            'name'        => 'Action Stunt Reel',
            'category_id' => $catIds['Action'] . ',' . $catIds['Trailers'],
            'thumbnail'   => 'action_poster.jpg',
            'url'         => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
            'desc'        => 'Quick high-energy action trailer clip.',
            'views'       => 6420,
            'likes'       => 540,
        ],
        [
            'name'        => 'Comedy Laugh Clip',
            'category_id' => $catIds['Comedy'] . ',' . $catIds['Trailers'],
            'thumbnail'   => 'comedy_poster.jpg',
            'url'         => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerFun.mp4',
            'desc'        => '60-second comedy highlight reel.',
            'views'       => 5310,
            'likes'       => 480,
        ],
        [
            'name'        => 'Sci-Fi Future Teaser',
            'category_id' => $catIds['Sci-Fi'] . ',' . $catIds['Trailers'],
            'thumbnail'   => 'scifi_poster.jpg',
            'url'         => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4',
            'desc'        => 'First look teaser from Cyber Horizon 2099.',
            'views'       => 7890,
            'likes'       => 690,
        ],
    ];
    $stmtShort = $mysqli->prepare("INSERT INTO `tbl_shorts` (`type_id`, `video_type`, `producer_id`, `category_id`, `language_id`, `cast_id`, `name`, `storage_type`, `thumbnail`, `trailer_storage_type`, `trailer_type`, `trailer_url`, `description`, `is_title`, `is_comment`, `is_like`, `total_view`, `total_like`, `avg_rating`, `total_review`, `status`) VALUES (?, 8, 0, ?, ?, ?, ?, 1, ?, 1, 'external', ?, ?, 1, 1, 1, ?, ?, 4.7, 5, 1)");
    foreach ($shorts as $s) {
        $stmtShort->bind_param(
            'isssssssii',
            $shortsTypeId,
            $s['category_id'],
            $allLangs,
            $castList,
            $s['name'],
            $s['thumbnail'],
            $s['url'],
            $s['desc'],
            $s['views'],
            $s['likes']
        );
        $stmtShort->execute();
    }
    $stmtShort->close();

    // 8. Hero Banners (Home + Movies tab)
    $stmtBanner = $mysqli->prepare("INSERT INTO `tbl_banner` (`is_home_screen`, `type_id`, `video_type`, `subvideo_type`, `video_id`, `sort_order`, `status`) VALUES (?, ?, 1, 0, ?, ?, 1)");
    foreach ([0, 1, 2, 3] as $idx => $vidIdx) {
        $vidId = $videoIds[$vidIdx];
        $order = $idx + 1;
        // Home Screen Banner
        $homeFlag = 1;
        $stmtBanner->bind_param('iiii', $homeFlag, $moviesTypeId, $vidId, $order);
        $stmtBanner->execute();
        // Movies Tab Banner
        $homeFlag = 2;
        $stmtBanner->bind_param('iiii', $homeFlag, $moviesTypeId, $vidId, $order);
        $stmtBanner->execute();
    }
    $stmtBanner->close();

    // 9. Home Sections
    $actionContentIds  = implode(',', [$videoIds[0], $videoIds[2], $videoIds[3], $videoIds[4]]);
    $comedyContentIds  = implode(',', [$videoIds[1], $videoIds[5]]);
    $trailerContentIds = implode(',', [$videoIds[4], $videoIds[5], $videoIds[0], $videoIds[1]]);

    $sections = [
        // [section_type, is_home_screen, video_type, type_id, title, short_title, screen_layout, content_ids, category_id, order_by_upload, order_by_view, sort_order]
        [0, 1, 1,   $moviesTypeId, 'Trending Now',       'Top Action & Comedy Hits', 'big_landscape', '',                 0, 0, 2, 1],
        [1, 1, 1,   $moviesTypeId, 'Action & Thrillers', 'High-Octane Cinema',       'portrait',      $actionContentIds,  0, 0, 0, 2],
        [1, 1, 1,   $moviesTypeId, 'Comedy Specials',    'Laugh Out Loud',           'landscape',     $comedyContentIds,  0, 0, 0, 3],
        [1, 1, 1,   $moviesTypeId, 'Official Trailers',  'Watch Teasers & Previews', 'landscape',     $trailerContentIds, 0, 0, 0, 4],
        [0, 1, 3,   0,             'Browse Categories',  'Action, Comedy & More',    'category',      '',                 0, 0, 0, 5],
        [0, 1, 102, 0,             'Featured Channels',  'Watch By Channel',         'channel',       '',                 0, 0, 0, 6],
        [0, 1, 8,   $shortsTypeId, 'Quick Trailers',     'Short Clips & Reels',      'shorts',        '',                 0, 2, 0, 7],
        // Movies tab sections (is_home_screen = 2)
        [0, 2, 1,   $moviesTypeId, 'Latest Movies',      'Fresh Releases',           'portrait',      '',                 0, 2, 0, 1],
        [1, 2, 1,   $moviesTypeId, 'Action Hits',        'Non-Stop Action',          'landscape',     $actionContentIds,  0, 0, 0, 2],
        [1, 2, 1,   $moviesTypeId, 'Comedy Movies',      'Feel-Good Entertainment',  'landscape',     $comedyContentIds,  0, 0, 0, 3],
    ];

    $stmtSec = $mysqli->prepare("INSERT INTO `tbl_home_section` (
        `section_type`, `is_home_screen`, `video_type`, `sub_video_type`, `type_id`,
        `title`, `short_title`, `screen_layout`, `content_ids`,
        `category_id`, `language_id`, `channel_id`, `order_by_upload`, `order_by_view`,
        `premium_video`, `no_of_content`, `view_all`, `is_title`, `sort_order`, `status`
    ) VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, 0, 10, 1, 1, ?, 1)");

    foreach ($sections as $sec) {
        $stmtSec->bind_param(
            'iiiissssiiii',
            $sec[0], $sec[1], $sec[2], $sec[3],
            $sec[4], $sec[5], $sec[6], $sec[7],
            $sec[8], $sec[9], $sec[10], $sec[11]
        );
        $stmtSec->execute();
    }
    $stmtSec->close();

    // 10. Update Brand Logo & Login Banner in tbl_general_setting
    $mysqli->query("UPDATE `tbl_general_setting` SET `value` = 'dtlive_logo.jpg' WHERE `key` = 'app_logo'");
    $mysqli->query("UPDATE `tbl_general_setting` SET `value` = 'login_banner.jpg' WHERE `key` = 'panel_login_page_img' AND (`value` = '' OR `value` IS NULL)");

    $mysqli->commit();
    echo "Seeded sample OTT videos, trailers, categories, channels, cast, banners, and brand logos successfully.\n";
} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        @$mysqli->rollback();
    }
    fwrite(STDERR, "Sample content seeding skipped: " . $e->getMessage() . "\n");
}
