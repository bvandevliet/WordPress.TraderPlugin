=== Trader ===

Contributors: bvandevliet
Tags: 
Requires at least: 5.7
Tested up to: 5.8
Requires PHP: 7.2
Stable tag: 2022.03.21
License: MIT

Calculates and executes a crypto portfolio rebalance.


== Description ==

Connects to exchange API's, provides blocks for rendering exchange data and includes logic to rebalance a portfolio. Still a work in progress and currently only supports the Bitvavo Exchange.


== Changelog ==

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

= 2021.11.19 =
* Improved reliability and scalability preparations.

= 2021.11.09 =
* Market Cap EMA.

= 2021.11.03 =
* Log Market Cap history.

= 2021.10.27 =
* Initial release.
