# ABS V14.7.6 Subscription Decision Workflow

Admin → Memberships & Payments now provides a dedicated decision card for each pending request.

## Approval flow

1. Verify member identity and requested plan.
2. Verify amount, payment reference/network and payment proof where required.
3. Review promotion/discount and any user note.
4. Confirm access duration.
5. Enter an approval remark.
6. Select **Approve & activate subscription**.

The existing PulseMembershipService activates access, the action is written to the Pulse audit log, and the user receives the branded activation email.

## Rejection flow

1. Enter a clear rejection reason.
2. Select **Reject request**.

The reason is retained on the membership request, written to the audit trail, and included in the branded decline email.

No schema change is required for this release.
