@php
    $adSettings = $adSettings ?? \App\Domain\Tracking\Settings\AdTrackingSettings::load();

    // Check for Affiliate Pixel in session or query ref
    $refCode = request()->query('ref') ?? session('affiliate_ref');
    $affiliate = $refCode ? \App\Models\Affiliate::where('code', $refCode)->first() : null;

    $merchantMetaPixelId = $adSettings->meta_pixel_enabled ? $adSettings->meta_pixel_id : null;
    $affiliateMetaPixelId = $affiliate?->meta_pixel_id;

    $merchantTikTokPixelId = $adSettings->tiktok_pixel_enabled ? $adSettings->tiktok_pixel_id : null;
    $affiliateTikTokPixelId = $affiliate?->tiktok_pixel_id;

    $hasMeta = !empty($merchantMetaPixelId) || !empty($affiliateMetaPixelId);
    $hasTikTok = !empty($merchantTikTokPixelId) || !empty($affiliateTikTokPixelId);
@endphp

@if($hasMeta)
    <!-- Meta Pixel Base Code -->
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');

        @if(!empty($merchantMetaPixelId))
            fbq('init', '{{ $merchantMetaPixelId }}');
        @endif

        @if(!empty($affiliateMetaPixelId) && $affiliateMetaPixelId !== $merchantMetaPixelId)
            fbq('init', '{{ $affiliateMetaPixelId }}');
        @endif

        fbq('track', 'PageView');
    </script>
@endif

@if($hasTikTok)
    <!-- TikTok Pixel Base Code -->
    <script>
        !function (w, d, t) {
            w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script");n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};

            @if(!empty($merchantTikTokPixelId))
                ttq.load('{{ $merchantTikTokPixelId }}');
            @endif

            @if(!empty($affiliateTikTokPixelId) && $affiliateTikTokPixelId !== $merchantTikTokPixelId)
                ttq.load('{{ $affiliateTikTokPixelId }}');
            @endif

            ttq.page();
        }(window, document, 'ttq');
    </script>
@endif

<!-- Shared Multi-Platform Ad Tracking Helper -->
<script>
    window.trackAdEvent = function(eventName, params, eventId) {
        params = params || {};
        var options = eventId ? { eventID: eventId } : {};

        // 1. Meta Pixel
        if (typeof window.fbq === 'function') {
            window.fbq('track', eventName, params, options);
        }

        // 2. TikTok Pixel (maps Purchase -> CompletePayment)
        if (typeof window.ttq === 'function') {
            var ttEvent = eventName;
            if (eventName === 'Purchase') ttEvent = 'CompletePayment';
            if (eventName === 'InitiateCheckout') ttEvent = 'InitiateCheckout';
            if (eventName === 'AddToCart') ttEvent = 'AddToCart';
            if (eventName === 'ViewContent') ttEvent = 'ViewContent';

            var ttOptions = eventId ? { event_id: eventId } : {};
            window.ttq.track(ttEvent, params, ttOptions);
        }
    };
</script>

