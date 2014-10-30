=== AffiliateWP - Store Credit ===
Plugin Name: AffiliateWP - Store Credit
Plugin URI: http://affiliatewp.com
Description: Pay AffiliateWP referrals as store credit. Currently supports WooCommerce, with support for EDD planned.
Author: ramiabraham
Contributors: ramiabraham, mordauk, sumobi
Tags: affiliatewp, affiliates, store credit, woo, woocommerce
License: GPLv2 or later
Tested up to: 4.0
Stable tag: 1.0
Requires at least: 3.5

== Description ==

* Pay AffiliateWP referrals as store credit.

* Supports WooCommerce, with support for EDD planned.

== Installation ==

* Install and activate.

* When marking an AffiliateWP referral paid, it adds the total to the user's credit balance. If for some reason you go back and mark it unpaid, this plugin will also remove the referral amount from the balance.

* On the checkout page, if the user has credit available, it will show a notice and ask them if they want to use it. Based on the credit available and order total, it will create a 1 time use coupon code for the lower amount and automatically apply it to the order. i.e. for a $100 order and $50 credit, it would generate a $50 coupon since the order is more. If the order is $25 and they have $50 in credit, it will generate a coupon for the $25 order total, and leave them with a $25 credit balance after checkout.

* Upon successful checkout, the one time use coupon code is grabbed and the coupon total is deducted from their available balance.

== Frequently Asked Questions ==

* Does this support Easy Digital Downloads?

A: Not yet! But it will.

== Screenshots ==


== Changelog ==

= 1.0 =
* Initial release.



