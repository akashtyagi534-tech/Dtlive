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
                    <h5 class="mb-2">Paste 1 or Multiple Video Links</h5>
                    <p class="text-muted mb-3">
                        Moj (`mojapp.in`), YouTube / YouTube Shorts, Vimeo, ya direct `.mp4` / `.m3u8` links ek-ek line me daalein. Server automatically video stream, title aur thumbnail nikaal kar **AT Player** APK ke Home screen par publish kar dega.
                    </p>

                    <form id="quickLinkForm" enctype="multipart/form-data">
                        @csrf
                        <div class="form-row">
                            <div class="col-md-12 mb-3">
                                <label><strong>Video Links (One link per line)</strong></label>
                                <textarea name="links" id="linksInput" rows="5" class="form-control" placeholder="https://mojapp.in/@glow_and_lovely/video/3972316268?referrer=VbDk6YT-1KM04XN&#10;https://www.youtube.com/watch?v=...&#10;https://example.com/video.mp4" required></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="col-md-5 mb-3">
                                <label>Optional Title (only for single link)</label>
                                <input type="text" name="title" class="form-control" placeholder="Auto-detect from link if blank">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Category</label>
                                <select name="category_id" class="form-control">
                                    <option value="0">Auto / Default Category</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3 d-flex align-items-end">
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input" id="alsoShorts" name="also_shorts" value="1" checked>
                                    <label class="custom-control-label" for="alsoShorts">Also add to Clips/Shorts</label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" id="importBtn" class="btn btn-default mw-150">
                            <i class="fa-solid fa-bolt mr-1"></i> Import Videos
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
    $('#quickLinkForm').on('submit', function(e) {
        e.preventDefault();
        var btn = $('#importBtn');
        var resBox = $('#importResult');
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-1"></i> Extracting...');
        resBox.hide().html('');

        $.ajax({
            type: 'POST',
            url: '{{ route("admin.quicklink.import") }}',
            data: $(this).serialize(),
            success: function(resp) {
                btn.prop('disabled', false).html('<i class="fa-solid fa-bolt mr-1"></i> Import Videos');
                if (resp.status == 200) {
                    var html = '<div class="alert alert-success"><strong>' + resp.success + '</strong></div>';
                    if (resp.imported && resp.imported.length) {
                        html += '<ul class="list-group mb-3">';
                        resp.imported.forEach(function(item) {
                            html += '<li class="list-group-item d-flex justify-content-between align-items-center bg-dark text-white">'
                                + '<span><strong>' + $('<div>').text(item.name).html() + '</strong></span>'
                                + '<span class="badge badge-success">Live in APK</span>'
                                + '</li>';
                        });
                        html += '</ul>';
                    }
                    if (resp.failed && resp.failed.length) {
                        html += '<div class="alert alert-warning">Some links skipped:<ul>';
                        resp.failed.forEach(function(f) {
                            html += '<li>' + $('<div>').text(f.url + ' — ' + f.error).html() + '</li>';
                        });
                        html += '</ul></div>';
                    }
                    resBox.html(html).show();
                    $('#linksInput').val('');
                } else {
                    var msgs = Array.isArray(resp.errors) ? resp.errors.join('<br>') : (resp.errors || 'Import failed');
                    resBox.html('<div class="alert alert-danger">' + msgs + '</div>').show();
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="fa-solid fa-bolt mr-1"></i> Import Videos');
                resBox.html('<div class="alert alert-danger">Request failed (' + xhr.status + '). Please verify the links and try again.</div>').show();
            }
        });
    });
</script>
@endsection
