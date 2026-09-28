<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\MonthlyStatement;
use App\Models\PortfolioAccount;
use App\Models\PortfolioRequest;
use App\Models\PortfolioTransaction;
use App\Models\PortfolioInvestmentTerm;
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
        $benefits = $request->plan ? app(PulseMembershipService::class)->planHighlights($request->plan) : [];

        $this->send($recipient, 'Pulse payment awaiting verification — '.($request->plan?->name ?? 'Pulse plan'), [
            'eyebrow' => 'PAYMENT VERIFICATION REQUIRED',
            'title' => 'A Pulse payment is ready for review',
            'greeting' => 'Hello Admin,',
            'intro' => $request->user->name.' has submitted a direct USDT payment for '.($request->plan?->name ?? 'Pulse access').'.',
            'body' => 'Verify the transaction reference against the configured wallet and network. Approving the request activates the selected package for the recorded access period.',
            'bullets' => $benefits ? array_map(fn ($item) => 'Package: '.$item, array_slice($benefits, 0, 4)) : [],
            'facts' => array_filter([
                'Member' => $request->user->name.' · '.$request->user->email,
                'Plan' => $request->plan?->name ?? 'Pulse plan',
                'Amount' => number_format((float) $request->final_amount, 2).' '.$request->currency,
                'Network' => $request->network,
                'Transaction ID' => $request->payment_reference,
                'Payment proof' => $request->payment_proof_path ? 'Attached to request' : 'Not provided',
                'Member note' => $request->user_notes,
                'Request' => '#'.$request->id,
                'Source' => $source === 'mobile_api' ? 'ABS Pulse mobile' : 'ABS Pulse web',
                'Submitted at' => $request->created_at?->format('d M Y H:i:s'),
            ]),
            'buttonText' => 'Review Payment',
            'buttonUrl' => route('admin.pulse.memberships', ['q' => $request->user->email]),
            'notice' => 'Confirm the transaction independently before approval. The member remains in verification-pending status until the request is approved.',
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
        $benefits = $request->plan ? app(PulseMembershipService::class)->planHighlights($request->plan) : [];
        $this->send($request->user->email, 'Payment received — Pulse verification pending', [
            'eyebrow' => 'PAYMENT SUBMITTED',
            'title' => 'Your payment is awaiting verification',
            'greeting' => 'Hi '.$this->firstName($request->user).',',
            'intro' => 'We received your payment details for '.($request->plan?->name ?? 'Pulse access').'.',
            'body' => 'Your transaction is now queued for verification. Once the USDT transfer is confirmed, your package will be activated and you will receive a separate access-confirmation email.',
            'bullets' => $benefits,
            'facts' => array_filter([
                'Request' => '#'.$request->id,
                'Plan' => $request->plan?->name ?? 'Pulse plan',
                'Amount' => number_format((float) $request->final_amount, 2).' '.$request->currency,
                'Network' => $request->network,
                'Transaction ID' => $request->payment_reference,
                'Access after approval' => max(1, (int) $request->activation_days).' days',
                'Status' => 'Awaiting verification',
            ]),
            'buttonText' => 'Track Payment Status',
            'buttonUrl' => route('pulse.membership.index'),
            'notice' => 'No further action is required unless the ABS team requests additional information. Keep your transaction reference for your records.',
        ], 'plan_request_received', $request->user, ['membership_request_id' => $request->id]);
    }

    public function planActivated(PulseMembershipRequest $request): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $request->loadMissing(['user', 'plan', 'activatedAccess']);
        if (! $request->user) return;
        $benefits = $request->plan ? app(PulseMembershipService::class)->planHighlights($request->plan) : [];
        $this->send($request->user->email, 'Pulse access activated — '.($request->plan?->name ?? 'ABS Pulse'), [
            'eyebrow' => 'ACCESS ACTIVATED',
            'title' => 'Your Pulse package is active',
            'greeting' => 'Hi '.$this->firstName($request->user).',',
            'intro' => 'Your '.($request->plan?->name ?? 'Pulse').' payment has been verified and your access is now active.',
            'body' => 'You can sign in immediately and use the market intelligence, signal and account features included with your package.',
            'bullets' => $benefits,
            'facts' => array_filter([
                'Plan' => $request->plan?->name ?? 'Pulse plan',
                'Payment request' => '#'.$request->id,
                'Access period' => max(1, (int) $request->activation_days).' days',
                'Access until' => $request->activatedAccess?->ends_at?->format('d M Y H:i'),
                'Status' => 'Active',
                'Approval note' => $request->admin_notes ?: null,
            ]),
            'buttonText' => 'Open ABS Pulse',
            'buttonUrl' => route('pulse.dashboard'),
            'notice' => 'Pulse market intelligence supports decision-making but does not guarantee a trading outcome. Review each setup and manage risk independently.',
        ], 'plan_activated', $request->user, ['membership_request_id' => $request->id]);
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

    public function investorTransactionPosted(User $user, PortfolioAccount $account, PortfolioTransaction $transaction): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $account->loadMissing('investmentTerm');
        $term = $account->investmentTerm;
        $label = match ($transaction->type) {
            'deposit' => 'Investment added',
            'withdrawal' => 'Withdrawal recorded',
            'profit' => 'Portfolio profit recorded',
            'loss' => 'Portfolio loss recorded',
            'fee' => 'Portfolio fee recorded',
            default => 'Portfolio adjustment recorded',
        };
        $this->send($user->email, $label.' — ABS Private Investor', [
            'eyebrow' => 'PRIVATE INVESTOR ACCOUNT ACTIVITY',
            'title' => $label,
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'A confirmed entry has been posted to your private investor portfolio.',
            'body' => 'Your portfolio dashboard and transaction ledger now reflect this activity. Published monthly statements remain the official month-end record.',
            'facts' => array_filter([
                'Activity' => ucfirst((string) $transaction->type),
                'Amount' => $account->currency.' '.number_format((float) $transaction->amount, 2),
                'Transaction date' => $transaction->transaction_date?->format('d M Y'),
                'Reference' => $transaction->reference,
                'Agreed monthly rate' => $transaction->type === 'deposit' && $term ? number_format((float)$term->monthly_target_rate,2).'%' : null,
                'Performance starts' => $transaction->type === 'deposit' && $term ? $term->effective_from?->format('d M Y') : null,
                'Portfolio value' => $account->currency.' '.number_format((float) $account->fresh()->current_value, 2),
                'Description' => $transaction->description,
            ]),
            'buttonText' => 'Review Portfolio Activity',
            'buttonUrl' => route('private.transactions'),
            'notice' => 'If you do not recognize this portfolio activity, contact ABS Support from your account.',
        ], 'private_investor_transaction_posted', $user, ['portfolio_transaction_id'=>$transaction->id,'portfolio_account_id'=>$account->id]);
    }

    public function investorTransactionVoided(User $user, PortfolioAccount $account, PortfolioTransaction $transaction): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $this->send($user->email, 'Portfolio entry corrected — ABS Private Investor', [
            'eyebrow' => 'PRIVATE INVESTOR ACCOUNT CORRECTION',
            'title' => 'A portfolio entry was corrected',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'A previously posted portfolio entry has been voided and its balance effect reversed.',
            'body' => 'The original entry remains visible in your activity history as a corrected record so your account audit trail stays complete.',
            'facts' => array_filter([
                'Original activity' => ucfirst((string) $transaction->type),
                'Original amount' => $account->currency.' '.number_format((float) $transaction->amount, 2),
                'Original date' => $transaction->transaction_date?->format('d M Y'),
                'Correction reason' => $transaction->void_reason,
                'Corrected portfolio value' => $account->currency.' '.number_format((float) $account->fresh()->current_value, 2),
            ]),
            'buttonText' => 'Review Transaction History',
            'buttonUrl' => route('private.transactions'),
            'notice' => 'For questions about this correction, open Pulse Support from your ABS account.',
        ], 'private_investor_transaction_voided', $user, ['portfolio_transaction_id'=>$transaction->id,'portfolio_account_id'=>$account->id]);
    }


    public function investorTransactionUpdated(User $user, PortfolioAccount $account, PortfolioTransaction $transaction, array $before): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $this->send($user->email, 'Portfolio activity updated — ABS Private Investor', [
            'eyebrow' => 'PRIVATE INVESTOR ACCOUNT UPDATE',
            'title' => 'A portfolio entry was updated',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'ABS Administration updated a previously posted portfolio entry and recalculated its balance effect.',
            'body' => 'Your secure portfolio dashboard and transaction history now reflect the corrected information.',
            'facts' => array_filter([
                'Updated activity' => ucfirst((string) $transaction->type),
                'Updated amount' => $account->currency.' '.number_format((float) $transaction->amount, 2),
                'Previous amount' => isset($before['amount']) ? $account->currency.' '.number_format((float) $before['amount'], 2) : null,
                'Effective date' => $transaction->transaction_date?->format('d M Y'),
                'Reference' => $transaction->reference,
                'Current portfolio value' => $account->currency.' '.number_format((float) $account->current_value, 2),
            ]),
            'buttonText' => 'Review Portfolio Activity',
            'buttonUrl' => route('private.transactions'),
            'notice' => 'If you have a question about this change, contact ABS Support from your account.',
        ], 'private_investor_transaction_updated', $user, ['portfolio_transaction_id'=>$transaction->id,'portfolio_account_id'=>$account->id]);
    }

    public function investorTransactionDeleted(User $user, PortfolioAccount $account, array $snapshot): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $this->send($user->email, 'Portfolio activity corrected — ABS Private Investor', [
            'eyebrow' => 'PRIVATE INVESTOR ACCOUNT CORRECTION',
            'title' => 'A portfolio entry was removed',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'ABS Administration removed an incorrect portfolio entry and reversed its balance effect.',
            'body' => 'Your current portfolio value and transaction history have been recalculated to exclude the removed entry.',
            'facts' => array_filter([
                'Removed activity' => ucfirst((string) ($snapshot['type'] ?? 'activity')),
                'Removed amount' => $account->currency.' '.number_format((float) ($snapshot['amount'] ?? 0), 2),
                'Original date' => !empty($snapshot['transaction_date']) ? \Carbon\Carbon::parse($snapshot['transaction_date'])->format('d M Y') : null,
                'Reference' => $snapshot['reference'] ?? null,
                'Current portfolio value' => $account->currency.' '.number_format((float) $account->current_value, 2),
            ]),
            'buttonText' => 'Review Portfolio Activity',
            'buttonUrl' => route('private.transactions'),
            'notice' => 'If you do not recognize this correction, contact ABS Support from your account.',
        ], 'private_investor_transaction_deleted', $user, ['deleted_portfolio_transaction_id'=>$snapshot['id'] ?? null,'portfolio_account_id'=>$account->id]);
    }

    public function investorInvestmentTermsUpdated(User $user, PortfolioAccount $account, PortfolioInvestmentTerm $term): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $fullMonthTarget = (float)$account->net_contributions * ((float)$term->monthly_target_rate / 100);
        $this->send($user->email, 'Private Investor terms updated — Alpha Block Solutions', [
            'eyebrow' => 'PRIVATE INVESTOR TERMS',
            'title' => 'Your portfolio terms have been updated',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'The monthly performance terms attached to your private investor portfolio have been updated.',
            'body' => 'Your dashboard will use these terms for provisional monthly progress from the effective date. Published statements remain the official month-end record.',
            'facts' => [
                'Portfolio currency' => $account->currency,
                'Agreed monthly rate' => number_format((float)$term->monthly_target_rate,2).'%',
                'Effective from' => $term->effective_from?->format('d M Y'),
                'Current net investment' => $account->currency.' '.number_format((float)$account->net_contributions,2),
                'Full-month target at current capital' => $account->currency.' '.number_format($fullMonthTarget,2),
                'Status' => ucfirst((string)$term->status),
            ],
            'buttonText' => 'Open Investor Portfolio',
            'buttonUrl' => route('private.index'),
            'notice' => 'Current-month progress may be prorated when capital starts or changes during the month.',
        ], 'private_investor_terms_updated', $user, ['portfolio_account_id'=>$account->id,'investment_term_id'=>$term->id]);
    }

    public function investorStatementPublished(User $user, PortfolioAccount $account, MonthlyStatement $statement): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $this->send($user->email, $statement->statement_month->format('F Y').' investor statement available', [
            'eyebrow' => 'PRIVATE INVESTOR MONTHLY REPORT',
            'title' => 'Your monthly portfolio statement is available',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'Your '.$statement->statement_month->format('F Y').' private investor statement has been published.',
            'body' => 'Review the month-end valuation, capital activity and published profit or loss from your secure investor area.',
            'facts' => array_filter([
                'Opening balance' => $account->currency.' '.number_format((float)$statement->opening_balance,2),
                'Additional investment' => $account->currency.' '.number_format((float)$statement->contributions,2),
                'Withdrawals' => $account->currency.' '.number_format((float)$statement->withdrawals,2),
                'Profit / Loss' => $account->currency.' '.number_format((float)$statement->profit_loss,2),
                'Closing balance' => $account->currency.' '.number_format((float)$statement->closing_balance,2),
            ]),
            'buttonText' => 'Open Monthly Statement',
            'buttonUrl' => route('private.statement', $statement),
            'notice' => 'This statement is available only through your authenticated ABS account.',
        ], 'private_investor_statement_published', $user, ['monthly_statement_id'=>$statement->id,'portfolio_account_id'=>$account->id]);
    }

    public function investorRequestReceived(User $user, PortfolioRequest $portfolioRequest): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $title = ucwords(str_replace('_',' ',(string)$portfolioRequest->type));
        $this->send($user->email, $title.' request received — ABS Private Investor', [
            'eyebrow' => 'PRIVATE INVESTOR SERVICE REQUEST',
            'title' => 'Your request has been received',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'Your '.$title.' request is now in the ABS review queue.',
            'body' => 'You can follow its status from your private investor area. Any confirmed capital movement will appear separately in your audited portfolio activity ledger.',
            'facts' => array_filter([
                'Request' => '#'.$portfolioRequest->id.' · '.$title,
                'Amount' => $portfolioRequest->amount ? $portfolioRequest->currency.' '.number_format((float)$portfolioRequest->amount,2) : null,
                'Status' => 'Submitted',
                'Submitted at' => $portfolioRequest->created_at?->format('d M Y H:i'),
            ]),
            'buttonText' => 'Track Your Request',
            'buttonUrl' => route('private.requests'),
            'notice' => 'Submitting a request does not change your portfolio balance. A confirmed transaction is recorded only after review and completion.',
        ], 'private_investor_request_received', $user, ['portfolio_request_id'=>$portfolioRequest->id]);
    }

    public function investorRequestUpdated(User $user, PortfolioRequest $portfolioRequest): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $title = ucwords(str_replace('_',' ',(string)$portfolioRequest->type));
        $status = ucwords(str_replace('_',' ',(string)$portfolioRequest->status));
        $this->send($user->email, $title.' request updated — '.$status, [
            'eyebrow' => 'PRIVATE INVESTOR SERVICE REQUEST',
            'title' => $title.' request: '.$status,
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'The status of your private investor request has been updated.',
            'facts' => array_filter([
                'Request' => '#'.$portfolioRequest->id.' · '.$title,
                'Amount' => $portfolioRequest->amount ? $portfolioRequest->currency.' '.number_format((float)$portfolioRequest->amount,2) : null,
                'Status' => $status,
                'Admin note' => $portfolioRequest->admin_note,
                'Updated at' => now()->format('d M Y H:i'),
            ]),
            'buttonText' => 'Track Your Request',
            'buttonUrl' => route('private.requests'),
            'notice' => 'Request approval does not by itself alter your portfolio balance. Confirmed capital activity is recorded separately in your transaction ledger.',
        ], 'private_investor_request_updated', $user, ['portfolio_request_id'=>$portfolioRequest->id]);
    }

    public function investorRequestCancelled(User $user, PortfolioRequest $portfolioRequest): void
    {
        if (! $this->enabled('transactional_emails_enabled', true)) return;
        $title = ucwords(str_replace('_',' ',(string)$portfolioRequest->type));
        $this->send($user->email, $title.' request cancelled — ABS Private Investor', [
            'eyebrow' => 'PRIVATE INVESTOR SERVICE REQUEST',
            'title' => 'Your request was cancelled',
            'greeting' => 'Hi '.$this->firstName($user).',',
            'intro' => 'Your '.$title.' request has been cancelled from your account.',
            'facts' => array_filter([
                'Request' => '#'.$portfolioRequest->id.' · '.$title,
                'Amount' => $portfolioRequest->amount ? $portfolioRequest->currency.' '.number_format((float)$portfolioRequest->amount,2) : null,
                'Status' => 'Cancelled',
                'Updated at' => now()->format('d M Y H:i'),
            ]),
            'buttonText' => 'Open Investor Requests',
            'buttonUrl' => route('private.requests'),
            'notice' => 'Cancelling a request does not alter your portfolio balance or confirmed transaction history.',
        ], 'private_investor_request_cancelled', $user, ['portfolio_request_id'=>$portfolioRequest->id]);
    }

    public function adminInvestorRequest(User $user, PortfolioRequest $portfolioRequest): void
    {
        $recipient = $this->adminNotificationEmail();
        if (! $recipient) return;
        $title = ucwords(str_replace('_',' ',(string)$portfolioRequest->type));
        $this->send($recipient, 'Private Investor request — '.$user->name, [
            'eyebrow' => 'PRIVATE INVESTOR REQUEST',
            'title' => $title.' requires review',
            'greeting' => 'Hello Admin,',
            'intro' => $user->name.' submitted a private investor request.',
            'facts' => array_filter([
                'Investor' => $user->name.' · '.$user->email,
                'Request' => '#'.$portfolioRequest->id.' · '.$title,
                'Amount' => $portfolioRequest->amount ? $portfolioRequest->currency.' '.number_format((float)$portfolioRequest->amount,2) : null,
                'Message' => $portfolioRequest->message,
                'Submitted at' => $portfolioRequest->created_at?->format('d M Y H:i'),
            ]),
            'buttonText' => 'Review Investor Request',
            'buttonUrl' => route('admin.private-investors.requests'),
            'notice' => 'Review the request and confirmed portfolio record before recording any capital movement.',
        ], 'admin_private_investor_request', $user, ['portfolio_request_id'=>$portfolioRequest->id]);
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

    /**
     * Admin-facing transport health. A log/array transport is useful locally but
     * is not an email delivery channel and must never be reported as delivered.
     */
    public function deliveryHealth(): array
    {
        $configured = (string) config('mail.default', 'log');
        $effective = $this->effectiveMailer();
        $host = trim((string) config('mail.mailers.smtp.host', ''));
        $username = trim((string) config('mail.mailers.smtp.username', ''));
        $sendmailCommand = trim((string) config('mail.mailers.sendmail.path', '/usr/sbin/sendmail -bs -i'));
        $sendmailBinary = preg_split('/\s+/', $sendmailCommand)[0] ?? '/usr/sbin/sendmail';
        $sendmailReady = $effective === 'sendmail' && @is_executable($sendmailBinary);
        $smtpReady = $effective === 'smtp' && $host !== '' && ! in_array(strtolower($host), ['127.0.0.1', 'localhost'], true);
        $delivery = match ($effective) {
            'log', 'array' => false,
            'smtp' => $smtpReady,
            'sendmail' => $sendmailReady,
            default => true,
        };

        return [
            'configured_mailer' => $configured,
            'effective_mailer' => $effective,
            'delivery_transport' => $delivery,
            'smtp_host' => $host,
            'smtp_username_configured' => $username !== '' && strtolower($username) !== 'null',
            'smtp_ready' => $smtpReady,
            'sendmail_ready' => $sendmailReady,
            'from_address' => (string) config('mail.from.address'),
            'status' => $delivery ? 'ready' : 'setup_required',
            'message' => $delivery
                ? 'Outbound email uses the '.strtoupper($effective).' transport.'
                : 'Outbound email is currently using a non-delivery transport. Configure SMTP or sendmail before relying on production notifications.',
        ];
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
        $mailer = $this->effectiveMailer();
        $health = $this->deliveryHealth();
        if (! ($health['delivery_transport'] ?? false)) {
            $message = 'Email was not delivered because the active mail transport is not production-ready. Configure SMTP or sendmail in Admin before relying on inbox notifications.';
            if ($log) $log->update(['status' => 'not_delivered', 'error_message' => $message]);
            Log::warning('ABS email delivery transport is not configured', ['event' => $event, 'recipient' => $email, 'subject' => $subject, 'mailer' => $mailer]);
            return;
        }

        try {
            Mail::mailer($mailer)->send('emails.branded', $payload, function (Message $message) use ($email, $subject): void {
                $message->to($email)->subject($subject);
                if (config('brand.support_email')) $message->replyTo(config('brand.support_email'));
            });
            if ($log) $log->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
        } catch (Throwable $e) {
            if ($log) $log->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 2000)]);
            Log::warning('ABS email delivery failed', ['event' => $event, 'recipient' => $email, 'subject' => $subject, 'mailer' => $mailer, 'error' => $e->getMessage()]);
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

    private function effectiveMailer(): string
    {
        $configured = strtolower(trim((string) config('mail.default', 'log')));
        if (in_array($configured, ['log', 'array', 'failover'], true)) {
            $host = strtolower(trim((string) config('mail.mailers.smtp.host', '')));
            if ($host !== '' && ! in_array($host, ['127.0.0.1', 'localhost'], true)) return 'smtp';
            $sendmailCommand = trim((string) config('mail.mailers.sendmail.path', '/usr/sbin/sendmail -bs -i'));
            $sendmailBinary = preg_split('/\s+/', $sendmailCommand)[0] ?? '/usr/sbin/sendmail';
            if (@is_executable($sendmailBinary)) return 'sendmail';
        }
        return $configured !== '' ? $configured : 'log';
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
