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

// Load configuration settings common to the Message Broker system.
// Symlinks in the project directory point to the actual location of the files.
require('mb-config.inc');

// Require a valid secret key before processing the webhook request.
if ($_GET['key'] != md5('DoSomething.org')) {
  echo "Invalid key.\n";
  return;
}

// Verify type is 'unsubscribe'
if ($_POST['type'] == 'unsubscribe') {
  // Pull RabbitMQ credentials from environment vars. Otherwise, default to local settings.
  $credentials = array();
  $credentials['host'] = getenv('RABBITMQ_HOST') ? getenv('RABBITMQ_HOST') : 'localhost';
  $credentials['port'] = getenv('RABBITMQ_PORT') ? getenv('RABBITMQ_PORT') : '5672';
  $credentials['username'] = getenv('RABBITMQ_USERNAME') ? getenv('RABBITMQ_USERNAME') : 'guest';
  $credentials['password'] = getenv('RABBITMQ_PASSWORD') ? getenv('RABBITMQ_PASSWORD') : 'guest';

  $config = array(
    // Routing key
    'routingKey' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_ROUTING_KEY'),

    // Consume options
    'consume' => array(
      'consumer_tag' => '',
      'no_local' => FALSE,
      'no_ack' => FALSE,
      'exclusive' => FALSE,
      'nowait' => FALSE,
    ),

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
      'name' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_QUEUE'),
      'passive' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_QUEUE_PASSIVE'),
      'durable' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_QUEUE_DURABLE'),
      'exclusive' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_QUEUE_EXCLUSIVE'),
      'auto_delete' => getenv('MB_MAILCHIMP_UNSUBSCRIBE_QUEUE_AUTO_DELETE'),
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
}

?>
