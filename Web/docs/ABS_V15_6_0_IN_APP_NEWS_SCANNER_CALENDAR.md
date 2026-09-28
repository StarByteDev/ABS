# ABS V15.6.0 — In-App News, Best Signal Progress & Economic Calendar Reliability

## Verified headlines remain inside ABS
Live RSS headlines from configured verified publishers no longer send members away from Alpha Block Solutions. Home and ABS News cards open an internal `/news/live/{id}` page. The internal page shows source attribution, publish time, category, publisher-supplied feed summary and available feed image in the established ABS dark navy/gold/cyan experience. The publisher's full website is not proxied or mirrored.

Dynamic `/api/v1/news/live` responses include an `abs_url` so browser-loaded cards use the same in-platform route. Editorial source links and economic-event source links are also presented as attribution rather than external redirect actions.

## Best Signal scan progress
The member scanner now displays a prominent ABS Pulse progress surface while the synchronous scan runs. It communicates the user-facing sequence — latest market data, 15M/4H strategy evaluation, qualification checks and ranking — and changes the primary button state to `Finding Best Signal…`. The existing scan result and Best Signal behavior is unchanged.

## Economic Calendar reliability
Financial Modeling Prep remains the optional primary Economic Calendar source. When it is not configured or temporarily unavailable, ABS can use the Trading Economics calendar endpoint as a fallback, defaulting to its limited `guest:guest` access unless another key is provided through environment configuration.

Calendar bootstrap now checks whether the stored database contains events inside the current `-30 day / +45 day` display window. A database containing only stale rows therefore triggers a refresh instead of leaving Today, Upcoming and Previous Releases empty.

Supported environment options:
- `TRADING_ECONOMICS_FALLBACK_ENABLED=true`
- `TRADING_ECONOMICS_API_KEY=guest:guest`
- `TRADING_ECONOMICS_CALENDAR_URL=https://api.tradingeconomics.com/calendar`

The public calendar continues to show Today, Upcoming, Previous Releases and All views with previous, forecast and actual values when the source supplies them.

## Compatibility
- No database migration is required.
- `PulseScannerService.php` is unchanged from the V15.5.0 base.
- The ABS master logo is unchanged.
- V15.5.0 Admin multi-signal research and paper-validation logic is preserved.
