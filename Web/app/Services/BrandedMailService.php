<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\EmailDeliveryLog;
use App\Models\PulseAlert;
use App\Models\PulseMembershipRequest;
use App\Models\PulsePromotionCode;
use App\Models\PulseSignal;
use App\Models\SiteSetting;
use App\Models\PulseSystemSetting;
use App\Models\User;
use App\Models\UserServiceAccess;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BrandedMailService
{
    public function accountActivation(User $user, string $activationUrl): void
    {
        $this->send($user->email, 'Activate your Alpha Block Solutions account', [
            'eyebrow' => 'SECURE ACCOUNT ACTIVATION',
            'title' => 'Activate your ABS account',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'Your Alpha Block Solutions account has been created successfully.',
            'body' => 'Activate your email address before signing in. This secure activation link expires after 24 hours.',
            'bullets' => [
                'One secure identity for Alpha Block Solutions services.',
                'Your account securely connects the ABS services and features available to you.',
                'Eligible accounts receive Pulse Trial access only after activation.',
            ],
            'buttonText' => 'Activate ABS Account',
            'buttonUrl' => $activationUrl,
            'notice' => 'If you did not create this account, you can safely ignore this email or contact '.config('brand.support_email').'.',
        ], 'account_activation', $user);
    }

    public function adminNewRegistration(User $user, string $source = 'web'): void
    {
        if (! $this->siteSettingEnabled('admin_notify_new_registration', true)) return;
        $recipient = $this->adminNotificationEmail();
        if (! $recipient) return;

        $this->send($recipient, 'New ABS user registration: '.$user->email, [
            'eyebrow' => 'ADMIN EVENT ALERT',
            'title' => 'New ABS user registration',
            'greeting' => 'Hello Admin,',
            'intro' => 'A new user has registered with Alpha Block Solutions.',
            'body' => 'Review the account from User Management if any action is required. The user must still complete the normal activation flow before account access becomes active.',
            'facts' => array_filter([
                'Name' => $user->name,
                'Email' => $user->email,
                'Phone' => trim(((string) $user->country_code).' '.((string) $user->phone)) ?: null,
                'Country' => $user->country,
                'Account status' => ucfirst((string) $user->status),
                'Registration source' => $source === 'mobile_api' ? 'Mobile API / Flutter' : 'Website',
                'Registered at' => $user->created_at?->format('d M Y H:i:s'),
            ]),
            'buttonText' => 'Review User',
            'buttonUrl' => route('admin.users.show', $user),
            'notice' => 'This is an administrator event notification. Change the recipient or disable this alert from Admin → Email Communications.',
        ], 'admin_new_user_registration', $user, ['source' => $source]);
    }

    public function adminNewSubscription(PulseMembershipRequest $request, string $source = 'web'): void
    {
        if (! $this->siteSettingEnabled('admin_notify_new_subscription', true)) return;
        $recipient = $this->adminNotificationEmail();
        if (! $recipient) return;

        $request->loadMissing(['user', 'plan', 'promotion']);
        if (! $request->user) return;

        $this->send($recipient, 'New Pulse package subscription: '.($request->plan?->name ?? 'Pulse plan'), [
            'eyebrow' => 'ADMIN SUBSCRIPTION ALERT',
            'title' => 'New Pulse package subscription',
            'greeting' => 'Hello Admin,',
            'intro' => $request->user->name.' submitted a Pulse package payment for verification.',
            'body' => $request->status === 'approved'
                ? 'The request was automatically approved by the existing ABS membership rules. Review the subscription if needed.'
                : 'Verify the submitted USDT transaction. When approved, the selected Pulse package is activated for the approved access period.',
            'facts' => array_filter([
                'User' => $request->user->name,
                'Email' => $request->user->email,
                'Plan' => $request->plan?->name ?? 'Pulse plan',
                'Amount' => number_format((float) $request->final_amount, 2).' '.$request->currency,
                'Status' => ucfirst(str_replace('_', ' ', (string) $request->status)),
                'Request' => '#'.$request->id,
                'Payment reference' => $request->payment_reference,
                'Promotion' => $request->promotion_code_snapshot,
                'Source' => $source === 'mobile_api' ? 'Mobile API / Flutter' : 'Website',
                'Submitted at' => $request->created_at?->format('d M Y H:i:s'),
            ]),
            'buttonText' => 'Verify Package Payment',
            'buttonUrl' => route('admin.pulse.memberships', ['q' => $request->user->email]),
            'notice' => 'This is an administrator event notification. Change the recipient or disable this alert from Admin → Email Communications.',
        ], 'admin_new_package_subscription', $request->user, ['source' => $source, 'membership_request_id' => $request->id]);
    }

    public function welcome(User $user, bool $trialActive = false): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $this->send($user->email, 'Welcome to Alpha Block Solutions', [
            'eyebrow' => 'ACCOUNT READY',
            'title' => 'Welcome to Alpha Block Solutions',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'Your ABS account is active and ready.',
            'body' => 'You can now explore live markets, verified market intelligence and the Pulse tools available to your account.',
            'bullets' => array_values(array_filter([
                'Follow live market movement and selected verified headlines.',
                'Review signal evidence, trade levels and risk context before making a decision.',
                $trialActive ? 'Your Pulse Trial access is ready to use.' : 'Available Pulse plans can be reviewed from your account.',
            ])),
            'buttonText' => $trialActive ? 'Open Pulse' : 'Open Your ABS Account',
            'buttonUrl' => $trialActive ? route('pulse.entry') : route('dashboard'),
            'notice' => 'Market conditions can change quickly. ABS provides decision support, not guaranteed outcomes.',
        ], 'welcome', $user);
    }

    public function accountCreatedByAdmin(User $user, ?UserServiceAccess $access = null): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $access?->loadMissing('plan');
        $facts = [];
        if ($access?->plan?->name) $facts['Pulse plan'] = $access->plan->name;
        if ($access?->starts_at) $facts['Access starts'] = $access->starts_at->format('d M Y');
        if ($access?->ends_at) $facts['Access until'] = $access->ends_at->format('d M Y');
        $this->send($user->email, 'Your Alpha Block Solutions account is ready', [
            'eyebrow' => 'ACCOUNT READY',
            'title' => 'Your ABS account is ready',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'An Alpha Block Solutions administrator has prepared your account.',
            'body' => 'Use the password shared with you through the agreed secure channel. Passwords are never included in ABS email messages.',
            'facts' => $facts,
            'buttonText' => 'Sign In',
            'buttonUrl' => route('login'),
            'notice' => 'If you were not expecting this account, contact '.config('brand.support_email').'.',
        ], 'admin_account_created', $user);
    }

    public function passwordReset(User $user, string $resetUrl): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $this->send($user->email, 'Reset your Alpha Block Solutions password', [
            'eyebrow' => 'SECURE PASSWORD RECOVERY',
            'title' => 'Reset your ABS password',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'We received a request to reset the password for your Alpha Block Solutions account.',
            'body' => 'Use the secure button below to choose a new password. The recovery link is time-limited and can only be used with the matching account.',
            'buttonText' => 'Reset Password',
            'buttonUrl' => $resetUrl,
            'notice' => 'If you did not request a password reset, no action is required. Do not share this link with anyone.',
        ], 'password_reset', $user);
    }

    public function passwordChanged(User $user, bool $byAdmin = false): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $this->send($user->email, 'Security update for your ABS account', [
            'eyebrow' => 'ACCOUNT SECURITY',
            'title' => 'Your account password was updated',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => $byAdmin ? 'An Alpha Block Solutions administrator updated your account password.' : 'The password for your Alpha Block Solutions account was changed successfully.',
            'body' => 'For your security, passwords are never displayed or sent by email.',
            'buttonText' => 'Sign In to ABS',
            'buttonUrl' => route('login'),
            'notice' => 'If you did not expect this change, contact '.config('brand.support_email').' immediately and secure your account.',
        ], $byAdmin ? 'password_changed_admin' : 'password_changed', $user);
    }

    public function passwordChangedByAdmin(User $user): void
    {
        $this->passwordChanged($user, true);
    }

    public function accountIdentifierReminder(User $user): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $this->send($user->email, 'Your Alpha Block Solutions sign-in details', [
            'eyebrow' => 'ACCOUNT RECOVERY',
            'title' => 'Your ABS sign-in email',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'We received a request to recover the sign-in details for your Alpha Block Solutions account.',
            'body' => 'Use the email address below when signing in or requesting a password reset.',
            'facts' => ['Sign-in email' => $user->email],
            'buttonText' => 'Sign In to ABS',
            'buttonUrl' => route('login'),
            'notice' => 'If you did not request this reminder, no action is required. Your password has not been changed.',
        ], 'account_identifier_reminder', $user);
    }

    public function planRequestReceived(PulseMembershipRequest $request): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $request->loadMissing(['user', 'plan']);
        if (! $request->user) return;
        $this->send($request->user->email, 'We received your Pulse plan request', [
            'eyebrow' => 'PLAN REQUEST RECEIVED',
            'title' => 'Your request is being reviewed',
            'greeting' => 'Hi '.$this->firstName($request->user).',',
            'intro' => 'We received your request for '.($request->plan?->name ?? 'Pulse access').'.',
            'body' => 'Submitted payment details will be reviewed before access is activated. You can track the request from your account.',
            'facts' => [
                'Request' => '#'.$request->id,
                'Plan' => $request->plan?->name ?? 'Pulse plan',
                'Amount' => number_format((float) $request->final_amount, 2).' '.$request->currency,
                'Status' => 'Submitted for verification',
            ],
            'buttonText' => 'View Plan Requests',
            'buttonUrl' => route('pulse.membership.index'),
            'notice' => 'Keep the USDT transaction reference available until Admin verification is complete. Your selected Pulse package activates only after approval.',
        ], 'plan_request_received', $request->user);
    }

    public function planActivated(PulseMembershipRequest $request): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $request->loadMissing(['user', 'plan']);
        if (! $request->user) return;
        $this->send($request->user->email, 'Your Pulse access is active', [
            'eyebrow' => 'ACCESS ACTIVATED',
            'title' => 'Your Pulse plan is ready',
            'greeting' => 'Hi '.$this->firstName($request->user).',',
            'intro' => 'Your '.($request->plan?->name ?? 'Pulse').' access has been activated.',
            'body' => 'Sign in to review current market intelligence, signals, risk information and the trading tools included with your plan.',
            'facts' => array_filter([
                'Plan' => $request->plan?->name ?? 'Pulse plan',
                'Access period' => max(1, (int) $request->activation_days).' days',
                'Status' => 'Active',
                'Approval note' => $request->admin_notes ?: null,
            ]),
            'buttonText' => 'Open Pulse',
            'buttonUrl' => route('pulse.dashboard'),
            'notice' => 'No market signal or strategy can guarantee a profit or prevent a loss. Review every setup and keep risk within your limits.',
        ], 'plan_activated', $request->user);
    }

    public function planRequestDeclined(PulseMembershipRequest $request): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $request->loadMissing(['user', 'plan']);
        if (! $request->user) return;
        $this->send($request->user->email, 'Update on your Pulse plan request', [
            'eyebrow' => 'PLAN REQUEST UPDATE',
            'title' => 'Your request needs attention',
            'greeting' => 'Hi '.$this->firstName($request->user).',',
            'intro' => 'We could not activate your request for '.($request->plan?->name ?? 'Pulse access').' at this time.',
            'body' => $request->admin_notes ? 'Review note: '.$request->admin_notes : 'Please review your submitted details or contact support if you would like assistance.',
            'buttonText' => 'Review Your Requests',
            'buttonUrl' => route('pulse.membership.index'),
            'notice' => 'For account assistance, contact '.config('brand.support_email').'.',
        ], 'plan_request_declined', $request->user);
    }

    public function accessUpdated(User $user, ?UserServiceAccess $access, string $context = 'updated'): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $access?->loadMissing('plan');
        $status = $access?->isActive() ? 'Active' : ucfirst((string) ($access?->status ?: 'Updated'));
        $facts = ['Status' => $status];
        if ($access?->plan?->name) $facts['Plan'] = $access->plan->name;
        if ($access?->ends_at) $facts['Access until'] = $access->ends_at->format('d M Y');
        $this->send($user->email, 'Your Pulse access has been updated', [
            'eyebrow' => 'ACCOUNT ACCESS UPDATE',
            'title' => $context === 'renewed' ? 'Pulse access renewed' : 'Pulse access update',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => $context === 'renewed' ? 'Your Pulse access period has been extended.' : 'Your Pulse access settings have been updated.',
            'body' => 'Your account reflects the latest plan status and permissions. Sign in to review the features currently available to you.',
            'facts' => $facts,
            'buttonText' => 'Open Your Account',
            'buttonUrl' => route('pulse.entry'),
            'notice' => 'If you did not expect this change, contact '.config('brand.support_email').'.',
        ], $context === 'renewed' ? 'access_renewed' : 'access_updated', $user);
    }

    public function planExpiryReminder(User $user, UserServiceAccess $access, int $daysLeft): void
    {
        if (! $this->enabled('expiry_emails_enabled', true) || ! $this->userPrefers($user, 'plan_expiry', true)) return;
        $access->loadMissing('plan');
        $plan = $access->plan?->name ?? 'Pulse access';
        $subject = $daysLeft <= 0 ? 'Your Pulse access expires today' : "Your Pulse access expires in {$daysLeft} day".($daysLeft === 1 ? '' : 's');
        $this->send($user->email, $subject, [
            'eyebrow' => 'PLAN EXPIRY REMINDER',
            'title' => $daysLeft <= 0 ? 'Your Pulse access expires today' : 'Your Pulse access is nearing expiry',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => $daysLeft <= 0 ? "Your {$plan} access is scheduled to end today." : "Your {$plan} access is scheduled to end in {$daysLeft} day".($daysLeft === 1 ? '' : 's').'.',
            'body' => 'Review your membership before expiry if you want uninterrupted access to eligible Pulse intelligence and tools.',
            'facts' => array_filter(['Plan' => $plan, 'Access until' => $access->ends_at?->format('d M Y H:i')]),
            'buttonText' => 'Review Pulse Plans',
            'buttonUrl' => route('pulse.membership.index'),
            'notice' => 'Renewal is never automatic unless explicitly configured and approved for your account.',
        ], 'plan_expiry_'.$daysLeft.'d', $user, ['access_id' => $access->id, 'days_left' => $daysLeft]);
    }

    public function planExpired(User $user, UserServiceAccess $access): void
    {
        if (! $this->enabled('expiry_emails_enabled', true) || ! $this->userPrefers($user, 'plan_expiry', true)) return;
        $access->loadMissing('plan');
        $this->send($user->email, 'Your Pulse access has expired', [
            'eyebrow' => 'ACCESS EXPIRED',
            'title' => 'Your Pulse plan access has ended',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'Your '.($access->plan?->name ?? 'Pulse').' access period has ended.',
            'body' => 'Your ABS account remains available. Review current Pulse plans if you want to restore eligible Pulse features.',
            'buttonText' => 'Review Pulse Plans',
            'buttonUrl' => route('pulse.membership.index'),
            'notice' => 'Expired access does not place, cancel or modify any exchange order or position.',
        ], 'plan_expired', $user, ['access_id' => $access->id]);
    }

    public function promotionAssigned(PulsePromotionCode $promotion): void
    {
        if (! $this->enabled('promotion_emails_enabled', true)) return;
        $promotion->loadMissing(['assignee', 'plan']);
        if (! $promotion->assignee || ! $promotion->is_active) return;
        $this->send($promotion->assignee->email, 'A Pulse offer has been added to your account', [
            'eyebrow' => $promotion->type === 'gift_voucher' ? 'GIFT VOUCHER' : 'ACCOUNT OFFER',
            'title' => 'A Pulse offer is available',
            'greeting' => 'Hi '.$this->firstName($promotion->assignee).',',
            'intro' => 'A '.($promotion->type === 'gift_voucher' ? 'gift voucher' : 'coupon').' has been assigned directly to your Alpha Block Solutions account.',
            'body' => 'Sign in to review the offer and apply it to an eligible Pulse plan.',
            'facts' => array_filter([
                'Code' => $promotion->code,
                'Benefit' => $promotion->displayBenefit(),
                'Plan' => $promotion->plan?->name,
                'Valid until' => $promotion->valid_until?->format('d M Y'),
            ]),
            'buttonText' => 'View My Offers',
            'buttonUrl' => route('pulse.membership.index'),
            'notice' => 'Account-specific codes are linked to your signed-in ABS account and cannot be transferred to another user.',
        ], 'promotion_assigned', $promotion->assignee);
    }

    public function pulseAlert(User $user, PulseAlert $alert): void
    {
        if (! $this->enabled('pulse_alert_emails_enabled', true)) return;
        $prefKey = match ($alert->type) {
            'trade' => 'trades',
            'risk' => 'risk',
            'market' => 'market',
            default => 'system',
        };
        if (! $this->userPrefers($user, $prefKey, true)) return;
        $this->send($user->email, 'Pulse alert: '.$alert->title, [
            'eyebrow' => strtoupper(str_replace('_', ' ', $alert->type ?: 'PULSE ALERT')),
            'title' => $alert->title ?: 'Pulse account alert',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => $alert->message,
            'body' => 'Sign in to Pulse for the latest account context and any related action.',
            'buttonText' => $alert->action_url ? 'View in Pulse' : 'Open Pulse',
            'buttonUrl' => $this->safeActionUrl($alert->action_url),
            'notice' => in_array($alert->type, ['market', 'risk', 'trade'], true)
                ? 'Market information can change quickly. Confirm current prices, position details and risk before taking action.'
                : 'This message was sent as an Alpha Block Solutions account alert.',
        ], 'pulse_'.$prefKey.'_alert', $user, ['alert_id' => $alert->id, 'alert_type' => $alert->type]);
    }

    public function qualifiedSignal(User $user, PulseSignal $signal): void
    {
        if (! $this->enabled('signal_email_alerts_enabled', true) || ! $this->userPrefers($user, 'signals', true)) return;
        $this->send($user->email, 'Pulse qualified signal: '.$signal->symbol.' '.$signal->direction, [
            'eyebrow' => 'QUALIFIED PULSE SIGNAL',
            'title' => $signal->symbol.' · '.$signal->direction,
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'Pulse identified a qualified setup that meets your configured signal threshold.',
            'body' => 'Review the full strategy evidence, current price, expiry, risk controls and execution readiness inside Pulse before taking any action.',
            'facts' => array_filter([
                'Symbol' => $signal->symbol,
                'Direction' => $signal->direction,
                'Score' => is_numeric($signal->score) ? number_format((float) $signal->score, 1).'/100' : null,
                'Timeframe' => $signal->timeframe,
                'Entry' => $signal->entry_price,
                'Stop loss' => $signal->stop_loss,
                'Take profit' => $signal->take_profit,
                'Expires' => $signal->expires_at?->format('d M Y H:i'),
            ]),
            'buttonText' => 'Review Signal in Pulse',
            'buttonUrl' => route('pulse.signals.show', $signal),
            'notice' => 'A signal is not a guarantee or personalized financial advice. Confirm live conditions and size risk appropriately.',
        ], 'qualified_signal', $user, ['signal_id' => $signal->id, 'symbol' => $signal->symbol]);
    }

    public function tradeAlert(User $user, PulseAlert $alert): void
    {
        if (! $this->enabled('trade_email_alerts_enabled', false)) return;
        if (! $this->userPrefers($user, 'trades', true)) return;
        $this->pulseAlert($user, $alert);
    }

    public function newsletterSubscribed(string $email): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $this->send($email, 'You are subscribed to ABS market updates', [
            'eyebrow' => 'MARKET UPDATES',
            'title' => 'Subscription confirmed',
            'greeting' => 'Hello,',
            'intro' => 'You are now subscribed to selected Alpha Block Solutions market-awareness and product updates.',
            'body' => 'Messages are designed to surface relevant developments and meaningful Pulse updates without unnecessary noise.',
            'buttonText' => 'Explore Alpha Block Solutions',
            'buttonUrl' => route('home'),
            'notice' => 'Market updates are informational and are not personalized financial advice.',
        ], 'newsletter_confirmation');
    }

    public function contactAcknowledgement(ContactMessage $contact): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $this->send($contact->email, 'We received your message', [
            'eyebrow' => 'ABS SUPPORT',
            'title' => 'Your message has been received',
            'greeting' => 'Hi '.(trim($contact->name) ?: 'there').',',
            'intro' => 'Thank you for contacting Alpha Block Solutions.',
            'body' => 'Your enquiry has been recorded and can be reviewed by the appropriate team. Keep the reference below if you need to follow up.',
            'facts' => ['Reference' => '#'.$contact->id, 'Subject' => $contact->subject, 'Category' => ucfirst($contact->category)],
            'buttonText' => 'Visit Alpha Block Solutions',
            'buttonUrl' => route('home'),
            'notice' => 'Never send passwords, private keys, seed phrases or exchange API secrets by email or support form.',
        ], 'contact_acknowledgement', $contact->user, ['contact_message_id' => $contact->id]);
    }

    public function dailyMarketBrief(User $user, array $market): void
    {
        if (! $this->enabled('daily_market_brief_enabled', false) || ! $this->userPrefers($user, 'daily_brief', false)) return;
        $global = (array) ($market['global'] ?? []);
        $btc = collect($market['core'] ?? [])->firstWhere('symbol', 'BTCUSDT') ?: [];
        $this->send($user->email, 'ABS Daily Market Brief', [
            'eyebrow' => 'DAILY MARKET BRIEF',
            'title' => 'Today’s digital-asset market snapshot',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'A concise daily view of the market context monitored by Alpha Block Solutions.',
            'body' => 'Use this brief as a starting point, then confirm live conditions inside ABS before making any trading decision.',
            'facts' => array_filter([
                'BTC/USDT' => is_numeric($btc['price'] ?? null) ? '$'.number_format((float) $btc['price'], 2) : null,
                'BTC 24H' => is_numeric($btc['change_percent'] ?? null) ? number_format((float) $btc['change_percent'], 2).'%' : null,
                'Market Cap' => $this->formatUsd($global['market_cap_usd'] ?? $global['total_market_cap_usd'] ?? null),
                '24H Volume' => $this->formatUsd($global['volume_24h_usd'] ?? $global['total_volume_24h_usd'] ?? null),
                'BTC Dominance' => is_numeric($global['btc_dominance'] ?? null) ? number_format((float) $global['btc_dominance'], 1).'%' : null,
                'Fear & Greed' => is_numeric($global['fear_greed_score'] ?? null) ? (string) round((float) $global['fear_greed_score']).' '.($global['fear_greed_label'] ?? '') : null,
            ]),
            'buttonText' => 'Open Market Intelligence',
            'buttonUrl' => route('home'),
            'notice' => 'Market data can change rapidly and may be delayed by upstream providers. This brief is informational only.',
        ], 'daily_market_brief', $user);
    }

    public function testEmail(string $email): void
    {
        $this->send($email, 'ABS email system test', [
            'eyebrow' => 'DELIVERY TEST',
            'title' => 'ABS email delivery is connected',
            'greeting' => 'Hello,',
            'intro' => 'This is a production-readiness test from Alpha Block Solutions.',
            'body' => 'If you can read this message, the application completed an outbound branded-email delivery attempt using the currently configured mail transport.',
            'facts' => ['Environment' => (string) config('app.env'), 'Application URL' => (string) config('app.url'), 'Sent at' => now()->toDateTimeString()],
            'notice' => 'For production, also verify SPF, DKIM, DMARC, sender reputation and inbox/spam placement at your mail provider.',
        ], 'system_test');
    }

    private function send(string $email, string $subject, array $data, string $event = 'general', ?User $user = null, array $metadata = []): void
    {
        $email = strtolower(trim($email));
        if ($email === '') return;

        $payload = array_merge([
            'eyebrow' => 'ALPHA BLOCK SOLUTIONS',
            'title' => $subject,
            'greeting' => 'Hello,',
            'intro' => '',
            'body' => '',
            'bullets' => [],
            'facts' => [],
            'buttonText' => null,
            'buttonUrl' => null,
            'notice' => null,
            'supportEmail' => config('brand.support_email'),
            'companyName' => config('brand.company_name', 'Alpha Block Solutions'),
            'websiteUrl' => config('brand.website') ?: config('app.url'),
        ], $data);

        $log = $this->createLog($email, $subject, $event, $user, $metadata);
        try {
            Mail::send('emails.branded', $payload, function (Message $message) use ($email, $subject): void {
                $message->to($email)->subject($subject);
                if (config('brand.support_email')) $message->replyTo(config('brand.support_email'));
            });
            if ($log) $log->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
        } catch (Throwable $e) {
            if ($log) $log->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 2000)]);
            Log::warning('ABS email delivery failed', ['event' => $event, 'recipient' => $email, 'subject' => $subject, 'error' => $e->getMessage()]);
        }
    }

    private function createLog(string $email, string $subject, string $event, ?User $user, array $metadata): ?EmailDeliveryLog
    {
        try {
            if (! Schema::hasTable('email_delivery_logs')) return null;
            return EmailDeliveryLog::create([
                'user_id' => $user?->id,
                'event' => $event,
                'recipient_email' => $email,
                'subject' => $subject,
                'status' => 'queued',
                'metadata' => $metadata ?: null,
            ]);
        } catch (Throwable $e) {
            Log::notice('ABS email logging unavailable', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function adminNotificationEmail(): ?string
    {
        try {
            if (! Schema::hasTable('site_settings')) return 'i@armansabir.com';
            $email = trim((string) SiteSetting::query()->where('key', 'admin_notification_email')->value('value'));
            if ($email === '') $email = 'i@armansabir.com';
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null;
        } catch (Throwable) {
            return 'i@armansabir.com';
        }
    }

    private function siteSettingEnabled(string $key, bool $default): bool
    {
        try {
            if (! Schema::hasTable('site_settings')) return $default;
            $value = SiteSetting::query()->where('key', $key)->value('value');
            return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
        } catch (Throwable) {
            return $default;
        }
    }

    private function enabled(string $key, bool $default): bool
    {
        try {
            if (! Schema::hasTable('pulse_system_settings')) return $default;
            return (bool) PulseSystemSetting::value($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }

    private function userPrefers(User $user, string $key, bool $default): bool
    {
        try {
            $preferences = $user->pulseSettings?->notification_preferences ?: [];
            return array_key_exists($key, $preferences) ? (bool) $preferences[$key] : $default;
        } catch (Throwable) {
            return $default;
        }
    }

    private function firstName(User $user): string
    {
        $name = trim((string) $user->name);
        return $name === '' ? 'there' : explode(' ', $name)[0];
    }

    private function safeActionUrl(?string $actionUrl): string
    {
        if (! $actionUrl) return route('pulse.entry');
        if (str_starts_with($actionUrl, '/')) return url($actionUrl);
        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl !== '' && str_starts_with($actionUrl, $appUrl)) return $actionUrl;
        return route('pulse.entry');
    }

    private function formatUsd(mixed $value): ?string
    {
        if (! is_numeric($value)) return null;
        $number = (float) $value;
        if ($number >= 1_000_000_000_000) return '$'.number_format($number / 1_000_000_000_000, 2).'T';
        if ($number >= 1_000_000_000) return '$'.number_format($number / 1_000_000_000, 2).'B';
        if ($number >= 1_000_000) return '$'.number_format($number / 1_000_000, 2).'M';
        return '$'.number_format($number, 0);
    }
}
