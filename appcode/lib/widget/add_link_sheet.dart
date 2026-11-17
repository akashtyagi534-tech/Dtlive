import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

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
  String? _errorMessage;
  String? _successMessage;
  List<Map<String, dynamic>> _importedVideos = [];

  @override
  void dispose() {
    _linksController.dispose();
    _titleController.dispose();
    super.dispose();
  }

  Future<void> _pasteFromClipboard() async {
    try {
      final data = await Clipboard.getData(Clipboard.kTextPlain);
      final text = data?.text?.trim() ?? "";
      if (text.isEmpty) {
        if (!mounted) return;
        setState(() {
          _errorMessage = "Clipboard khali hai — pehle koi video link copy karein.";
        });
        return;
      }
      final existing = _linksController.text.trim();
      setState(() {
        _errorMessage = null;
        _linksController.text = existing.isEmpty ? text : "$existing\n$text";
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = "Clipboard read nahi ho paya: $e";
      });
    }
  }

  Future<void> _importLinks() async {
    final rawLinks = _linksController.text.trim();
    if (rawLinks.isEmpty) {
      setState(() {
        _errorMessage = "Kripya kam se kam ek video link paste karein.";
        _successMessage = null;
      });
      return;
    }

    FocusScope.of(context).unfocus();
    setState(() {
      _isLoading = true;
      _errorMessage = null;
      _successMessage = null;
      _importedVideos = [];
    });

    final res = await ApiService().addVideoByLink(
      links: rawLinks,
      title: _titleController.text.trim(),
      alsoShorts: _alsoShorts,
    );

    if (!mounted) return;

    final status = int.tryParse(res['status']?.toString() ?? '400') ?? 400;
    if (status == 200) {
      final rawList = res['result'];
      final List<Map<String, dynamic>> parsed = [];
      if (rawList is List) {
        for (final item in rawList) {
          if (item is Map) {
            parsed.add(Map<String, dynamic>.from(item));
          }
        }
      }

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
        _importedVideos = parsed;
        _successMessage =
            res['message']?.toString() ??
            "${parsed.length} video(s) AT Player me add ho gaye!";
      });
    } else {
      setState(() {
        _isLoading = false;
        _errorMessage =
            res['message']?.toString() ??
            res['errors']?.toString() ??
            "Link se video extract nahi ho paya.";
      });
    }
  }

  Future<void> _watchVideo(Map<String, dynamic> video) async {
    final int videoId = int.tryParse(video['id']?.toString() ?? '0') ?? 0;
    final int videoType =
        int.tryParse(video['video_type']?.toString() ?? '1') ?? 1;
    final int typeId = int.tryParse(video['type_id']?.toString() ?? '1') ?? 1;
    final String videoTitle = (video['name'] ?? 'Imported Video').toString();
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
      playType: "Video",
      isLive: false,
      videoId: videoId,
      videoTitle: videoTitle,
      videoType: videoType,
      subVideoType: 0,
      typeId: typeId,
      episodeId: 0,
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
      currentEpiPos: 0,
      episodeList: null,
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
                        "Add Video by Link",
                        style: TextStyle(
                          color: titleTextColor,
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      SizedBox(height: 2),
                      Text(
                        "Moj, YouTube, Reels, Vimeo, MP4 / M3U8 — ek ya multiple links dalen",
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
            const SizedBox(height: 16),

            // Links input + Paste / Clear actions
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  "Video Link(s)",
                  style: TextStyle(
                    color: titleTextColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
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
              maxLines: 4,
              minLines: 2,
              style: const TextStyle(color: white, fontSize: 13),
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                hintText:
                    "https://mojapp.in/@user/video/...\nYa ek ke niche ek multiple links paste karein",
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

            // Optional title
            TextField(
              controller: _titleController,
              style: const TextStyle(color: white, fontSize: 13),
              decoration: InputDecoration(
                hintText: "Optional Video Title (single link ke liye)",
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
            const SizedBox(height: 6),

            // Also show in Shorts toggle
            InkWell(
              onTap: _isLoading
                  ? null
                  : () => setState(() => _alsoShorts = !_alsoShorts),
              borderRadius: BorderRadius.circular(10),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
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

            if (_errorMessage != null) ...[
              const SizedBox(height: 6),
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
                    if (_importedVideos.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      ..._importedVideos.take(3).map((v) {
                        final thumb =
                            (v['landscape'] ?? v['thumbnail'] ?? '').toString();
                        final vTitle =
                            (v['name'] ?? 'Imported Video').toString();
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
                                    width: 56,
                                    height: 38,
                                    fit: BoxFit.cover,
                                    errorBuilder: (_, _, _) => Container(
                                      width: 56,
                                      height: 38,
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
                              ElevatedButton.icon(
                                onPressed: () => _watchVideo(v),
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
                                icon: const Icon(
                                  Icons.play_arrow_rounded,
                                  size: 16,
                                ),
                                label: const Text(
                                  "Watch Now",
                                  style: TextStyle(
                                    fontSize: 11.5,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        );
                      }),
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
                      ? "Extracting & Adding Video..."
                      : "Import & Add to ${Constant.appName}",
                  style: const TextStyle(
                    fontSize: 14.5,
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
