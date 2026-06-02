@php
    $hreflangUrl = $seo['canonical_url'] ?? url()->current();
@endphp
<link rel="alternate" hreflang="uk" href="{{ $hreflangUrl }}">
<link rel="alternate" hreflang="x-default" href="{{ $hreflangUrl }}">
