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
   $credentials['host'] = getenv('RABBITMQ_HOST') ? getenv('RABBITMQ_HOST') : 'localhost';
   $credentials['port'] = getenv('RABBITMQ_PORT') ? getenv('RABBITMQ_PORT') : '5672';
   $credentials['user'] = getenv('RABBITMQ_USERNAME') ? getenv('RABBITMQ_USERNAME') : 'guest';
   $credentials['password'] = getenv('RABBITMQ_PASSWORD') ? getenv('RABBITMQ_PASSWORD') : 'guest';

   // Create connection
   $connection = new AMQPConnection(
      $credentials['host'],
      $credentials['port'],
      $credentials['user'],
      $credentials['password']
   );

   // Name of the direct exchange the channel will connect to
   $exchangeName = 'direct_mailchimp_webhooks';

   // Set the routing key
   $routingKey = 'mailchimp-unsubscribe';

   // Channel
   $channel = $connection->channel();

   // Setup the queue
   $queueName = 'mailchimp-unsubscribe-queue';
   $channel->queue_declare(
      $queueName,    // queue name
      false,         // passive
      true,          // durable
      false,         // exclusive
      false          // auto_delete
   );

   // Setup the exchange
   $channel->exchange_declare(
      $exchangeName,  // exchange name
      'direct',       // exchange type
      false,          // passive
      true,           // durable
      false           // auto_delete
   );

   // Bind the queue to the exchange
   $channel->queue_bind($queueName, $exchangeName, $routingKey);

   // Serialize $_POST data to throw into message body
   $serializedData = serialize($_POST);

   // Publish AMQPMessage to direct_mailchimp_webhooks exchange
   // Mark messages as persistent by setting the delivery_mode = 2
   // @see https://github.com/videlalvaro/php-amqplib/blob/master/doc/AMQPMessage.md
   $message = new AMQPMessage($serializedData, array('delivery_mode' => 2));
   $channel->basic_publish($message, $exchangeName, $routingKey);

   $channel->close();
   $connection->close();
}

?>
