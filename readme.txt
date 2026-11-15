=== Renewal Ready – UK Subscription Rules for WooCommerce ===
Contributors: facilo
Tags: subscriptions, renewal reminder, cancel subscription, woocommerce subscriptions, uk
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Keep subscribers' trust and get ready for the UK's January 2027 subscription rules: clear checkout, renewal reminders and easy cancellation.

== Description ==

From 1 January 2027, UK shops that sell subscriptions must remind customers before renewals, let them cancel online easily and confirm it in writing. If a buyer doesn't clearly agree to pay at checkout, they may not have to pay at all. Renewal Ready adds all of this to the subscription plugin you already use, in minutes, so you keep sales and customers' trust.

It works with WP Swings Subscriptions for WooCommerce, YITH WooCommerce Subscription (free) and, in beta, WooCommerce Subscriptions. Everything below is free, with no trial and nothing locked.

= Renewal reminders on the right schedule =

Customers hear from you before they pay again, so renewals don't come as a surprise and fewer turn into chargebacks.

* A reminder notice before each renewal, on the schedule the Digital Markets, Competition and Consumers Act 2024 (DMCC subscription rules) sets out: one every 6 months, one before the first full-price payment after a free trial or intro price, and an extra early reminder for annual plans.
* Each notice is sent once, inside the window you choose (14 to 7 days before the last day to cancel by default).
* The key facts come first in a clear box: what renews, the price, the payment date and the date to end it by.
* The emails are normal WooCommerce emails, so they use your shop's look and can be tested in WooCommerce › Settings › Emails.
* Preview mode is on when you install: every notice is written to the subscription's notes but nothing is emailed until you switch to Live.

= A checkout customers understand =

Buyers see exactly what they're signing up to, which builds the trust that gets the order placed.

* A key information box on the block checkout and the classic checkout, shown only when the cart has a subscription: price, how often, trial, minimum term, when reminders come and how to end it.
* A required tick next to it ("I understand I'm subscribing and agree to pay…") with the amount and frequency, and the order is refused without it. The text, time and order are saved on the order.
* The place order button reads "Subscribe and pay" when the cart has a subscription.

= Cancel online in two clicks =

An easy, honest exit keeps customers' goodwill, and many come back.

* An "End subscription" button in My Account with one question and two equal buttons.
* The subscription ends at the end of the paid period, with no further charge.
* An end-of-contract email goes out after every cancellation, whether the customer used our button, your subscription plugin's own button, or you cancelled it in the admin.
* A "How to end your subscription" page (block and shortcode `[renewal_ready_exit]`) linked from every notice.

= Know where you stand =

* A readiness check with green, amber and red items and a one-line fix for each: source found, live mode, checkout box, tick, exit page, reminder window, daily run, test email, contact email, terms page, short trials and late notices.
* "Send me a test" for any notice and any subscription.

= Proof when you need it (Pro) =

The shop has to show that each notice was sent. Renewal Ready Pro, a separate paid add-on, keeps an evidence log with a copy of every email, a printable evidence pack per subscription and a CSV export, plus editable email templates with a live preview.

= Cooling-off refunds worked out for you (Pro) =

Renewal Ready Pro sends the renewal cooling-off notice, works out the refund for the renewal cooling-off period by product type (service, digital content, goods, perishable), puts it in a queue for you to approve in one click, and adds an "End my subscription" link that works without logging in. It also flags late notices and shows the next 60 days on a calendar. Pro costs £29 a year for one shop or £89 for unlimited shops.

= Works with =

* WP Swings Subscriptions for WooCommerce
* YITH WooCommerce Subscription (free version)
* WooCommerce Subscriptions (beta, built on its public functions and hooks)

Renewal Ready is a tool, not legal advice. It's built from the Act, the draft Consumer Subscription Contracts Regulations 2026 and the government's guidance, and you decide how your shop uses it.

= Your data =

The free plugin makes no requests to outside servers. Everything it stores stays in your WordPress database, and it adds its records to WordPress's personal data export and erase tools.

== Installation ==

1. Install and activate WooCommerce and one of the subscription plugins above.
2. Install Renewal Ready from Plugins › Add New and activate it.
3. Open WooCommerce › Renewal Ready. The setup creates the "How to end your subscription" page.
4. Work through the readiness check, send yourself a test notice, then switch from Preview to Live.

== Frequently Asked Questions ==

= Is this legal advice? =

No. Renewal Ready is a tool that sends the notices and adds the checkout steps the rules describe. If you're unsure how the rules apply to your shop, ask a solicitor.

= Which subscription plugins does it work with? =

WP Swings Subscriptions for WooCommerce and the free YITH WooCommerce Subscription. WooCommerce Subscriptions works in beta: it's built on that plugin's public functions and tested against its open-source core.

= What happens to customers who subscribed before 2027? =

The government's guidance says the rules apply only to subscriptions taken out on or after 1 January 2027, not to older ones. It also calls giving every subscriber the same protection good practice, so Renewal Ready sends reminders and offers the online exit to every active subscription by default. The checkout box and tick apply to new orders.

= Does it send data anywhere? =

The free plugin: no. It makes no outside requests. Pro only contacts its licence server (Freemius) after you opt in.

= Does it charge or refund by itself? =

The free plugin never charges or refunds anything: it only ends subscriptions at the end of the paid period through your subscription plugin. Pro refunds automatically only if you turn that on for a product type; otherwise refunds wait in a queue for you to approve.

= What's in Pro? =

Renewal cooling-off notices, the refund calculator and refund queue, an express consent tick for services, one-click cancel without logging in, the evidence log and evidence pack with copies of every email, CSV export, editable templates with live preview, the late-notice guard, per-product settings and the 60-day calendar. Pro is a separate plugin that needs this one.

= How long is a release supported? =

Each release gets security fixes for 5 years from its release date. Report security problems to support@facilo.es.

== Screenshots ==

1. The key information box and payment tick on the block checkout.
2. A renewal reminder email, with the key facts and the date to end it by first.
3. Ending a subscription from My Account: one question, two equal buttons.
4. The readiness check, with a fix for every item that needs one.
5. Every notice previewed in your own email design, with a test to your inbox.

== Changelog ==

= 1.0.0 =
* First release: reminder notices, checkout key information box and payment tick, online exit, end-of-contract email, preview mode and readiness check, for WP Swings, YITH free and WooCommerce Subscriptions (beta).

== Upgrade Notice ==

= 1.0.0 =
First release.
