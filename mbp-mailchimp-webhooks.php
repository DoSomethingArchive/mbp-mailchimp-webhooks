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

// MailChimp webhook will submit a POST to this endpoint

// Require a valid secret key before processing the webhook request.
if ($_GET['key'] != md5('DoSomething.org')) {
   echo "Invalid key.\n";
   return;
}

// Verify type is 'unsubscribe'
if ($_POST['type'] == 'unsubscribe') {
   // Pull RabbitMQ credentials from environment vars. Otherwise, default to local settings.
   $credentials = array();
   $credentials['host'] = isset(getenv('RABBITMQ_HOST')) ? getenv('RABBITMQ_HOST') : 'localhost';
   $credentials['port'] = isset(getenv('RABBITMQ_PORT')) ? getenv('RABBITMQ_PORT') : '5672';
   $credentials['user'] = isset(getenv('RABBITMQ_USERNAME')) ? getenv('RABBITMQ_USERNAME') : 'guest';
   $credentials['password'] = isset(getenv('RABBITMQ_PASSWORD')) ? getenv('RABBITMQ_PASSWORD') : 'guest';

   // Create connection
   $connection = new AMQPConnection(
      $credentials['host'],
      $credentials['port'],
      $credentials['user'],
      $credentials['password']
   );

   // Name the direct exchange the channel will connect to
   $exchangeName = 'ds.direct_mailchimp_webhooks';

   // Set the routing key that
   $routingKey = 'mailchimp-unsubscribe';

   $channel = $connection->channel();
   $channel->exchange_declare(
      $exchangeName,  // exchange name
      'direct',       // exchange type
      false,          // passive
      false,          // durable
      false           // auto_delete
   );

   // Serialize $_POST data to throw into message body
   $serializedData = serialize($_POST);

   // Publish AMQPMessage to direct_mailchimp_webhooks exchange
   $message = new AMQPMessage($serializedData);
   $channel->basic_publish($message, $exchangeName, $routingKey);

   $channel->close();
   $connection->close();
}

?>
