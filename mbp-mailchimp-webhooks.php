<?php

/**
 * MailChimp webhooks will submit POST data to this endpoint. This endpoint is
 * responsible for packaging the webhook info and forwarding the message onto the
 * Message Broker. Once there, the message will sit in queues to be processed by
 * an appropriate consumer when available.
 */

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use DoSomething\MBStatTracker\StatHat;


// Load configuration settings common to the Message Broker system.
// Symlinks in the project directory point to the actual location of the files.
require('mb-secure-config.inc');
require('mb-config.inc');

$statHat = new StatHat(getenv('STATHAT_EZKEY'), 'mbp-mailchimp-webhooks:');

// Require a valid secret key before processing the webhook request.
if (!isset($_GET['key']) || $_GET['key'] != md5('DoSomething.org')) {
  echo "Invalid key.\n";

  // Report to StatHat that a query was received with an invalid key.
  $statHat->addStatName('invalid key');
  $statHat->reportCount(1);

  return;
}

// Report to StatHat all received webhook events.
if (isset($_POST['type'])) {
  $statHat->addStatName('received: ' . $_POST['type']);
}
else {
  $statHat->addStatName('no event type');
}

// Verify type is 'unsubscribe'
if ($_POST['type'] == 'unsubscribe') {
  // Pull RabbitMQ credentials from environment vars. Otherwise, default to local settings.
  $credentials = array (
    'host' => getenv('RABBITMQ_HOST') ? getenv('RABBITMQ_HOST') : 'localhost',
    'port' => getenv('RABBITMQ_PORT') ? getenv('RABBITMQ_PORT') : '5672',
    'username' => getenv('RABBITMQ_USERNAME') ? getenv('RABBITMQ_USERNAME') : 'guest',
    'password' => getenv('RABBITMQ_PASSWORD') ? getenv('RABBITMQ_PASSWORD') : 'guest',
    'vhost' => getenv('RABBITMQ_VHOST') ? getenv('RABBITMQ_VHOST') : '',
  );

  $config = array(
    // Routing key
    'routingKey' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_ROUTING_KEY'),

    // Exchange options
    'exchange' => array(
      'name' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_EXCHANGE'),
      'type' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_EXCHANGE_TYPE'),
      'passive' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_EXCHANGE_PASSIVE'),
      'durable' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_EXCHANGE_DURABLE'),
      'auto_delete' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_EXCHANGE_AUTO_DELETE'),
    ),

    // Queue options
    'queue' => array(
      array(
        'name' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_QUEUE'),
        'passive' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_QUEUE_PASSIVE'),
        'durable' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_QUEUE_DURABLE'),
        'exclusive' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_QUEUE_EXCLUSIVE'),
        'auto_delete' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_QUEUE_AUTO_DELETE'),
        'bindingKey' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_ROUTING_KEY'),
      ),
    ),
  );

  // Use MessageBroker to establish the connection and handle transactions.
  $mb = new MessageBroker($credentials, $config);

  // Pass along POST data in its entirety to the message broker.
  $payloadUnserialized = $_POST;

  // Also add application id and timestamp.
  // @see https://github.com/DoSomething/message-broker/wiki/Config
  $payloadUnserialized['application_id'] = 3;
  $payloadUnserialized['activity_timestamp'] = time();

  // Serialize data and publish to the message broker.
  $payload = serialize($payloadUnserialized);
  $mb->publishMessage($payload);

  // Report to StatHat that an event was published to the message broker.
  $statHat->addStatName('published: ' . $_POST['type']);
}

// Report to StatHat.
$statHat->reportCount(1);

?>
