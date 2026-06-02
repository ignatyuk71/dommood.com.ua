@if (config('seo.noindex_site'))
    <meta name="robots" content="noindex,nofollow,noarchive">
@elseif (empty($metaRobots))
    <meta name="robots" content="index, follow">
@endif
