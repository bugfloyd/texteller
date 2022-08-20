=== Texteller ===
Contributors: yashar_hv
Tags: sms,mobile registration,phone login,sms notifications,mobile verification,woocommerce,send sms,bulk sms,newsletter,text message,mobile login,receive sms
Requires at least: 5.3
Tested up to: 6.0
Stable tag: trunk
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

An all-in-one text message integration solution for WordPress and popular third-party plugins, supporting multiple SMS and messaging gateways.

== Description ==
Texteller is an all-in-one text message integration solution for WordPress, supporting multiple third-party SMS and messaging gateways.  
The plugin tries to integrate with almost all email triggers on the site to send text messages. You can also receive text messages with supported gateways.  

Texteller offers a separated member registration system and along with the complete integration with WordPress user registration, it fully supports most popular plugins, including WooCommerce customer registration and checkout process (there’s more to come). If enabled, users may be able to register on the site using their mobile number instead of email address.  

The plugin has a complete text message newsletter system with a detailed registration form which could be inserted on any page or post using WordPress Widgets or the shortcode. Members registered via newsletter form may have no user linked to them, in order to prevent conflicts with other third-party plugins and they become just members of your site’s text newsletter!  

# PLUGIN FEATURES #  
## WORDPRESS INTEGRATION FEATURES ##  
*   Enable/disable WordPress user registration integration  
*   Full integration with WordPress user registration on wp-login.php and admin dashboard pages: Users may be able to register using their mobile number and their member account will be linked to their WP user  
*   Customize registration form fields: Edit fields’ label or mark them as required  
*   Hide username field or make it as an optional field  
*   Automatic username generation with 4 different patterns: 1) Using Name, surname, or email address 2) International mobile number 3) National mobile number with or without the leading zero 4) Random numbers  
*   Add form description to wp-login.php page  
*   Update first and last name on WordPress user profile for new users  
*   Login with mobile number along with username or email login  
*   Mobile number enabled forget password process  
*   Mobile number verification by sending a code (from Profile page)  
*   Allow users to update their mobile number via Profile page and ask them to re-verify the new mobile number  
*   Control sending new users' set-password email
*   Control sending forget password email  
*   Send notifications when a new user is registered  
*   Send set-password link on new user registration  
*   Send notifications when a new blog post is published  
*   Send notifications when a new comment is added to a blog post  
*   Send notifications when a comment is approved  

## NEWSLETTER FEATURES ##  
*   Text message newsletter registration form  
*   Insert registration form on any page or post using WordPress Widgets or the plugin shortcode  
*   Customize registration form fields: Edit fields’ label and size, or make them required  
*   40 different option to customize newsletter form design including colors, font sizes, and margin/padding  
*   Add or edit the title, description, and submit button’s label for newsletter registration form  
*   Ability to link logged-in user to the member registered via newsletter form  
*   Mobile number verification by sending a code when a members registers via newsletter form  
*   Send notifications when a new member is registered via newsletter form  

## WOOCOMMERCE INTEGRATION FEATURES ##  
*   Enable/disable WooCommerce customer registration integration  
*   Full integration with WooCommerce customer registration from My-Account and Checkout pages: Customers may be able to register using their mobile number, and their member will be linked to their WP user  
*   Login with mobile number along with username or email login from My-Account and Checkout pages  
*   Mobile number enabled forget-password process  
*   Customize registration form fields: Edit fields’ label and size, or mark them as required  
*   Hide username field or make it optional  
*   Automatic username generation with 4 different patterns: 1) WooCommerce default 2) International mobile number 3) National mobile number with or without the leading zero 4) Random numbers  
*   Control sending new customer email  
*   Control sending forget-password email  
*   Update name fields on user profile for new customers registered via My-Account page
*   For logged in customers with an already linked member, update member name fields in the checkout process  
*   Allow logged in customers with no linked members, to register via Checkout page  
*   Allow customers to verify their mobile number on My-Account page  
*   Force customers to verify their mobile number on Checkout page  
*   Ask customers to verify their mobile on "Thank You" page
*   Allow customers to update their mobile number via My-Account page and ask them to re-verify the new mobile number  
*   Update Billing Phone field for new customers  
*   Send notifications when a new costumer is registered  
*   Send password message on new customer registration  
*   Send notification to existing customers on Checkout registration  
*   Send notification to existing customers when registering via Edit Account  
*   Send notification to logged-in customers after updating account details  
*   Send notifications when a new order is placed from admin dashboard  
*   Send notifications when a new order is placed by a customer
*   Send notification when an order status is changed

## MEMBERS MANAGEMENT FEATURES ##  
*   Add/Edit/Remove members from admin dashboard and a dedicated screen  
*   Ability to automatic member deletion when a linked user got deleted from the site  
*   Member note for each member for admin reference  
*   Mobile number and carrier info on admin member screen  
*   Send text messages directly from admin member screen  
*   Use member groups to categorize site members  
*   Mark a member group as Private to use it for your own reference or make it Public to display the member group on registration forms  
*   Filter members using status, member groups, registration origins or WP user correlation status  
*   Members bulk actions: Verify, Delete, Cancel membership, and Send text messages  
*   Search between members using first or last names or mobile number  

## MESSAGES MANAGEMENT FEATURES ##  
*   Ability to save all sent messages and notifications on a separated table on site’s database (Messages including password and RP links are excluded)  
*   Manage sent and received messages from admin dashboard  
*   See delivery status for sent messages (if supported by the gateway)  
*   Filter messages using status, notification trigger, and gateway  
*   Forward selected message to another member or a custom number  
*   Messages bulk actions: reply, resend, and delete  
*   Search between messages’ content or recipients  

## GENERAL & COMMON FEATURES ##  
*   Send text messages to manually selected or filtered members or custom numbers  
*   Receive text messages on a webhook (receive end-point). To use this feature the selected gateway should support message receiving  
*   Automatic digit conversion to a selected language in the notification message content  
*   Automatic URL shortener to convert links in the notification messages. Currently Texteller supports bitly.com (There’s more to come!)  
*   Manage site staff to send the desired notifications and messages  
*   Multiple notification triggers to send text messages: Each notification trigger is able to send the content to multiple recipients when a selected event happens on the site. Each trigger may have multiple recipients such as the trigger object recipient, site staff, selected members, or custom numbers  
*   Tag system which automatically replaces with the desired data in the message’s content. e.g.  members, users, posts or orders’ data  
*   Enhanced mobile number field on registration forms with a country drop-down, pre-selected country, and a preferred country list  
*   Ability to disable country selection and limit the registration to one country  
*   Ability to completely remove email field or make it an optional field on user registration forms  
*   13 different calendar types to be used in the plugin dashboard pages (PHP Intl extension should be installed on the server)   
*   Automatic signature insertion to all notifications  
*   Manage default country list to be used when a user tries to login or get a forget-password link without entering the country code  
*   Control verification codes’ lifetime
*   Advanced member importer tool to automatically register existing site users as a linked member

# SUPPORTED GATEWAYS #
*   [BulkSMS](https://www.bulksms.com)
*   [GatewayAPI](https://gatewayapi.com)
*   [Melipayamak](https://www.melipayamak.com) (Dedicated and shared line)
*   [SabaNovin](https://sms.sabanovin.com)
*   [Spryng](https://www.spryng.nl)
*   [Textlocal](https://www.textlocal.com)
*   [Twilio](https://www.twilio.com)
*   There’s more to come soon!

# REQUIREMENTS #  
Texteller needs PHP version 7.4 or above to give you the lite and smooth experience with the least effect on your website’s performance.
You should also have a WordPress version 5.0 or above to use the plugin and if you are planning to use the WooCommerce integration features, you will need WooCommerce version 6.1 or above.
To enable internationalization features like local calendar and date types, PHP Intl extension should be installed on the server.  


== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/texteller` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the \'Plugins\' screen in WordPress
3. Use the Texteller->Options screen to configure the plugin

== Frequently Asked Questions ==
= Are you going to add more messaging gateways to the plugin? =

Definitely yes! Texteller will support most popular messaging gateways in the world in near future!

= Why the plugin uses separated member system instead of using WordPress users? =

Texteller has many features based on mobile number such as newsletter and messaging lists (coming soon). To prevent conflicts with other third-party plugins, we use a dedicated members database table. However, each member can be linked to an existing WP user.

= Is there any plan to support more plugins? =  

Of course! More plugin integration will be available in future updates. Some notable ones are: Easy Digital Downloads, Gravity Forms, Ultimate Member, etc.

== Screenshots ==
 
1. Notification trigger configuration
2. WordPress user integration options
3. WordPress user registration form
4. WooCommerce registration form fields
5. Dashboard Members screen
6. Newsletter registration form
7. Add/Edit member screen
8. Send message screen
9. Member groups
10. WordPress Profile page
11. Gateway settings
12. WooCommerce registration and login via My-Account page

== Changelog ==

= 1.3.0 =
* New: BulkSMS gateway support
* New: Textlocal gateway support
* New: SabaNovin gateway support
* Tweak: Add native support for GatewayAPI & remove third-party dependency
* Tweak: Minor improvements and code refactoring
* Fix: Store and use GatewayAPI message IDs to match delivery reports

= 1.0 =
* New: Tested the plugin up to WordPress 6.0
* New: Now Texteller supports WooCommerce up to 6.4.1
* New: Added plugin upgrade notice
* Tweak: Improved plugin options descriptions
* Tweak: Updated WooCommerce modified templates
* Tweak: Removed simple passwords option from WooCommerce module, since password generation is now disabled in WC
* Tweak: Increased minimum PHP version to 7.4
* Tweak: Load required PHP libraries using composer
* Tweak: Updated intl-tel-input library to v17
* Tweak: Updated libphonenumber-for-php to 8.12.47.1
* Tweak: Updated Twilio SDK to 6.37
* Tweak: Added a notice when there are no numbers on Twilio account
* Tweak: Refactored and cleaned up some plugin files
* Fix: Reset password links in WooCommerce set-password and forget password notifications
* Fix: WordPress warning for public REST routes
* Fix: PHP warning while formatting datetime
* Fix: PHP notices while sending notifications when a gateway does not have extra data

## Upgrade Notice ##
### 1.3.0 ###
If you have PHP version below 7.4, you can download plugin version 0.1.3 from bit.ly/3yJQRUv which supports PHP 7.1 and above