<?php

namespace App\Http\Controllers;

class LegalController extends Controller
{
    public function privacy(){ return $this->render('privacy'); }
    public function terms(){ return $this->render('terms'); }
    public function risk(){ return $this->render('risk'); }
    public function disclaimer(){ return $this->render('disclaimer'); }

    private function render(string $type)
    {
        $documents = self::documents();
        abort_unless(isset($documents[$type]), 404);
        return view('legal.page', ['document' => $documents[$type], 'type' => $type]);
    }

    public static function documents(): array
    {
        $support = e((string) config('brand.support_email'));

        return [
            'terms' => [
                'title' => 'Terms of Service',
                'summary' => 'These Terms govern access to Alpha Block Solutions and Pulse, including account use, market information, plan access and trading-related features.',
                'effective' => '9 September 2026',
                'sections' => [
                    ['Acceptance of Terms', '<p>By creating an account, accessing Alpha Block Solutions or using Pulse, you agree to these Terms of Service and the Privacy Policy. If you do not agree, do not use the service.</p>'],
                    ['Account Registration', '<p>You must provide accurate account information and keep your login credentials secure. You are responsible for activity performed through your account and should contact support promptly if you believe your account has been compromised.</p>'],
                    ['Platform Access', '<p>Access to Alpha Block Solutions and Pulse is personal, limited, non-exclusive and subject to the features and limits available to your account. We may update, restrict, suspend or discontinue features where reasonably required for security, maintenance, legal compliance or service reliability.</p>'],
                    ['Market Information & Educational Use', '<p>Market prices, charts, headlines, indicators, research, signals and related content are provided for general information, market awareness and educational use. They are not personalized financial, investment, tax or legal advice. You remain responsible for independently evaluating information before making a trading or investment decision.</p>'],
                    ['Economic Calendar & Forecasts', '<p>Economic-event times, previous readings, forecasts, actual releases and simplified crypto-impact notes may come from third-party data providers or administrator-maintained records. Forecasts are estimates, actual values may be delayed or revised, and event timing may change. Crypto-impact notes describe common market transmission channels and are not predictions, recommendations or guarantees.</p>'],
                    ['Rewarded Free Signal', '<p>The anonymous Free Signal feature may offer an optional rewarded advertisement before market intelligence is revealed for the current page session until the visitor refreshes or leaves the page. ABS prioritizes a qualified public Pulse signal. If no setup reaches the qualification threshold, the feature may instead show the highest-scoring Entry Watch from the latest market check, clearly labelled as not a qualified signal. Advertisement availability is not guaranteed. All rewarded outputs are informational only and do not create an advisory relationship or guarantee a trading outcome. Technical controls may limit repeat access by browser, session, timing or other anti-abuse measures.</p>'],
                    ['Risk Notice', '<p>Trading digital assets and derivatives involves substantial risk, including rapid price movement, liquidity risk, leverage risk and the possibility of losing capital. No signal, strategy, model, indicator or platform feature can guarantee a profit or prevent a loss. Past results do not guarantee future performance.</p>'],
                    ['User Responsibilities', '<p>You are responsible for your trading decisions, position sizing, leverage, exchange permissions, risk limits and compliance with laws and regulations that apply to you. You should use only capital you can afford to risk and verify current market conditions before acting on any information displayed by Pulse.</p>'],
                    ['Pulse Package Payments', '<p>Paid Pulse packages are purchased by direct USDT transfer. You are responsible for selecting the stated blockchain network, confirming the receiving address and submitting an accurate transaction reference. Blockchain transfers may be irreversible. Pulse access is activated only after an administrator verifies the submitted payment.</p><p>Package price and duration are shown before payment. Best Signal access is included while an eligible paid package is active and there is no separate points balance or per-signal charge.</p>'],
                    ['Exchange Connections', '<p>If you connect an exchange account, you are responsible for the permissions granted to your API credentials and for maintaining appropriate exchange-side security. Alpha Block Solutions does not require wallet seed phrases or private keys. Never provide them through the platform.</p>'],
                    ['Acceptable Use', '<p>You must not attempt to bypass access controls, interfere with platform operation, introduce malicious software, misuse other users’ information, impersonate another person, use the service unlawfully or attempt unauthorized access to systems, accounts or data.</p>'],
                    ['Suspension or Termination', '<p>Access may be suspended or terminated where necessary to protect users or the platform, respond to suspected misuse, comply with legal obligations, address unpaid or disputed access, or enforce these Terms. Where appropriate, we may provide account information or support options before or after a restriction is applied.</p>'],
                    ['Intellectual Property', '<p>Alpha Block Solutions branding, software, interface design, original content, proprietary indicators, documentation and other protected materials remain the property of Alpha Block Solutions or their respective licensors. These Terms do not transfer ownership rights to users.</p>'],
                    ['Third-Party Services', '<p>Market data, news, blockchain networks, exchanges and other external services may be provided by third parties. Their availability, accuracy, security and terms are outside our direct control. Alpha Block Solutions is not responsible for third-party outages, policy changes or external content.</p>'],
                    ['Disclaimers & Limitation of Liability', '<p>To the extent permitted by applicable law, the service is provided on an “as available” basis without a guarantee that market information, economic releases, forecasts, third-party connections, advertisements or trading-related features will always be uninterrupted, complete, timely or error-free. Alpha Block Solutions does not guarantee profits, signal accuracy, advertisement availability, provider uptime or any market outcome.</p><p>To the extent permitted by applicable law, Alpha Block Solutions is not responsible for trading or investment losses caused by market movement, user decisions, exchange execution, leverage, blockchain activity, third-party data, provider revisions, outages, advertising systems, unauthorized use resulting from compromised credentials or reliance on informational content. Nothing in these Terms excludes liability that cannot legally be excluded.</p>'],
                    ['Changes to These Terms', '<p>We may update these Terms when the service, legal requirements or operating practices change. The effective date shown on this page identifies the current version. Continued use after an update means you accept the revised Terms where permitted by applicable law.</p>'],
                    ['Contact', '<p>For account or Terms-related questions, contact <a href="mailto:'.$support.'">'.$support.'</a>.</p>'],
                ],
            ],
            'privacy' => [
                'title' => 'Privacy Policy',
                'summary' => 'This Policy explains the information Alpha Block Solutions collects, how it is used and the choices available to account holders.',
                'effective' => '9 September 2026',
                'sections' => [
                    ['Information We Collect', '<p>We collect information you provide directly, including your name, email address, account credentials, preferences, support communications and information related to your Pulse access.</p><p>Where you purchase a Pulse package with USDT, we may store the transaction reference, selected network, a snapshot of the receiving address, payment proof you upload and related review records. We do not ask for wallet seed phrases or private keys.</p><p>The public rewarded-signal feature does not require registration. It uses a random browser identifier, timing information and an irreversible server-side hash to enforce the page-session reveal and cooldown. The raw visitor identifier is stored only in the browser cookie.</p>'],
                    ['Usage & Technical Information', '<p>We may collect technical information needed to operate and secure the service, such as IP address, browser or device details, session information, security events, timestamps, application logs and feature activity.</p>'],
                    ['Market & Account Preferences', '<p>We may store watchlists, selected markets, alert preferences, Pulse settings, plan status, trading-tool preferences and related account activity so the service can provide the features you request and maintain continuity between sessions.</p>'],
                    ['How We Use Information', '<p>We use information to create and manage accounts, provide Pulse features, process plan requests, secure the platform, deliver service communications, respond to support requests, maintain audit records, investigate misuse and improve service reliability and user experience.</p>'],
                    ['Account & Security', '<p>We use administrative, technical and application-level safeguards designed to protect account information. Exchange credentials stored by the platform are handled through protected application storage and are not displayed back in full after saving. You are responsible for keeping your password and authentication credentials confidential.</p>'],
                    ['Communications', '<p>We may send account-created messages, plan-request updates, access activation notices, security or service alerts, assigned coupon or gift-voucher notices, and market or Pulse alerts when enabled. Marketing or market-update subscriptions are managed separately from essential account communications.</p>'],
                    ['Payment Information', '<p>USDT transfers are used to purchase eligible Pulse packages. We store only the information required to verify and audit the package payment, such as the transaction reference and any proof you choose or are required to upload. Blockchain transaction information may also be publicly visible on the relevant network. Package access is activated only after Admin verification.</p>'],
                    ['Cookies & Sessions', '<p>We use cookies and similar session technologies that are necessary for authentication, security, preferences and core website operation. The anonymous Free Signal feature uses a first-party browser identifier to enforce its 30-minute cooldown without requiring an account. Rewarded advertising may also use Google advertising technologies according to the consent choices and policies applicable to the visitor. Additional analytics technologies may be introduced where configured and should be described through the platform before use where required by applicable law.</p>'],
                    ['Rewarded Advertising', '<p>Where the Free Signal experience uses Google rewarded advertising, Google or its advertising partners may process advertising identifiers, device/browser information, consent signals and related data according to their own policies and applicable consent requirements. ABS does not receive a user wallet balance or points from these ads; a successful rewarded event only unlocks the single page-session signal experience described on the page.</p>'],
                    ['Sharing of Information', '<p>We may share information with service providers that support hosting, email delivery, security, market data, infrastructure or other operational functions, subject to appropriate access controls. We may also disclose information where required by law or where reasonably necessary to protect users, enforce our terms or defend legal rights.</p><p>Alpha Block Solutions does not use personal account information as a product for sale to advertisers.</p>'],
                    ['Data Retention', '<p>We retain information for as long as reasonably necessary to provide the service, maintain security and audit history, resolve disputes, support accounting or payment verification, and comply with applicable legal obligations. Retention periods may differ by record type.</p>'],
                    ['Your Choices & Rights', '<p>You may update certain account information and communication preferences through available account settings. Depending on applicable law, you may also have rights to request access, correction, deletion, restriction or other action relating to personal information. Some records may need to be retained for security, transaction verification or legal obligations.</p>'],
                    ['International Processing', '<p>Alpha Block Solutions and its service providers may process information in countries other than the country where you are located. Where applicable, reasonable measures should be used to protect information when it is transferred across borders.</p>'],
                    ['Third-Party Services', '<p>Pulse may connect to exchanges, market-data providers, news publishers and other third-party services. Their privacy practices are governed by their own policies. When you follow an external link or connect an external account, review the relevant third party’s terms and privacy information.</p>'],
                    ['Policy Updates', '<p>We may update this Privacy Policy when our services, data practices or legal requirements change. The effective date on this page identifies the current version.</p>'],
                    ['Contact', '<p>For privacy-related questions or account-data requests, contact <a href="mailto:'.$support.'">'.$support.'</a>.</p>'],
                ],
            ],
            'risk' => [
                'title' => 'Digital Asset & Trading Risk Disclosure',
                'summary' => 'Important risks related to digital assets, derivatives, trading signals, automation and exchange connectivity.',
                'effective' => '9 September 2026',
                'sections' => [
                    ['Market Risk', '<p>Digital assets can experience rapid and substantial price changes. Liquidity may fall without warning, spreads can widen and orders may execute at prices different from visible quotes. Losses can increase quickly when leverage is used.</p>'],
                    ['Technology & Exchange Risk', '<p>Blockchains, exchanges, APIs, networks and software can fail, become unavailable or behave unexpectedly. Transactions may be irreversible. Protect passwords, API credentials and authentication methods, and never disclose wallet seed phrases or private keys.</p>'],
                    ['Signal & Automation Risk', '<p>Signals and automated logic can be wrong, delayed or unsuitable for current conditions. Automated actions can repeat an error quickly. Use defined position limits, stop-loss controls, monitoring and exchange-side protections appropriate to your risk tolerance.</p>'],
                    ['Macro & News Event Risk', '<p>CPI, PPI, FOMC, employment, GDP and other macro releases can cause rapid volatility, slippage and reversals. Consensus forecasts can be wrong, source data can be revised, and the market may react differently from common historical patterns. Do not treat an ABS crypto-impact label as a directional forecast.</p>'],
                    ['Regulatory & Counterparty Risk', '<p>Digital-asset rules, exchange availability and tax treatment vary by jurisdiction and can change. Exchanges, custodians and service providers may restrict access, experience losses or cease operations.</p>'],
                    ['No Guaranteed Outcome', '<p>No market insight, signal, strategy, model, research item or Pulse feature can guarantee profit or prevent loss. You remain responsible for each trading decision and should seek independent professional advice where appropriate.</p>'],
                ],
            ],
            'disclaimer' => [
                'title' => 'Market Data & Content Disclaimer',
                'summary' => 'Important limits on market data, external headlines, indicators and Alpha Block Solutions market content.',
                'effective' => '9 September 2026',
                'sections' => [
                    ['Market Data', '<p>Prices, market statistics and chart data may be obtained from public or third-party providers. Information can be delayed, unavailable or different from the price displayed by a user’s exchange.</p>'],
                    ['External Headlines', '<p>External headlines are attributed to their source and link to the original publisher when available. Alpha Block Solutions does not control external articles, publication timing, availability or later corrections.</p>'],
                    ['ABS Content', '<p>Original Alpha Block Solutions articles, market observations and Pulse outputs are intended for general market awareness and education. They are not personalized financial advice.</p>'],
                    ['Indicators & Signals', '<p>Indicators, scores and signals are analytical outputs based on available data and configured rules. They should not be used as a standalone reason to enter or exit a trade.</p>'],
                    ['Economic Calendar', '<p>Event times, previous values, forecasts and actual values may be supplied by third parties and can be delayed, changed or revised. ABS does not warrant that every release is complete or immediately updated. Users should verify important releases with an authoritative source before making a decision.</p>'],
                    ['Crypto Impact Notes', '<p>Labels such as supportive, pressure, volatile or mixed are simplified educational context based on common relationships between inflation, interest rates, bond yields, the US dollar and risk appetite. They are not predictions and should not be interpreted as buy, sell, long or short recommendations.</p>'],
                    ['Rewarded Advertising & Free Signals', '<p>Rewarded advertisements are optional and may not be available on every device, browser, region or visit. A rewarded reveal may be a qualified public Pulse signal or, when no setup reaches the qualification threshold, a clearly labelled Entry Watch. An Entry Watch is not a signal or trade recommendation. Rewarded outputs remain visible until the visitor refreshes or leaves the page and do not guarantee accuracy, execution quality or profit. The advertisement provider and publishers operate under their own systems and policies.</p>'],
                    ['No Professional Relationship', '<p>Using Alpha Block Solutions does not create a fiduciary, broker, dealer, investment-adviser, financial-planner, legal, tax or other professional-client relationship. Users should obtain independent professional advice where appropriate.</p>'],
                ],
            ],
        ];
    }
}
