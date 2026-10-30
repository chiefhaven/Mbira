{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($urls as $location => $modified)
    <url>
        <loc>{{ $location }}</loc>
        @if($modified)
        <lastmod>{{ $modified }}</lastmod>
        @endif
    </url>
@endforeach
</urlset>
