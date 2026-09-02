@php($promo=$promotion)
<label>Code<input name="code" required maxlength="80" value="{{ old('code',$promo?->code) }}" placeholder="MEMBER2026"></label>
<label>Label<input name="label" maxlength="120" value="{{ old('label',$promo?->label) }}" placeholder="Internal campaign name"></label>
<label>Type<select name="type"><option value="coupon" @selected(old('type',$promo?->type ?? 'coupon')==='coupon')>Coupon</option><option value="gift_voucher" @selected(old('type',$promo?->type)==='gift_voucher')>Gift voucher</option></select></label>
<label>Discount<select name="discount_type"><option value="percent" @selected(old('discount_type',$promo?->discount_type ?? 'percent')==='percent')>Percentage</option><option value="fixed" @selected(old('discount_type',$promo?->discount_type)==='fixed')>Fixed amount</option><option value="full" @selected(old('discount_type',$promo?->discount_type)==='full')>Full value / complimentary</option></select></label>
<label>Discount value<input type="number" name="discount_value" min="0" step="0.01" value="{{ old('discount_value',$promo?->discount_value ?? 0) }}"></label>
<label>Applicable plan<select name="applicable_plan_id"><option value="">Any public plan</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected((int)old('applicable_plan_id',$promo?->applicable_plan_id)===$plan->id)>{{ $plan->name }}</option>@endforeach</select></label>
<label>Assigned member <input type="email" name="assigned_user_email" maxlength="255" value="{{ old('assigned_user_email',$promo?->assignee?->email) }}" placeholder="Optional user email"><small>Leave blank for a general code. Assigning an email makes the code visible and usable only by that member.</small></label>
<label>Access days override<input type="number" name="access_days" min="1" max="3650" value="{{ old('access_days',$promo?->access_days) }}" placeholder="Use plan duration"></label>
<label>Maximum uses<input type="number" name="max_uses" min="0" value="{{ old('max_uses',$promo?->max_uses ?? 0) }}"><small>0 = unlimited.</small></label>
<label>Per-user limit<input type="number" name="per_user_limit" min="1" value="{{ old('per_user_limit',$promo?->per_user_limit ?? 1) }}"></label>
<label>Valid from<input type="datetime-local" name="valid_from" value="{{ old('valid_from',$promo?->valid_from?->format('Y-m-d\TH:i')) }}"></label>
<label>Valid until<input type="datetime-local" name="valid_until" value="{{ old('valid_until',$promo?->valid_until?->format('Y-m-d\TH:i')) }}"></label>
<label class="admin-check admin-check-inline"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$promo?->is_active ?? true))><span>Code is active</span></label>
<label class="admin-check admin-check-inline"><input type="checkbox" name="auto_activate" value="1" @checked(old('auto_activate',$promo?->auto_activate ?? false))><span>Auto-activate a full gift voucher</span></label>
<label class="full">Internal note<textarea name="notes" rows="2" maxlength="2000">{{ old('notes',$promo?->notes) }}</textarea></label>
