<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
    <channel>
        <title>DomMood</title>
        <link>{{ url('/') }}</link>
        <description>Фід товарів для Google Merchant Center</description>
@foreach($items as $item)
        <item>
            <g:id>{{ $item['id'] }}</g:id>
            <title>{{ $item['title'] }}</title>
            <description>{{ $item['description'] }}</description>
            <link>{{ $item['link'] }}</link>
            <g:image_link>{{ $item['image_link'] }}</g:image_link>
@foreach($item['additional_image_links'] as $imageLink)
            <g:additional_image_link>{{ $imageLink }}</g:additional_image_link>
@endforeach
            <g:availability>{{ $item['availability'] }}</g:availability>
            <g:price>{{ number_format((float) $item['price'], 2, '.', '') }} {{ $item['currency'] }}</g:price>
@if($item['sale_price'] !== null)
            <g:sale_price>{{ number_format((float) $item['sale_price'], 2, '.', '') }} {{ $item['currency'] }}</g:sale_price>
@endif
            <g:condition>{{ $item['condition'] }}</g:condition>
            <g:brand>{{ $item['brand'] }}</g:brand>
@if($item['gtin'])
            <g:gtin>{{ $item['gtin'] }}</g:gtin>
@endif
@if($item['mpn'])
            <g:mpn>{{ $item['mpn'] }}</g:mpn>
@endif
@if($item['identifier_exists'])
            <g:identifier_exists>{{ $item['identifier_exists'] }}</g:identifier_exists>
@endif
            <g:item_group_id>{{ $item['item_group_id'] }}</g:item_group_id>
@if($item['google_gender'])
            <g:gender>{{ $item['google_gender'] }}</g:gender>
@endif
@if($item['google_age_group'])
            <g:age_group>{{ $item['google_age_group'] }}</g:age_group>
@endif
@if($item['size'])
            <g:size>{{ $item['size'] }}</g:size>
@endif
@if($item['google_size_system'])
            <g:size_system>{{ $item['google_size_system'] }}</g:size_system>
@endif
@foreach($item['google_size_types'] as $sizeType)
            <g:size_type>{{ $sizeType }}</g:size_type>
@endforeach
@if($item['color'])
            <g:color>{{ $item['color'] }}</g:color>
@endif
@if($item['google_material'])
            <g:material>{{ $item['google_material'] }}</g:material>
@endif
@if($item['google_pattern'])
            <g:pattern>{{ $item['google_pattern'] }}</g:pattern>
@endif
@if($item['product_type'])
            <g:product_type>{{ $item['product_type'] }}</g:product_type>
@endif
@if($item['google_product_category'])
            <g:google_product_category>{{ $item['google_product_category'] }}</g:google_product_category>
@endif
@if($item['google_is_bundle'])
            <g:is_bundle>yes</g:is_bundle>
@endif
@foreach($item['google_product_highlights'] as $highlight)
            <g:product_highlight>{{ $highlight }}</g:product_highlight>
@endforeach
@foreach($item['google_product_details'] as $detail)
            <g:product_detail>
@if(!empty($detail['section_name']))
                <g:section_name>{{ $detail['section_name'] }}</g:section_name>
@endif
                <g:attribute_name>{{ $detail['attribute_name'] }}</g:attribute_name>
                <g:attribute_value>{{ $detail['attribute_value'] }}</g:attribute_value>
            </g:product_detail>
@endforeach
@foreach($item['custom_labels'] as $index => $customLabel)
@if($customLabel)
            <g:custom_label_{{ $index }}>{{ $customLabel }}</g:custom_label_{{ $index }}>
@endif
@endforeach
        </item>
@endforeach
    </channel>
</rss>
