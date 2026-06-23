@if(config('seo.google_analytics_id'))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ config('seo.google_analytics_id') }}"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{{ config('seo.google_analytics_id') }}', { anonymize_ip: true });
</script>
@endif

@if(config('seo.google_tag_manager_id'))
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{{ config('seo.google_tag_manager_id') }}');</script>
@endif

@if(config('seo.meta_pixel_id'))
<script>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '{{ config('seo.meta_pixel_id') }}');
    fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" alt=""
    src="https://www.facebook.com/tr?id={{ config('seo.meta_pixel_id') }}&ev=PageView&noscript=1"/></noscript>
@endif
