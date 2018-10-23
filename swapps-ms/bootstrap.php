<?php

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\DependencyInjection;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Routing;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Model\Entity\ResponseBootstrap;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/functions.php';

$request = Request::createFromGlobals();
$locator = new FileLocator(__DIR__ . '/../config');

$data = new ResponseBootstrap();


// DI container
$container = new DependencyInjection\ContainerBuilder;
$container->setParameter('path.root', __DIR__);
$resolver = new LoaderResolver(
    [
        new YamlFileLoader($container, $locator),
        new PhpFileLoader($container, $locator),
    ]
);

$loader = new DelegatingLoader($resolver);

// check if production ready
if(getenv("ENVIVORMENT") === "prod"){
    $loader->load('config-production.yml');
}else{
    $loader->load('config-development.yml');
}

$container->compile();

// routing
$loader = new Routing\Loader\YamlFileLoader($locator);
$context = new Routing\RequestContext();
$context->fromRequest($request);

$matcher = new Routing\Matcher\UrlMatcher(
    $loader->load('routing.yml'),
    $context
);
try {

    $parameters = $matcher->match($request->getPathInfo());
    foreach ($parameters as $key => $value) {
        $request->attributes->set($key, $value);
    }







    $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
    $channel = $connection->channel();
    $channel->queue_declare('rpc_queue', false, false, false, false);


    echo " [x] Awaiting RPC requests\n";


    /**
     * Get data
     *
     * @param $requestData
     * @return string
     */
    function decodeResponse($requestData){
        $params = json_decode($requestData);

        if ($route = $params->_route != null){
            $route = $params->_route;
        } else {
            $route = "none";
        }

        if ($action = $params->action != null){
            $action = $params->action;
        } else {
            $action = "none";
        }

        if ($userId = $params->user_id != null){
            $userId = $params->user_id;
        } else {
            $userId = "none";
        }

        if ($limit = $params->limit != null){
            $limit = $params->limit;
        } else {
            $limit = "none";
        }

        $method = $params->method;

        return strtoupper($method) . ", route: " . $route . ", " . $action . ", limit: " . $limit . ", user_id: " . $userId;
    }



    /**
     * RPC response callback
     *
     * @param $request
     */
    $callback = function ($request) {
        $jsonData = $request->body;

        $params = decodeResponse($jsonData);

        echo ' [.] Request from swapps ---->(', $params, ")\n";

        $msg = new AMQPMessage(
            (string)$jsonData,
            // (string)json_encode($params),
            array('correlation_id' => $request->get('correlation_id'))
        );

        $request->delivery_info['channel']->basic_publish(
            $msg,
            '',
            $request->get('reply_to')
        );

        $request->delivery_info['channel']->basic_ack(
            $request->delivery_info['delivery_tag']
        );
    };



    $channel->basic_qos(null, 1, null);
    $channel->basic_consume('rpc_queue', '', false, false, false, false, $callback);
    while (count($channel->callbacks)) {
        $channel->wait();
    }

    $channel->close();
    $connection->close();














    // $command = $request->getMethod() . $request->get('action');
    // $controller = $container->get('controller.' . $request->get('resource'));


    // $controller->{$command}($request);
    // $data = $controller->{$command}($request);

} catch (\Exception $exception) {
    // TODO log data
    $data->setStatus(404);
    $data->setMessage('No routes found');
    die(print_r($exception->getMessage()));
} catch (\TypeError $error) {
    // TODO log data
    $data->setStatus(404);
    $data->setMessage(new Response("Invalid dependency: {$error->getMessage()}"));
    die(print_r(new Response("Invalid dependency: {$error->getMessage()}")));
}

// Check if json in array from
if(!empty($data->getData())){
    $response = new JsonResponse($data->getData());
    // set encoding to handle istring as int
    $response->setEncodingOptions(JSON_NUMERIC_CHECK);
}else{
    // Not json
    $response = new Response;
}

//Set custom headers
$response->setStatusCode(
    (int)$data->getStatus(),
    empty($data->getMessage()) ? $data->getMessage() : null
);

// preflighted request handle
if($request->getMethod() === 'OPTIONS'){
    // set status
    $response->setStatusCode((int)200);
}

// headers
$response->headers->set('Access-Control-Allow-Origin', '*');
$response->headers->set('Access-Control-Allow-Methods', 'GET,HEAD,OPTIONS,POST,PUT,DELETE');
$response->headers->set('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization');
$response->headers->set('Access-Control-Allow-Credentials', 'true');
$response->headers->set("Access-Control-Max-Age", "1728000");

// return response
// $response->send();