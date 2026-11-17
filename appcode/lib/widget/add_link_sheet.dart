import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../model/episodebyseasonmodel.dart' as episodes;
import '../model/playermodel.dart';
import '../provider/homeprovider.dart';
import '../provider/sectiondataprovider.dart';
import '../utils/color.dart';
import '../utils/constant.dart';
import '../utils/utils.dart';
import '../webservice/apiservices.dart';

Future<void> showAddLinkSheet(BuildContext context) async {
  await showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: secondaryBgColor,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (ctx) => _AddLinkSheetContent(parentContext: context),
  );
}

class _AddLinkSheetContent extends StatefulWidget {
  final BuildContext parentContext;
  const _AddLinkSheetContent({required this.parentContext});

  @override
  State<_AddLinkSheetContent> createState() => _AddLinkSheetContentState();
}

class _AddLinkSheetContentState extends State<_AddLinkSheetContent> {
  final TextEditingController _linksController = TextEditingController();
  final TextEditingController _titleController = TextEditingController();

  bool _isLoading = false;
  bool _alsoShorts = true;
  bool _isSeriesMode = false;
  bool _userToggledMode = false;

  int _uploadCurrent = 0;
  int _uploadTotal = 0;
  int _importedShowId = 0;
  String _uploadStatusText = "";

  String? _errorMessage;
  String? _successMessage;
  List<Map<String, dynamic>> _importedVideos = [];

  @override
  void dispose() {
    _linksController.dispose();
    _titleController.dispose();
    super.dispose();
  }

  List<String> _parseUrls(String rawInput) {
    final normalized = rawInput
        .replaceAll('\r\n', '\n')
        .replaceAll('\r', '\n')
        .replaceAll(',', '\n');
    final parts = normalized.split(RegExp(r'[\s\n]+'));
    final List<String> urls = [];
    final Set<String> seen = {};
    final domainRegex = RegExp(r'^[a-z0-9.-]+\.[a-z]{2,}(/.*)?$', caseSensitive: false);

    for (final raw in parts) {
      var u = raw.trim().replaceAll(RegExp('^["\'<>]+|["\'<>]+\$'), '');
      if (u.isEmpty) continue;
      if (!RegExp(r'^https?://', caseSensitive: false).hasMatch(u)) {
        if (domainRegex.hasMatch(u)) {
          u = 'https://$u';
        } else {
          continue;
        }
      }
      final uri = Uri.tryParse(u);
      if (uri != null && uri.host.isNotEmpty && !seen.contains(u)) {
        seen.add(u);
        urls.add(u);
      }
    }
    return urls;
  }

  void _onLinksChanged(String value) {
    final detected = _parseUrls(value);
    if (!_userToggledMode) {
      _isSeriesMode = detected.length > 1;
    }
    setState(() {});
  }

  Future<void> _pasteFromClipboard() async {
    try {
      final data = await Clipboard.getData(Clipboard.kTextPlain);
      final text = data?.text?.trim() ?? "";
      if (text.isEmpty) {
        if (!mounted) return;
        setState(() {
          _errorMessage =
              "Clipboard khali hai — pehle koi video link copy karein.";
        });
        return;
      }
      final existing = _linksController.text.trim();
      final combined = existing.isEmpty ? text : "$existing\n$text";
      _linksController.text = combined;
      _errorMessage = null;
      _onLinksChanged(combined);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = "Clipboard read nahi ho paya: $e";
      });
    }
  }

  Future<void> _importLinks() async {
    final urls = _parseUrls(_linksController.text.trim());
    if (urls.isEmpty) {
      setState(() {
        _errorMessage = "Kripya kam se kam ek valid video link paste karein.";
        _successMessage = null;
      });
      return;
    }

    FocusScope.of(context).unfocus();
    final bool useSeriesMode = _isSeriesMode;
    final String titleInput = _titleController.text.trim();

    setState(() {
      _isLoading = true;
      _errorMessage = null;
      _successMessage = null;
      _importedVideos = [];
      _importedShowId = 0;
      _uploadCurrent = 0;
      _uploadTotal = urls.length;
      _uploadStatusText = useSeriesMode
          ? "Starting Part 1 / ${urls.length}..."
          : "Starting Video 1 / ${urls.length}...";
    });

    final List<Map<String, dynamic>> collected = [];
    final List<String> failedNotes = [];
    int showId = 0;

    for (int i = 0; i < urls.length; i++) {
      if (!mounted) return;
      final int partNum = i + 1;
      setState(() {
        _uploadCurrent = partNum;
        _uploadStatusText = useSeriesMode
            ? "Uploading Part $partNum / ${urls.length}..."
            : "Uploading Video $partNum / ${urls.length}...";
      });

      final res = await ApiService().addVideoByLink(
        links: urls[i],
        title: titleInput,
        mode: useSeriesMode ? 'series' : 'single',
        seriesTitle: titleInput,
        showId: showId,
        partNumber: partNum,
        alsoShorts: _alsoShorts,
      );

      if (!mounted) return;
      final status = int.tryParse(res['status']?.toString() ?? '400') ?? 400;
      if (status == 200) {
        final returnedShowId =
            int.tryParse(res['show_id']?.toString() ?? '0') ?? 0;
        if (returnedShowId > 0) {
          showId = returnedShowId;
        }
        final rawList = res['result'];
        if (rawList is List) {
          for (final item in rawList) {
            if (item is Map) {
              collected.add(Map<String, dynamic>.from(item));
            }
          }
        }
        setState(() {
          _importedShowId = showId;
          _importedVideos = List<Map<String, dynamic>>.from(collected);
        });
      } else {
        final errText =
            res['message']?.toString() ??
            res['errors']?.toString() ??
            "Extract failed";
        failedNotes.add("Part $partNum: $errText");
      }
    }

    if (!mounted) return;

    if (collected.isNotEmpty) {
      // Refresh Home feed sections & banners immediately
      try {
        final homeProvider = Provider.of<HomeProvider>(
          widget.parentContext,
          listen: false,
        );
        final sectionDataProvider = Provider.of<SectionDataProvider>(
          widget.parentContext,
          listen: false,
        );
        homeProvider.setSelectedTab(-1);
        await Future.wait([
          homeProvider.getSectionType(),
          sectionDataProvider.getSectionBanner("0", "1"),
          sectionDataProvider.getSectionList("0", "1", 1),
        ]);
      } catch (e) {
        printLog("AddLinkSheet home refresh error: $e");
      }

      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _importedShowId = showId;
        _importedVideos = collected;
        _uploadStatusText = "";
        _successMessage = useSeriesMode
            ? "${collected.length} / ${urls.length} Parts 1-by-1 add ho gaye! Ab Part 1 se lagaatar chalega."
            : "${collected.length} / ${urls.length} video(s) AT Player me add ho gaye!";
        if (failedNotes.isNotEmpty) {
          _errorMessage =
              "${failedNotes.length} link skip hua: ${failedNotes.first}";
        }
      });
    } else {
      setState(() {
        _isLoading = false;
        _uploadStatusText = "";
        _errorMessage = failedNotes.isNotEmpty
            ? failedNotes.first
            : "Link se video extract nahi ho paya.";
      });
    }
  }

  episodes.Result _mapToEpisodeResult(Map<String, dynamic> m, int fallbackIdx) {
    int toInt(dynamic v, [int def = 0]) =>
        int.tryParse(v?.toString() ?? '') ?? def;
    return episodes.Result(
      id: toInt(m['id']),
      showId: toInt(m['show_id'], _importedShowId),
      seasonId: toInt(m['season_id'], 1),
      name: (m['name'] ?? 'Part ${fallbackIdx + 1}').toString(),
      thumbnail: (m['thumbnail'] ?? '').toString(),
      landscape: (m['landscape'] ?? m['thumbnail'] ?? '').toString(),
      description: (m['description'] ?? '').toString(),
      videoUploadType: (m['video_upload_type'] ?? 'external').toString(),
      video320: (m['video_320'] ?? '').toString(),
      video480: (m['video_480'] ?? m['video_320'] ?? '').toString(),
      video720: (m['video_720'] ?? m['video_320'] ?? '').toString(),
      video1080: (m['video_1080'] ?? m['video_320'] ?? '').toString(),
      videoExtension: (m['video_extension'] ?? 'mp4').toString(),
      videoDuration: toInt(m['video_duration'], 60000),
      stopTime: 0,
      subtitleType: (m['subtitle_type'] ?? 'external').toString(),
      subtitleLang1: (m['subtitle_lang_1'] ?? '').toString(),
      subtitle1: (m['subtitle_1'] ?? '').toString(),
      subtitleLang2: (m['subtitle_lang_2'] ?? '').toString(),
      subtitle2: (m['subtitle_2'] ?? '').toString(),
      subtitleLang3: (m['subtitle_lang_3'] ?? '').toString(),
      subtitle3: (m['subtitle_3'] ?? '').toString(),
      isPremium: 0,
      isTitle: 1,
      isDownload: 0,
      totalView: toInt(m['total_view'], 1),
      sortable: fallbackIdx + 1,
      status: 1,
      isBuy: 0,
      isRent: 0,
      rentBuy: 0,
    );
  }

  Future<void> _watchFromIndex(int index) async {
    if (index < 0 || index >= _importedVideos.length) return;
    final video = _importedVideos[index];

    final bool asSeries = _isSeriesMode && _importedVideos.length > 1;
    final List<episodes.Result>? epList = asSeries
        ? List.generate(
            _importedVideos.length,
            (i) => _mapToEpisodeResult(_importedVideos[i], i),
          )
        : null;

    final int videoId = asSeries && _importedShowId > 0
        ? _importedShowId
        : (int.tryParse(video['id']?.toString() ?? '0') ?? 0);
    final int episodeId = asSeries
        ? (int.tryParse(video['id']?.toString() ?? '0') ?? 0)
        : 0;
    final int videoType = asSeries
        ? 2
        : (int.tryParse(video['video_type']?.toString() ?? '1') ?? 1);
    final int typeId = int.tryParse(video['type_id']?.toString() ?? '1') ?? 1;
    final String videoTitle = (video['name'] ?? 'Part ${index + 1}').toString();
    final String video320 = (video['video_320'] ?? '').toString();
    final String video480 = (video['video_480'] ?? video320).toString();
    final String video720 = (video['video_720'] ?? video320).toString();
    final String video1080 = (video['video_1080'] ?? video320).toString();
    final String trailerUrl = (video['trailer_url'] ?? video320).toString();
    final String uploadType =
        (video['video_upload_type'] ?? 'external').toString();
    final String thumbUrl =
        (video['landscape'] ?? video['thumbnail'] ?? '').toString();

    if (video320.isEmpty) {
      setState(() {
        _errorMessage = "Playable video URL missing hai.";
      });
      return;
    }

    Utils.setQualityURLs(
      video320: video320,
      video480: video480,
      video720: video720,
      video1080: video1080,
    );

    final playerModel = PlayerModel(
      playType: asSeries ? "Show" : "Video",
      isLive: false,
      videoId: videoId,
      videoTitle: videoTitle,
      videoType: videoType,
      subVideoType: 0,
      typeId: typeId,
      episodeId: episodeId,
      stopTime: 0,
      isPremium: 0,
      isBuy: 0,
      isRent: 0,
      rentBuy: 0,
      videoUrl: video320,
      trailerUrl: trailerUrl,
      uploadType: uploadType,
      videoThumb: thumbUrl,
      securityKey: "",
      securityIVKey: null,
      currentEpiPos: index,
      episodeList: epList,
      cipherMediaDetails: null,
    );

    Navigator.of(context).pop();
    if (widget.parentContext.mounted) {
      await Utils.openPlayer(
        context: widget.parentContext,
        playerModel: playerModel,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    final detectedUrls = _parseUrls(_linksController.text);

    return Padding(
      padding: EdgeInsets.fromLTRB(18, 16, 18, bottomInset + 20),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 44,
                height: 4,
                margin: const EdgeInsets.only(bottom: 14),
                decoration: BoxDecoration(
                  color: descTextColor.withValues(alpha: 0.4),
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
            ),
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: colorPrimary.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(
                    Icons.add_link_rounded,
                    color: colorPrimary,
                    size: 22,
                  ),
                ),
                const SizedBox(width: 12),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        "Add Video / 30+ Parts by Link",
                        style: TextStyle(
                          color: titleTextColor,
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      SizedBox(height: 2),
                      Text(
                        "Moj, YouTube, Reels, MP4 — 1 ya 30 part links paste karein",
                        style: TextStyle(
                          color: descTextColor,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.of(context).pop(),
                  icon: const Icon(Icons.close_rounded, color: descTextColor),
                ),
              ],
            ),
            const SizedBox(height: 14),

            // Links input + Paste / Clear actions
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  detectedUrls.isEmpty
                      ? "Video Link(s)"
                      : "${detectedUrls.length} Link(s) Ready",
                  style: TextStyle(
                    color: detectedUrls.isEmpty ? titleTextColor : colorPrimary,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                Row(
                  children: [
                    if (_linksController.text.isNotEmpty)
                      TextButton.icon(
                        onPressed: _isLoading
                            ? null
                            : () {
                                setState(() {
                                  _linksController.clear();
                                  _errorMessage = null;
                                  _userToggledMode = false;
                                  _isSeriesMode = false;
                                });
                              },
                        icon: const Icon(
                          Icons.clear_rounded,
                          size: 16,
                          color: descTextColor,
                        ),
                        label: const Text(
                          "Clear",
                          style: TextStyle(color: descTextColor, fontSize: 12),
                        ),
                      ),
                    ElevatedButton.icon(
                      onPressed: _isLoading ? null : _pasteFromClipboard,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: colorPrimary.withValues(alpha: 0.18),
                        foregroundColor: colorPrimary,
                        elevation: 0,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 6,
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                      icon: const Icon(Icons.content_paste_rounded, size: 15),
                      label: const Text(
                        "Paste Link",
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _linksController,
              maxLines: 5,
              minLines: 2,
              style: const TextStyle(color: white, fontSize: 13),
              onChanged: _onLinksChanged,
              decoration: InputDecoration(
                hintText:
                    "Part 1 link\nPart 2 link\n...\nPart 30 link (1 ke baad 1 upload aur chalega)",
                hintStyle: TextStyle(
                  color: descTextColor.withValues(alpha: 0.6),
                  fontSize: 12,
                ),
                filled: true,
                fillColor: appBgColor,
                contentPadding: const EdgeInsets.all(12),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: grayDark),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: grayDark),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: colorPrimary, width: 1.4),
                ),
              ),
            ),
            const SizedBox(height: 12),

            // Optional title / Series title
            TextField(
              controller: _titleController,
              style: const TextStyle(color: white, fontSize: 13),
              decoration: InputDecoration(
                hintText: _isSeriesMode
                    ? "Series Name (e.g. Comedy Part 1-30)"
                    : "Optional Video Title (single link ke liye)",
                hintStyle: TextStyle(
                  color: descTextColor.withValues(alpha: 0.6),
                  fontSize: 12,
                ),
                filled: true,
                fillColor: appBgColor,
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 11,
                ),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: grayDark),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: grayDark),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: colorPrimary, width: 1.4),
                ),
              ),
            ),
            const SizedBox(height: 8),

            // Multi-Part Series Mode Toggle
            InkWell(
              onTap: _isLoading
                  ? null
                  : () => setState(() {
                      _userToggledMode = true;
                      _isSeriesMode = !_isSeriesMode;
                    }),
              borderRadius: BorderRadius.circular(10),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 6),
                decoration: BoxDecoration(
                  color: _isSeriesMode
                      ? colorPrimary.withValues(alpha: 0.10)
                      : Colors.transparent,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: _isSeriesMode
                        ? colorPrimary.withValues(alpha: 0.45)
                        : Colors.transparent,
                  ),
                ),
                child: Row(
                  children: [
                    Checkbox(
                      value: _isSeriesMode,
                      activeColor: colorPrimary,
                      checkColor: black,
                      onChanged: _isLoading
                          ? null
                          : (val) => setState(() {
                              _userToggledMode = true;
                              _isSeriesMode = val ?? false;
                            }),
                    ),
                    const Expanded(
                      child: Text(
                        "Multi-Part Series Mode (1 ke baad 1 upload & Part-by-Part chalega)",
                        style: TextStyle(
                          color: titleTextColor,
                          fontSize: 12.5,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            // Also show in Shorts toggle (when single videos)
            if (!_isSeriesMode)
              InkWell(
                onTap: _isLoading
                    ? null
                    : () => setState(() => _alsoShorts = !_alsoShorts),
                borderRadius: BorderRadius.circular(10),
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Row(
                    children: [
                      Checkbox(
                        value: _alsoShorts,
                        activeColor: colorPrimary,
                        checkColor: black,
                        onChanged: _isLoading
                            ? null
                            : (val) =>
                                setState(() => _alsoShorts = val ?? true),
                      ),
                      const Expanded(
                        child: Text(
                          "Clips / Shorts section me bhi add karein (Moj/Reels)",
                          style: TextStyle(color: descTextColor, fontSize: 12.5),
                        ),
                      ),
                    ],
                  ),
                ),
              ),

            if (_isLoading && _uploadTotal > 0) ...[
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: appBgColor,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: colorPrimary.withValues(alpha: 0.4)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          _uploadStatusText,
                          style: const TextStyle(
                            color: colorPrimary,
                            fontSize: 12.5,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        Text(
                          "$_uploadCurrent / $_uploadTotal",
                          style: const TextStyle(
                            color: titleTextColor,
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(6),
                      child: LinearProgressIndicator(
                        value: _uploadTotal > 0
                            ? (_uploadCurrent / _uploadTotal).clamp(0.0, 1.0)
                            : null,
                        minHeight: 7,
                        color: colorPrimary,
                        backgroundColor: grayDark,
                      ),
                    ),
                  ],
                ),
              ),
            ],

            if (_errorMessage != null) ...[
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: failureBG.withValues(alpha: 0.16),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: failureBG.withValues(alpha: 0.5)),
                ),
                child: Text(
                  _errorMessage!,
                  style: const TextStyle(color: white, fontSize: 12.5),
                ),
              ),
            ],

            if (_successMessage != null) ...[
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: successBG.withValues(alpha: 0.18),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: colorPrimaryDark),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(
                          Icons.check_circle_rounded,
                          color: colorPrimary,
                          size: 18,
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            _successMessage!,
                            style: const TextStyle(
                              color: white,
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (_isSeriesMode && _importedVideos.length > 1) ...[
                      const SizedBox(height: 10),
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton.icon(
                          onPressed: () => _watchFromIndex(0),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: colorPrimary,
                            foregroundColor: black,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                          ),
                          icon: const Icon(Icons.playlist_play_rounded),
                          label: Text(
                            "Watch Part 1 -> All ${_importedVideos.length} Parts",
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 13,
                            ),
                          ),
                        ),
                      ),
                    ],
                    if (_importedVideos.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      ConstrainedBox(
                        constraints: const BoxConstraints(maxHeight: 200),
                        child: ListView.builder(
                          shrinkWrap: true,
                          itemCount: _importedVideos.length,
                          itemBuilder: (_, idx) {
                            final v = _importedVideos[idx];
                            final thumb =
                                (v['landscape'] ?? v['thumbnail'] ?? '')
                                    .toString();
                            final vTitle =
                                (v['name'] ?? 'Part ${idx + 1}').toString();
                            return Container(
                              margin: const EdgeInsets.only(top: 6),
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: appBgColor.withValues(alpha: 0.7),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Row(
                                children: [
                                  if (thumb.isNotEmpty)
                                    ClipRRect(
                                      borderRadius: BorderRadius.circular(6),
                                      child: Image.network(
                                        thumb,
                                        width: 50,
                                        height: 36,
                                        fit: BoxFit.cover,
                                        errorBuilder: (_, _, _) => Container(
                                          width: 50,
                                          height: 36,
                                          color: grayDark,
                                          child: const Icon(
                                            Icons.play_arrow_rounded,
                                            color: white,
                                          ),
                                        ),
                                      ),
                                    ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Text(
                                      vTitle,
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(
                                        color: titleTextColor,
                                        fontSize: 12.5,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  ElevatedButton(
                                    onPressed: () => _watchFromIndex(idx),
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: colorPrimary,
                                      foregroundColor: black,
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 10,
                                        vertical: 6,
                                      ),
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                    ),
                                    child: const Text(
                                      "Play",
                                      style: TextStyle(
                                        fontSize: 11.5,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            );
                          },
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],

            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              height: 46,
              child: ElevatedButton.icon(
                onPressed: _isLoading ? null : _importLinks,
                style: ElevatedButton.styleFrom(
                  backgroundColor: colorPrimary,
                  foregroundColor: black,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                icon: _isLoading
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: black,
                        ),
                      )
                    : const Icon(Icons.cloud_upload_rounded, size: 20),
                label: Text(
                  _isLoading
                      ? (_uploadTotal > 1
                            ? "Uploading $_uploadCurrent / $_uploadTotal..."
                            : "Extracting & Adding Video...")
                      : (_isSeriesMode && detectedUrls.length > 1
                            ? "Upload ${detectedUrls.length} Parts (1-by-1) to ${Constant.appName}"
                            : "Import & Add to ${Constant.appName}"),
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
