=== Trader ===

Contributors: bvandevliet
Tags: 
Requires at least: 5.8
Tested up to: 6.0
Requires PHP: 8.1
Stable tag: 2022.07.08
License: MIT

Calculates and executes a crypto portfolio rebalance.


== Description ==

Connects to exchange API's, provides blocks for rendering exchange data and includes logic to rebalance a portfolio. Still a work in progress and currently only supports the Bitvavo Exchange.


== Changelog ==

= 2022.07.08 =
* Small performance optimization in rebalance logic.
* Updated configuration defaults to be less risky.
* Improved error reporting of failed automations.

= 2022.06.19 =
* BREAKING: Assets containing excluded tags are skipped in top counter, reducing top count value may be required !!
* BREAKING: Stablecoins not skipped anymore by default, add 'stablecoin' to excluded tags to skip them !!
* BREAKING: Improved strong-typed params and returns, no back-compat php <8 !!
* Re-enabled `set_time_limit` fallback.

= 2022.06.12 =
* Fix compatibility with php 8.1
* Round up expected fee per trade, not only the total amount.
* Minor spelling correction in email notification.

= 2022.05.11 =
* Added option to ignore stablecoins in top counter.
* Now showing relevant information about orders in triggered automation email notification.
* Improved buy and sell order logic.

= 2022.04.26 =
* Added support to force a fixed sideline allocation of a given asset (e.g. a stablecoin with a high staking reward).
* Allocation of excluded assets is allowed if custom weighting is set and asset is within top count (e.g. stablecoins or excluded tags).

= 2022.04.07 =
* Improved logic to properly rebalance as best as possible within boundaries.
* Added admin setting to define "from" email address used for automated emails.
* Fixed a bug that could trigger larger buy orders than the available balance.
* Bugfix in email notification logic.
* Bugfixed form error output.
* Some stability, reliability and performance improvements.

= 2022.03.20 =
* Deprecated dust limit, just only trade above the min order amount.
* Only update last rebalance date if at least one trade was executed.
* Deprecated weighting quote allocation by fag index.
* Improved Javascript.

= 2022.02.07 =
* Painful bugfix that caused automations to potentially execute on the wrong portfolio.
* Important bugfix that caused the action hook for emailing triggered automations to fail.
* Placing rebalance orders asyncronously to reduce server page load.
* More reliable rebalance success verification by testing if all orders were filled.
* Improved sanitazion of excluded tags and better feature description.
* Important automation threshold bugfix.
* Added support to exclude assets based on tags.
* Various improvements.
* Added support for Wordfence 2FA plugin.
* Better UX using loaders on form input event.
* Email notification for triggered automations.
* Various improvements.
* Added support for automated rebalancing.

= 2021.12.08 =
* Ajax portfolio overview update on form change.
* Balance summary ajax ticker.
* Various bugfixes and improvements.
* Improved reliability and scalability preparations.

= 2021.11.09 =
* Market Cap EMA.
* Log Market Cap history.

= 2021.10.27 =
* Initial release.
