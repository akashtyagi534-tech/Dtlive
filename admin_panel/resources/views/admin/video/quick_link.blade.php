@extends('admin.layout.page-app')
@section('page_title', 'Quick Add Video by Link')
@section('tab_title', 'Quick Add Video by Link')

@section('content')
    @include('admin.layout.sidebar')

    <div class="right-content">
        @include('admin.layout.header')

        <div class="body-content">
            <h1 class="page-title-sm">Quick Add Video by Link</h1>

            <div class="row">
                <div class="col-sm-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{__('label.dashboard')}}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Quick Add Link</li>
                    </ol>
                </div>
            </div>

            <div class="card custom-border-card mt-3">
                <div class="card-body">
                    <h5 class="mb-2">Paste 1 or Multiple Video Links (30+ Parts Supported)</h5>
                    <p class="text-muted mb-3">
                        Moj (`mojapp.in`), YouTube / Shorts, Vimeo, ya direct `.mp4` / `.m3u8` links ek-ek line me daalein.
                        Agar video **30 Parts** me hai, to **Multi-Part Series** select karein — server **Part 1 ke baad Part 2, 3... ek-ek karke sequential upload** karega aur APK me **Part-by-Part auto-next** chalega!
                    </p>

                    <form id="quickLinkForm" enctype="multipart/form-data">
                        @csrf
                        <div class="form-row mb-3">
                            <div class="col-md-12">
                                <label class="d-block"><strong>Upload Mode</strong></label>
                                <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                    <label class="btn btn-outline-success active" id="modeSeriesLabel">
                                        <input type="radio" name="mode" value="series" id="modeSeries" checked>
                                        <i class="fa-solid fa-list-ol mr-1"></i> Multi-Part Series (Part 1, 2, 3... ek ke baad ek)
                                    </label>
                                    <label class="btn btn-outline-secondary" id="modeSingleLabel">
                                        <input type="radio" name="mode" value="single" id="modeSingle">
                                        <i class="fa-solid fa-film mr-1"></i> Separate Single Videos
                                    </label>
                                </div>
                                <small class="form-text text-muted" id="linkCountBadge">0 valid links detected</small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="col-md-12 mb-3">
                                <label><strong>Video Links (One link per line — e.g. Part 1 se Part 30)</strong></label>
                                <textarea name="links" id="linksInput" rows="7" class="form-control" placeholder="Part 1 link&#10;Part 2 link&#10;Part 3 link&#10;...&#10;Part 30 link" required></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="col-md-5 mb-3">
                                <label id="titleLabel">Series / Video Title</label>
                                <input type="text" name="series_title" id="seriesTitleInput" class="form-control" placeholder="e.g. Action Drama Series (blank = auto from Part 1)">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Category</label>
                                <select name="category_id" id="categoryIdInput" class="form-control">
                                    <option value="0">Auto / Default Category</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3 d-flex align-items-end">
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input" id="alsoShorts" name="also_shorts" value="1" checked>
                                    <label class="custom-control-label" for="alsoShorts">Also add single links to Clips/Shorts</label>
                                </div>
                            </div>
                        </div>

                        <div id="uploadProgressWrap" class="mb-3" style="display:none;">
                            <div class="d-flex justify-content-between mb-1">
                                <strong id="uploadProgressText">Uploading Part 0 / 0...</strong>
                                <span id="uploadProgressPct">0%</span>
                            </div>
                            <div class="progress" style="height: 12px;">
                                <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%;"></div>
                            </div>
                        </div>

                        <button type="submit" id="importBtn" class="btn btn-default mw-150">
                            <i class="fa-solid fa-bolt mr-1"></i> Upload Sequential (1-by-1)
                        </button>
                    </form>

                    <div id="importResult" class="mt-4" style="display:none;"></div>
                </div>
            </div>

            <div class="card custom-border-card mt-4">
                <div class="card-body">
                    <h5 class="mb-3">Recently Published Videos in AT Player</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Source Type</th>
                                    <th>Stream URL</th>
                                </tr>
                            </thead>
                            <tbody id="recentTableBody">
                                @foreach($recentVideos as $vid)
                                    <tr>
                                        <td>{{ $vid->id }}</td>
                                        <td>{{ $vid->name }}</td>
                                        <td><span class="badge badge-info">{{ $vid->video_upload_type }}</span></td>
                                        <td style="max-width:420px; word-break:break-all; font-size:12px;">{{ $vid->video_320 }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('pagescript')
<script>
    function parseCleanLinks(raw) {
        var tokens = (raw || '').replace(/,/g, '\n').split(/[\s\n]+/);
        var out = [];
        var seen = {};
        tokens.forEach(function(t) {
            var u = (t || '').trim().replace(/^["'<>]+|["'<>]+$/g, '');
            if (!u) return;
            if (!/^https?:\/\//i.test(u)) {
                if (/^[a-z0-9.-]+\.[a-z]{2,}(\/.*)?$/i.test(u)) {
                    u = 'https://' + u;
                } else {
                    return;
                }
            }
            if (!seen[u]) {
                seen[u] = true;
                out.push(u);
            }
        });
        return out;
    }

    function updateLinkBadge() {
        var count = parseCleanLinks($('#linksInput').val()).length;
        $('#linkCountBadge').text(count + ' valid link(s) ready — 1 ke baad 1 sequential upload hoga');
    }

    $('#linksInput').on('input', updateLinkBadge);
    $('input[name="mode"]').on('change', function() {
        var mode = $('input[name="mode"]:checked').val();
        if (mode === 'series') {
            $('#titleLabel').text('Series Name (All Parts ek Series me)');
        } else {
            $('#titleLabel').text('Optional Title (for single video)');
        }
    });

    $('#quickLinkForm').on('submit', async function(e) {
        e.preventDefault();
        var urls = parseCleanLinks($('#linksInput').val());
        if (!urls.length) {
            alert('Please paste at least one valid video link.');
            return;
        }

        var mode = $('input[name="mode"]:checked').val() || 'series';
        if (urls.length === 1 && mode === 'series' && !$('#seriesTitleInput').val().trim()) {
            // Single link without custom series title defaults cleanly if user prefers
        }

        var btn = $('#importBtn');
        var resBox = $('#importResult');
        var progWrap = $('#uploadProgressWrap');
        var progBar = $('#uploadProgressBar');
        var progText = $('#uploadProgressText');
        var progPct = $('#uploadProgressPct');

        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-1"></i> Uploading 1-by-1...');
        resBox.show().html('<ul class="list-group mb-3" id="liveImportedList"></ul><div id="liveFailedBox"></div>');
        progWrap.show();

        var csrf = $('input[name="_token"]').val();
        var seriesTitle = $('#seriesTitleInput').val().trim();
        var categoryId = $('#categoryIdInput').val();
        var alsoShorts = $('#alsoShorts').is(':checked') ? 1 : 0;

        var showId = 0;
        var okCount = 0;
        var failed = [];

        for (var i = 0; i < urls.length; i++) {
            var partNum = i + 1;
            var pct = Math.round((i / urls.length) * 100);
            progBar.css('width', pct + '%');
            progPct.text(pct + '%');
            progText.text('Uploading ' + (mode === 'series' ? ('Part ' + partNum + ' / ' + urls.length) : ('Video ' + partNum + ' / ' + urls.length)) + '...');

            try {
                var resp = await $.ajax({
                    type: 'POST',
                    url: '{{ route("admin.quicklink.import") }}',
                    data: {
                        _token: csrf,
                        links: urls[i],
                        mode: mode,
                        series_title: seriesTitle,
                        title: seriesTitle,
                        show_id: showId,
                        part_number: partNum,
                        category_id: categoryId,
                        also_shorts: alsoShorts
                    }
                });

                if (resp.status == 200 && resp.imported && resp.imported.length) {
                    okCount++;
                    if (resp.show_id) {
                        showId = resp.show_id;
                    }
                    resp.imported.forEach(function(item) {
                        var safeName = $('<div>').text(item.name).html();
                        var badge = mode === 'series' ? ('Part ' + partNum + ' Live') : 'Live in APK';
                        $('#liveImportedList').append(
                            '<li class="list-group-item d-flex justify-content-between align-items-center bg-dark text-white">'
                            + '<span><strong>' + safeName + '</strong></span>'
                            + '<span class="badge badge-success">' + badge + '</span>'
                            + '</li>'
                        );
                    });
                } else {
                    var errMsg = Array.isArray(resp.errors) ? resp.errors.join(', ') : (resp.errors || 'Failed');
                    failed.push({ part: partNum, url: urls[i], error: errMsg });
                }
            } catch (xhr) {
                failed.push({ part: partNum, url: urls[i], error: 'HTTP ' + (xhr.status || 'Error') });
            }
        }

        progBar.css('width', '100%');
        progPct.text('100%');
        progText.text('Complete: ' + okCount + ' / ' + urls.length + ' uploaded!');
        btn.prop('disabled', false).html('<i class="fa-solid fa-bolt mr-1"></i> Upload Sequential (1-by-1)');

        if (okCount > 0) {
            resBox.prepend('<div class="alert alert-success"><strong>' + okCount + ' / ' + urls.length + ' ' + (mode === 'series' ? 'parts sequentially uploaded into Series (Show ID: ' + showId + ')!' : 'videos imported!') + '</strong></div>');
            if (failed.length === 0) {
                $('#linksInput').val('');
                updateLinkBadge();
            }
        }
        if (failed.length) {
            var fHtml = '<div class="alert alert-warning">Some links failed:<ul>';
            failed.forEach(function(f) {
                fHtml += '<li>Part ' + f.part + ': ' + $('<div>').text(f.url + ' — ' + f.error).html() + '</li>';
            });
            fHtml += '</ul></div>';
            $('#liveFailedBox').html(fHtml);
        }
    });
</script>
@endsection
