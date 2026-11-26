{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('home') }}</loc></url>
@foreach ($titles as $entry)
    <url><loc>{{ route('titles.show', $entry->slug) }}</loc><lastmod>{{ $entry->lastModified->format(DATE_ATOM) }}</lastmod></url>
@endforeach
</urlset>
