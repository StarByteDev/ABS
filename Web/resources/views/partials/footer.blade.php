<footer class="site-footer final-site-footer">
    <div class="container final-footer-grid">
        <div class="final-footer-brand">
            <a href="{{ route('home') }}" aria-label="Alpha Block Solutions home"><img src="{{ asset('assets/brand/abs-logo-512.png') }}" alt="" width="44" height="44"><b>ALPHA <em>BLOCK</em> SOLUTIONS</b></a>
            <p>Intelligence for digital markets.<br>Verified insights. Real-time context.<br>Smarter decisions.</p>
            <div class="final-social-row" aria-label="Alpha Block Solutions social channels"><span>𝕏</span><span>in</span><span>▶</span><span>✉</span></div>
        </div>
        <nav aria-label="Platform links"><h4>PLATFORM</h4><a href="{{ route('pulse.entry') }}">Pulse Intelligence</a><a href="{{ route('news.index') }}">Market News</a><a href="{{ route('pulse.entry') }}">Research Reports</a><a href="{{ route('tools') }}">Tools &amp; Calculators</a><a href="{{ route('pulse.entry') }}">Alerts &amp; Watchlists</a></nav>
        <nav aria-label="Company links"><h4>COMPANY</h4><a href="{{ route('about') }}">About Us</a><a href="{{ route('pulse.entry') }}#plans">Membership</a><a href="mailto:{{ config('brand.contact_email') }}">Careers</a><a href="{{ route('home') }}#contact">Contact Us</a><a href="{{ route('about') }}">Company Overview</a></nav>
        <nav aria-label="Legal and security links"><h4>LEGAL &amp; SECURITY</h4><a href="{{ route('legal.privacy') }}">Privacy Policy</a><a href="{{ route('legal.terms') }}">Terms of Service</a><a href="{{ route('legal.risk') }}">Risk Disclosure</a><a href="{{ route('legal.disclaimer') }}">Disclaimer</a><a href="{{ route('legal.privacy') }}#section-8-cookies-sessions">Cookie Policy</a></nav>
        <div class="final-footer-subscribe">
            <h4>STAY INFORMED</h4>
            <p>Subscribe to our newsletter for the latest market insights and verified headlines.</p>
            <form method="POST" action="{{ route('newsletter.store') }}">@csrf<input type="email" name="email" aria-label="Email address" placeholder="Enter your email" required><button type="submit">Subscribe</button></form>
            <small>We respect your privacy. Unsubscribe anytime.</small>
        </div>
    </div>

    <div class="container final-footer-bottom">
        <span>© {{ date('Y') }} Alpha Block Solutions. All rights reserved.</span>
        <span>Digital assets involve risk. Information is provided for research and educational purposes only.</span>
    </div>
</footer>
