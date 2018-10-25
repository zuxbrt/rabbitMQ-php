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

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();
$channel->queue_declare('rpc_queue', false, false, false, false);


echo " [x] Awaiting RPC requests\n";


/**
 * Set response
 *
 * @param $params
 * @return JsonResponse|Response
 */
function setResponse($params){

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

    // die(print_r($loader));

    $container->compile();


    // routing
    $loader = new Routing\Loader\YamlFileLoader($locator);
    // $context = new Routing\RequestContext();
    // $context->fromRequest($request);

    $requestContext = new Routing\RequestContext();
    $requestContext->setPathInfo('/swapp/all');
    $requestContext->setMethod('GET');
    $requestContext->setHost('swapper-swapps');
    $requestContext->setScheme('http');
    $requestContext->setHttpPort(8888);
    $requestContext->setHttpsPort(443);
    $requestContext->setQueryString('token_user_id=2&limit=7');
    $requestContext->setParameters([]);

    $matcher = new Routing\Matcher\UrlMatcher(
        $loader->load('routing.yml'),
        $requestContext
    );

   // die(print_r($matcher));

    try {



        $parameters = [];
        $parameters["token_user_id"] = "096654fb5de7fddc04d44cd67b8562390b8e3f05";
        $parameters["limit"] = "7";

        $parameters = $matcher->match($requestContext->getPathInfo());
        //die(print_r($parameters));

        foreach ($parameters as $key => $value) {
            $request->attributes->set($key, $value);
            // print_r($parameters);
            // echo "<br/>";
        }


        $command = "get" . "all";


        // die(print_r($request));
        $controller = $container->get('controller.' . "swapp");

        // $controller = $container->get('controller.' . $request->get('resource'));

        // die($controller->{$command}($request));
        // $data = $controller->{$command}($request);

        $controller->{$command}($request);
        $data = $controller->{$command}($request);

        return $data;

    } catch (\Exception $exception) {
        // TODO log data
        $data->setStatus(404);
        $data->setMessage('No routes found');
        // die(print_r($exception->getMessage()));

        echo "Message: ". $exception->getMessage() . ", on line " . $exception->getLine() . ", file: " . $exception->getFile();

    } catch (\TypeError $error) {
        // TODO log data
        $data->setStatus(404);
        $data->setMessage(new Response("Invalid dependency: {$error->getMessage()}"));
        // die(print_r(new Response("Invalid dependency: {$error->getMessage()}")));

        echo "invalid dependency:   {$error->getMessage()}";
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

    return $response;
}


/**
 * RPC response callback
 *
 * @param $request
 */
$callback = function ($request) {
    $jsonData = $request->body;

    $response = setResponse($jsonData);

    echo ' [.] Request from swapps ---->(', $jsonData, ")\n";

    $msg = new AMQPMessage(
        //(string)$jsonData,
        json_encode($response),
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