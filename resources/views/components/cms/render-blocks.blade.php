@props(['blocks' => []])

@foreach ($blocks as $block)
    @php
        $type = $block['type'] ?? null;
        $data = $block['data'] ?? [];
    @endphp

    @switch($type)
        @case('hero')
            <x-cms.hero :data="$data" />
            @break
        @case('rich_text')
            <x-cms.rich-text :data="$data" />
            @break
        @case('image_gallery')
            <x-cms.image-gallery :data="$data" />
            @break
        @case('featured_units')
            <x-cms.featured-units :data="$data" />
            @break
        @case('announcements')
            <x-cms.announcements :data="$data" />
            @break
        @case('contact_form')
            <x-cms.contact-form :data="$data" />
            @break
    @endswitch
@endforeach
