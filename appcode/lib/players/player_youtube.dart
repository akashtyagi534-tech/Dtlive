import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:youtube_player_flutter/youtube_player_flutter.dart';

import '../main.dart';
import '../model/playermodel.dart';
import '../players/orientationmanager.dart';
import '../players/player_video.dart';
import '../players/player_vimeo.dart';
import '../provider/connectivityprovider.dart';
import '../provider/playerprovider.dart';
import '../routes/routes_constant.dart';
import '../utils/color.dart';
import '../utils/constant.dart';
import '../utils/utils.dart';
import '../widget/mynetworkimg.dart';
import '../widget/mytext.dart';

class PlayerYoutube extends StatefulWidget {
  final PlayerModel playerModel;
  const PlayerYoutube({super.key, required this.playerModel});

  @override
  State<PlayerYoutube> createState() => PlayerYoutubeState();
}

class PlayerYoutubeState extends State<PlayerYoutube>
    with RouteAware, WidgetsBindingObserver {
  YoutubePlayerController? controller;
  bool fullScreen = false;
  late PlayerProvider playerProvider;
  late ConnectivityProvider connectivityProvider;
  int? playerCPosition;
  late int _currentEpiPos;
  late int _currentEpisodeId;
  late String _currentTitle;

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    printLog('didChangeAppLifecycleState state =====> ${state.name}');
    switch (state) {
      case AppLifecycleState.resumed:
        if (connectivityProvider.isOnline &&
            (widget.playerModel.playType == "Video" ||
                widget.playerModel.playType == "Show") &&
            Constant.userID != null &&
            widget.playerModel.isPremium == 1) {
          playerProvider.addRemoveDevice(1);
        }
        break;
      case AppLifecycleState.paused:
        if (connectivityProvider.isOnline &&
            (widget.playerModel.playType == "Video" ||
                widget.playerModel.playType == "Show") &&
            Constant.userID != null &&
            widget.playerModel.isPremium == 1) {
          playerProvider.addRemoveDevice(2);
        }
        break;
      default:
        break;
    }
  }

  @override
  void initState() {
    super.initState();
    _currentEpiPos = widget.playerModel.currentEpiPos ?? 0;
    _currentEpisodeId = widget.playerModel.episodeId ?? 0;
    _currentTitle = widget.playerModel.videoTitle ?? "";
    WidgetsBinding.instance.addObserver(this);
    connectivityProvider = Provider.of<ConnectivityProvider>(
      context,
      listen: false,
    );
    playerProvider = Provider.of<PlayerProvider>(context, listen: false);
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.manual, overlays: []);
    OrientationManager.forceLandscape();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _playerInit();
    });
  }

  @override
  void didChangeDependencies() {
    printLog("========= didChangeDependencies =========");
    routeObserver.subscribe(this, ModalRoute.of(context)!);
    super.didChangeDependencies();
  }

  @override
  void didPop() {
    printLog("========= didPop =========");
    super.didPop();
  }

  @override
  void didPopNext() {
    printLog("========= didPopNext =========");
    if (controller == null) {
      _playerInit();
    }
    super.didPopNext();
  }

  @override
  void didPush() {
    printLog("========= didPush =========");
    super.didPush();
  }

  @override
  void didPushNext() {
    printLog("========= didPushNext =========");
    super.didPushNext();
  }

  Future<void> _playerInit() async {
    WidgetsFlutterBinding.ensureInitialized();

    /* ******* Check Device Sync ******* */
    if (connectivityProvider.isOnline &&
        (widget.playerModel.playType == "Video" ||
            widget.playerModel.playType == "Show") &&
        Constant.userID != null &&
        widget.playerModel.isPremium == 1) {
      await playerProvider.addRemoveDevice(1);
      if (!playerProvider.isDeviceAdded) {
        if (!mounted) return;
        dynamic isNotWatching = await Utils.openWebDialog(
          context: context,
          newPage: RoutesConstant.cannotWatchPage,
          oldPage: "",
          reqText: "",
        );
        printLog("isNotWatching =========> $isNotWatching");
        if (!mounted) return;
        if (isNotWatching != null && isNotWatching == false) {
          Utils.exitPage(context);
          return;
        }
      }
    }
    /* ************** */

    printLog("videoUrl :===> ${widget.playerModel.videoUrl}");

    String? videoId = "";
    if (widget.playerModel.playType == "Video" ||
        widget.playerModel.playType == "Show") {
      videoId = YoutubePlayer.convertUrlToId(widget.playerModel.videoUrl ?? "");
    } else {
      videoId = YoutubePlayer.convertUrlToId(
        widget.playerModel.trailerUrl ?? "",
      );
    }
    printLog("videoId :====> $videoId");

    controller = YoutubePlayerController(
      initialVideoId: videoId ?? "",
      flags: YoutubePlayerFlags(
        mute: false,
        autoPlay: true,
        disableDragSeek: false,
        loop: false,
        isLive: false,
        forceHD: false,
        enableCaption: true,
      ),
    );

    Future.delayed(const Duration(seconds: 1)).then((value) {
      if (!mounted) return;
      controller?.play();
      setState(() {});
    });
    if (widget.playerModel.playType == "Video" ||
        widget.playerModel.playType == "Show") {
      /* Add Video view */
      await playerProvider.addVideoView(
        widget.playerModel.videoId.toString(),
        widget.playerModel.videoType.toString(),
        widget.playerModel.subVideoType.toString(),
        _currentEpisodeId.toString(),
      );
    }
  }

  Future<void> _jumpToEpisode(int index) async {
    final list = widget.playerModel.episodeList;
    if (list == null || index < 0 || index >= list.length) {
      await onBackPressed(false);
      return;
    }

    final ep = list[index];
    final epUrl = ep.video320 ?? "";
    final rawUploadType =
        (ep.videoUploadType ?? widget.playerModel.uploadType ?? "").trim();
    final lowerUpload = rawUploadType.toLowerCase();
    final lowerUrl = epUrl.toLowerCase();

    Utils.setQualityURLs(
      video320: epUrl,
      video480: (ep.video480 != null && ep.video480!.isNotEmpty)
          ? ep.video480!
          : epUrl,
      video720: (ep.video720 != null && ep.video720!.isNotEmpty)
          ? ep.video720!
          : epUrl,
      video1080: (ep.video1080 != null && ep.video1080!.isNotEmpty)
          ? ep.video1080!
          : epUrl,
    );

    final bool isYt = lowerUpload == "youtube" ||
        lowerUrl.contains("youtube.com") ||
        lowerUrl.contains("youtu.be");

    if (isYt) {
      final ytId = YoutubePlayer.convertUrlToId(epUrl);
      if (ytId != null && ytId.isNotEmpty && controller != null) {
        controller!.load(ytId);
        setState(() {
          _currentEpiPos = index;
          _currentEpisodeId = ep.id ?? 0;
          _currentTitle =
              (ep.name != null && ep.name!.isNotEmpty)
                  ? ep.name!
                  : "Part ${index + 1}";
        });
        return;
      }
    }

    final nextModel = PlayerModel(
      playType: widget.playerModel.playType,
      isLive: false,
      videoId: widget.playerModel.videoId ?? 0,
      videoTitle: (ep.name != null && ep.name!.isNotEmpty)
          ? ep.name
          : "Part ${index + 1}",
      videoType: widget.playerModel.videoType ?? 0,
      subVideoType: widget.playerModel.subVideoType ?? 0,
      typeId: widget.playerModel.typeId ?? 0,
      episodeId: ep.id ?? 0,
      videoUrl: epUrl,
      trailerUrl: widget.playerModel.trailerUrl ?? "",
      uploadType: rawUploadType.isNotEmpty ? rawUploadType : "external",
      videoThumb: ep.landscape ?? "",
      stopTime: 0,
      isPremium: widget.playerModel.isPremium ?? 0,
      isBuy: widget.playerModel.isBuy ?? 0,
      isRent: widget.playerModel.isRent ?? 0,
      rentBuy: widget.playerModel.rentBuy ?? 0,
      securityKey: widget.playerModel.securityKey ?? "",
      securityIVKey: widget.playerModel.securityIVKey ?? "",
      currentEpiPos: index,
      episodeList: list,
      cipherMediaDetails: null,
    );

    if (!mounted) return;
    if (lowerUpload == "vimeo" || lowerUrl.contains("vimeo.com")) {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => PlayerVimeo(playerModel: nextModel)),
      );
    } else {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => PlayerVideo(playerModel: nextModel)),
      );
    }
  }

  void _showPartsSheet() {
    final list = widget.playerModel.episodeList;
    if (list == null || list.isEmpty) return;

    showModalBottomSheet(
      context: context,
      backgroundColor: lightBlack,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) {
        return Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 4,
              margin: const EdgeInsets.symmetric(vertical: 10),
              decoration: BoxDecoration(
                color: white.withValues(alpha: 0.24),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
              child: Row(
                children: [
                  MyText(
                    color: white,
                    text: "Parts (${_currentEpiPos + 1} / ${list.length})",
                    multilanguage: false,
                    fontsizeNormal: 15,
                    fontsizeWeb: 17,
                    fontweight: FontWeight.w700,
                    maxline: 1,
                    overflow: TextOverflow.ellipsis,
                    textalign: TextAlign.start,
                    fontstyle: FontStyle.normal,
                  ),
                  const Spacer(),
                  IconButton(
                    icon: Icon(
                      Icons.close,
                      color: white.withValues(alpha: 0.6),
                      size: 20,
                    ),
                    onPressed: () => Navigator.of(ctx).pop(),
                  ),
                ],
              ),
            ),
            Divider(color: white.withValues(alpha: 0.12), height: 1),
            LimitedBox(
              maxHeight: 280,
              child: ListView.builder(
                shrinkWrap: true,
                itemCount: list.length,
                itemBuilder: (_, i) {
                  final ep = list[i];
                  final isCurrent = i == _currentEpiPos;
                  return ListTile(
                    leading: ClipRRect(
                      borderRadius: BorderRadius.circular(6),
                      child: MyNetworkImage(
                        imageUrl: ep.landscape ?? "",
                        width: 72,
                        height: 44,
                        fit: BoxFit.cover,
                      ),
                    ),
                    title: MyText(
                      color: isCurrent ? colorPrimary : white,
                      text: ep.name ?? ep.description ?? "Part ${i + 1}",
                      multilanguage: false,
                      textalign: TextAlign.start,
                      fontsizeNormal: 13,
                      fontsizeWeb: 14,
                      fontweight: isCurrent ? FontWeight.w700 : FontWeight.w500,
                      maxline: 1,
                      overflow: TextOverflow.ellipsis,
                      fontstyle: FontStyle.normal,
                    ),
                    trailing: isCurrent
                        ? const Icon(
                            Icons.play_arrow_rounded,
                            color: colorPrimary,
                            size: 20,
                          )
                        : null,
                    onTap: isCurrent
                        ? null
                        : () {
                            Navigator.of(ctx).pop();
                            _jumpToEpisode(i);
                          },
                  );
                },
              ),
            ),
            const SizedBox(height: 12),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.manual, overlays: []);
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) async {
        await onBackPressed(didPop);
      },
      child: Scaffold(
        body: SafeArea(
          child: (controller == null)
              ? Utils.pageLoader()
              : Center(
                  child: YoutubePlayerBuilder(
                    onExitFullScreen: () {
                      onBackPressed(false);
                    },
                    player: YoutubePlayer(
                      aspectRatio: 16 / 9,
                      controller: controller!,
                      showVideoProgressIndicator: true,
                      progressIndicatorColor: colorAccent,
                      onEnded: (metaData) {
                        final list = widget.playerModel.episodeList;
                        if (list != null && _currentEpiPos + 1 < list.length) {
                          _jumpToEpisode(_currentEpiPos + 1);
                        } else {
                          onBackPressed(false);
                        }
                      },
                      onReady: () {},
                    ),
                    builder: (context, player) {
                      return _buildPlayerUI(player: player);
                    },
                  ),
                ),
        ),
      ),
    );
  }

  Widget _buildPlayerUI({required Widget player}) {
    final list = widget.playerModel.episodeList;
    final hasParts = list != null && list.length > 1;
    return Stack(
      children: [
        Center(child: player),
        if (!kIsWeb)
          Positioned(
            top: 15,
            left: 15,
            child: SafeArea(
              child: InkWell(
                onTap: () {
                  onBackPressed(false);
                },
                focusColor: gray.withValues(alpha: 0.5),
                borderRadius: BorderRadius.circular(20),
                child: Utils.buildBackBtnDesign(context),
              ),
            ),
          ),
        if (hasParts)
          Positioned(
            top: 15,
            right: 15,
            child: SafeArea(
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: black.withValues(alpha: 0.72),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: InkWell(
                      onTap: _showPartsSheet,
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.playlist_play_rounded,
                            color: colorPrimary,
                            size: 20,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            _currentTitle.isNotEmpty
                                ? "${_currentEpiPos + 1}/${list.length}"
                                : "Parts",
                            style: const TextStyle(
                              color: white,
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  if (_currentEpiPos + 1 < list.length) ...[
                    const SizedBox(width: 8),
                    InkWell(
                      onTap: () => _jumpToEpisode(_currentEpiPos + 1),
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 5,
                        ),
                        decoration: BoxDecoration(
                          color: colorPrimary,
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              "Next Part",
                              style: TextStyle(
                                color: black,
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            SizedBox(width: 2),
                            Icon(
                              Icons.skip_next_rounded,
                              color: black,
                              size: 16,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
      ],
    );
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    routeObserver.unsubscribe(this);
    controller?.dispose();
    if (!kIsWeb) {
      OrientationManager.forcePortrait();
    }
    SystemChrome.setEnabledSystemUIMode(
      SystemUiMode.manual,
      overlays: SystemUiOverlay.values,
    );
    super.dispose();
  }

  Future<void> onBackPressed(bool didPop) async {
    if (didPop) return;
    if (!kIsWeb) {
      OrientationManager.forcePortrait();
    }
    SystemChrome.setEnabledSystemUIMode(
      SystemUiMode.manual,
      overlays: SystemUiOverlay.values,
    );
    playerCPosition = controller?.value.position.inMilliseconds;
    printLog("onBackPressed playerCPosition :===> $playerCPosition");
    printLog("onBackPressed playType :===> ${widget.playerModel.playType}");

    /* Remove Device from Watch START ********* */
    if (connectivityProvider.isOnline &&
        (widget.playerModel.playType == "Video" ||
            widget.playerModel.playType == "Show") &&
        Constant.userID != null &&
        widget.playerModel.isPremium == 1) {
      playerProvider.addRemoveDevice(2);
    }
    /* *********** Remove Device from Watch END */

    if ((widget.playerModel.playType == "Video" ||
            widget.playerModel.playType == "Show") &&
        Constant.userID != null) {
      if ((playerCPosition ?? 0) > 0) {
        /* Add to Continue */
        if (connectivityProvider.isOnline) {
          await playerProvider.addToContinue(
            "${widget.playerModel.videoId}",
            "$_currentEpisodeId",
            "${widget.playerModel.videoType}",
            "${widget.playerModel.subVideoType}",
            "$playerCPosition",
          );
        }
        if (!mounted) return;
        Utils.exitPage(context);
      } else {
        if (!mounted) return;
        Utils.exitPage(context);
      }
    } else {
      if (!mounted) return;
      Utils.exitPage(context);
    }
  }
}
