@extends('layouts.app')
@section('title','About Alpha Block Solutions')
@section('content')
<section class="page-hero container focused-page-hero">
    <h1>A focused technology company built around <span class="gradient-text">trading intelligence and trusted reporting.</span></h1>
    <p>Alpha Block Solutions develops and operates Pulse Trading Intelligence for market awareness, signal analysis and trading-decision support.</p>
</section>

<section class="container about-grid focused-about-grid">
    <article class="panel" id="vision"><h2>Our Vision</h2><p>To build a trusted trading-technology platform that helps users organize market analysis, apply risk controls and monitor trading activity through clear, controlled workflows.</p></article>
    <article class="panel"><h2>Our Mission</h2><p>To develop Pulse as a practical market-intelligence and trading-decision platform and publish responsible market-awareness information.</p></article>
    <article class="panel"><h2>Our Operating Principle</h2><p>Trading features are protected by account permissions, plan limits, personal risk settings, audit records and platform-level controls.</p></article>
    <article class="panel"><h2>Our Market Position</h2><p>ABS is not presented as a collection of unrelated services. Pulse is the flagship product, supported by live market data, verified market headlines and a focused account and administration system.</p></article>
</section>

<section class="container focused-about-services">
    <article class="panel">
        <span class="service-icon accent-violet">⌁</span>
        <div><h2>Pulse Trading Intelligence</h2><p>Market scanner, explainable strategy scoring, risk-defined signals, Binance connectivity, order and position monitoring, alerts, trade history, reporting and mobile-ready APIs.</p></div>
        <a class="button button-primary" href="{{ route('pulse.entry') }}">Explore Pulse</a>
    </article>
</section>

<section class="container content-grid focused-values-grid">
    <article class="panel"><h2>Security by design</h2><p>Role-based access, encrypted exchange credentials, platform-level execution controls, audit records and emergency-stop controls protect sensitive workflows.</p></article>
    <article class="panel"><h2>Explainable decisions</h2><p>Pulse shows the strategy evidence, score, entry, invalidation, target and risk-to-reward details behind generated signals.</p></article>
    <article class="panel"><h2>Responsible market information</h2><p>Market data and headlines retain source attribution, while ABS News adds focused Pulse market context and controlled editorial coverage.</p></article>
    <article class="panel"><h2>No guaranteed outcomes</h2><p>Pulse signals, calculators, automation and reports do not guarantee accuracy, profit or protection from loss. Users remain responsible for decisions and exchange accounts.</p></article>
</section>

<section class="container panel" style="margin-top:18px">
    <h2>Market Data Sources</h2>
    <p>ABS combines exchange, market, macroeconomic and editorial data from established sources. Economic-calendar coverage may include <a href="https://www.financecalendar.com" target="_blank" rel="noopener noreferrer">FinanceCalendar.com</a> alongside configured market-data services.</p>
</section>

<section class="container newsletter-banner" id="contact">
    <div><span>✦</span><div><h3>Contact Alpha Block Solutions</h3><p>For Pulse access and platform support, contact Alpha Block Solutions.</p><small>General enquiries: {{ config('brand.contact_email') }} · Platform support: {{ config('brand.support_email') }}</small></div></div>
    <a class="button button-primary" href="mailto:{{ config('brand.contact_email') }}">Contact ABS</a>
</section>
@endsection
