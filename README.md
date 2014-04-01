# mbp-mailchimp-webhooks

This is a Message Broker producer that listens for Mailchimp webhooks. Webhook events are received and passed onto the Message Broker queue to be handled by any listening consumers.

## Setup
#### Prerequisites
- Install Composer: https://getcomposer.org/doc/00-intro.md#installation-nix
- Setup configs:
  - Clone the messagebroker-config repository: https://github.com/DoSomething/messagebroker-config.
  - Create a symlink in the root of `mbp-mailchimp-webhooks` to `mb-config.inc` in `messagebroker-config`.
  - Create a symlink in the root of `mbp-mailchimp-webhooks` to wherever the `mb-secure-config.inc` file is.

#### Publish Messages with the Producer
- Install dependencies: `composer install`
- Send POST requests to mbp-mailchimp-webhooks.php.

##### Notes
- To simulate `unsubscribe` webhook events, make sure to include a `type` parameter set to `unsubscribe`.
- As a basic layer of security against spam, a proper key needs to be included in the URL. See code to find out what it should be.
