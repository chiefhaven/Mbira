@if(isset($moduleAssets) && is_array($moduleAssets))
    {{-- Render CSS files --}}
    @if(!empty($moduleAssets['css']))
        @foreach($moduleAssets['css'] as $css)
            <link rel="stylesheet" href="{{ Module::asset($css['path']) }}">
        @endforeach
    @endif
    
    {{-- Render JS files --}}
    @if(!empty($moduleAssets['js']))
        @foreach($moduleAssets['js'] as $js)
            <script src="{{ Module::asset($js['path']) }}"></script>
        @endforeach
    @endif
@endif