@extends('cms::frontend.layouts.app')
@section('title', 'Home')
@php
    $navbar_btn['text'] = 'Try For Free';
    $navbar_btn['link'] = route('business.getRegister');
    if(isset($__site_details['btns']) && isset($__site_details['btns']['navbar']) && !empty($__site_details['btns']['navbar']['text'])) {
        $navbar_btn['text'] = $__site_details['btns']['navbar']['text'] ?? 'Try For Free';
    }
    if(isset($__site_details['btns']) && isset($__site_details['btns']['navbar']) && !empty($__site_details['btns']['navbar']['link'])) {
        $navbar_btn['link'] = $__site_details['btns']['navbar']['link'] ?? route('business.getRegister');
    }

    $hero_btn['text'] = 'Start your Free Trial';
    $hero_btn['link'] = route('business.getRegister');
    if(isset($__site_details['btns']) && isset($__site_details['btns']['hero']) && !empty($__site_details['btns']['hero']['text'])) {
        $hero_btn['text'] = $__site_details['btns']['hero']['text'] ?? 'Start your Free Trial';
    }
    if(isset($__site_details['btns']) && isset($__site_details['btns']['hero']) && !empty($__site_details['btns']['hero']['link'])) {
        $hero_btn['link'] = $__site_details['btns']['hero']['link'] ?? route('business.getRegister');
    }

    $industry_btn['text'] = 'Get Started';
    $industry_btn['link'] = route('business.getRegister');
    if(isset($__site_details['btns']) && isset($__site_details['btns']['industry']) && !empty($__site_details['btns']['industry']['text'])) {
        $industry_btn['text'] = $__site_details['btns']['industry']['text'] ?? 'Get Started';
    }
    if(isset($__site_details['btns']) && isset($__site_details['btns']['industry']) && !empty($__site_details['btns']['industry']['link'])) {
        $industry_btn['link'] = $__site_details['btns']['industry']['link'] ?? route('business.getRegister');
    }

    $cta_btn['text'] = 'Try Now';
    $cta_btn['link'] = route('business.getRegister');
    if(isset($__site_details['btns']) && isset($__site_details['btns']['cta']) && !empty($__site_details['btns']['cta']['text'])) {
        $cta_btn['text'] = $__site_details['btns']['cta']['text'] ?? 'Try Now';
    }
    if(isset($__site_details['btns']) && isset($__site_details['btns']['cta']) && !empty($__site_details['btns']['cta']['link'])) {
        $cta_btn['link'] = $__site_details['btns']['cta']['link'] ?? route('business.getRegister');
    }
@endphp
@includeIf('cms::frontend.layouts.home_header')
@section('meta')
    <meta name="description" content="{{$page->meta_description}}">
@endsection
@section('content')
@php
    $page_meta = $page->pageMeta->keyBy('meta_key');
@endphp
<!------------------------------>
<!--Features---------------->
<!------------------------------>
@includeIf('cms::frontend.pages.partials.features', ['page_meta' => $page_meta])

<!------------------------------>
<!--Industries---------------->
<!------------------------------>
@includeIf('cms::frontend.pages.partials.industries', ['page_meta' => $page_meta])

<!------------------------------>
<!--Stats---------------->
<!------------------------------>
@includeIf('cms::frontend.pages.partials.statistics', ['statistics' => $statistics ?? []])

<!------------------------------>
<!--Testimonial---------------->
<!------------------------------>
@includeIf('cms::frontend.pages.partials.testimonial', ['testimonials' => $testimonials ?? []])

<!------------------------------>
<!--CTA---------------->
<!------------------------------>
@includeIf('cms::frontend.pages.partials.cta')

<!------------------------------>
<!--FAQ---------------->
<!------------------------------>
@includeIf('cms::frontend.pages.partials.faq', ['faqs' => $faqs ?? []])
@endsection
@section('javascript')
<script type="text/javascript">
    new Sticky("[sticky]");
</script>
@endsection