<?php
/**
 * Created by PhpStorm.
 * User: mirza
 * Date: 8/4/18
 * Time: 12:37 PM
 */

namespace Model\Service;

use Model\Entity\Message;
use Model\Entity\ResponseBootstrap;
use Model\Entity\Shared;
use Model\Mapper\MessageMapper;
use Model\Mapper\SwappMapper;
use Model\Service\Helper\SwappImageFacade;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\RequestContext;

class SwappService
{

    private $swappMapper;
    private $configuration;
    private $response;
    private $cashedUsers = [];
    private $messageMapper;

    public function __construct(SwappMapper $swappMapper, MessageMapper $messageMapper)
    {
        $this->messageMapper = $messageMapper;
        $this->swappMapper = $swappMapper;
        $this->configuration = $swappMapper->getConfiguration();
        $this->response = new ResponseBootstrap();
    }
    
    
    /**
     * Get My Swapps from access token
     * 
     * @param int $limit
     * @param int $last
     * @param int $from
     * @param string $token
     * @return ResponseBootstrap
     */
    public function getMySwaps(int $limit, int  $last = null, int $from = null, string $token):ResponseBootstrap
    {
        // get user it from token
        // $client = new \GuzzleHttp\Client();
        // $res = $client->get($this->configuration['users_url'] . '/users/handletoken?token='.$token);
        // user id
        // $userId = json_decode($res->getBody()->getContents(), true)['data']['user_id'];


        $urlQuery = [];
        if($from){
            $urlQuery = array_merge($urlQuery , ['from'=>$from]);
        }
        if($limit){
            $urlQuery = array_merge($urlQuery , ['limit'=>$limit]);
        }
        if($last){
            $urlQuery = array_merge($urlQuery , ['last'=>$last]);
        }

        // $token  = "mytoken";
        // call swapps
        // $message = (string)$this->configuration['users_url'] . '/users/handletoken?token='.$token;
        // $rpcMessage = new \RPCClient();
        // $rpcData = $rpcMessage->getResponse($message);

        // die(print_r(json_decode($rpcData)));

        // $client = new \GuzzleHttp\Client();
        // $res = $client->request('GET', $this->configuration['swap_url'] . '/swapp/all?&token_user_id='.$userId.'&'.http_build_query($urlQuery), []);


        // call swapps
        $message = (string)$this->configuration['swap_url'] . '/swapp/all?&token_user_id=2&limit=7';
        $rpcMessage = new \RPCClient();
        $rpcData = $rpcMessage->getResponse($message);


        // set data to variable
        // $data = $res->getBody()->getContents();
        // $data = json_decode($data,true)['data'];

        // form user
        // foreach($data as $key=>$swapp){
        //     if(!is_null($this->getUserInfo($swapp['user']))){
        //         $data[$key]['user'] = $this->getUserInfo($swapp['user']);
        //     }else{
        //         unset($data[$key]);
        //     }
        // }

        // set response
        if(200 == 200){
            $this->response->setStatus(200);
            $this->response->setMessage('Success');
            $this->response->setData([
                $rpcData
            ]);
        }
        // if no content
        else if($res->getStatusCode() == 204){
            $this->response->setStatus(204);
            $this->response->setMessage('No Content');
        }else {
            $this->response->setStatus(304);
            $this->response->setMessage('Not modified');
        }
        
        return $this->response;
    }


    /**
     * Get My Swapps from access token
     *
     * @param int $limit
     * @param int $last
     * @param int $from
     * @param string $token
     * @return ResponseBootstrap
     */
    public function getMySwapsSimple(string $token):ResponseBootstrap
    {
        // get user it from token
        $client = new \GuzzleHttp\Client();
        $res = $client->get($this->configuration['users_url'] . '/users/handletoken?token='.$token);
        // user id
        $userId = json_decode($res->getBody()->getContents(), true)['data']['user_id'];

        // call swapps
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', $this->configuration['swap_url'] . '/swapp/simple?user_id='.$userId, []);

        // set response
        if($res->getStatusCode() == 200){
            $this->response->setStatus(200);
            $this->response->setMessage('Success');
            $this->response->setData(json_decode($res->getBody()->getContents(), true)['data']);
        }
        // if no content
        else if($res->getStatusCode() == 204){
            $this->response->setStatus(204);
            $this->response->setMessage('No Content');
        }else {
            $this->response->setStatus(304);
            $this->response->setMessage('Not modified');
        }

        return $this->response;
    }


    /**
     * Create Swapp
     *
     * @param string $name
     * @param array $images
     * @param array $tags
     * @param array $swappingFor
     * @param string $description
     * @return ResponseBootstrap
     */
    public function createSwap(string $token, string $name, array $images, array $tags, array $swappingFor,string $description):ResponseBootstrap{

        // get user it from token
        $client = new \GuzzleHttp\Client();
        $res = $client->get($this->configuration['users_url'] . '/users/handletoken?token='.$token);
        // user id
        $userId = json_decode($res->getBody()->getContents(), true)['data']['user_id'];

        // request
        $client = new \GuzzleHttp\Client();
        $res = $client->post($this->configuration['swap_url'] . '/swapp/swapp',
        [
            \GuzzleHttp\RequestOptions::JSON => [
                'name' => $name,
                'images' => $images,
                'tags' => $tags,
                'swapping_for' => $swappingFor,
                'from_user' => (int)$userId,
                'description' => $description
            ]
        ]);

        $res = $res->getStatusCode();

        if($res == 200){
            $this->response->setStatus(200);
            $this->response->setMessage('Success');
        }else {
            $this->response->setStatus(304);
            $this->response->setMessage('Not modified');
        }

        // return response
        return $this->response;
    }


    /**
     * Edit Swapp
     *
     * @param string $token
     * @param int $id
     * @param string $name
     * @param array $images
     * @param array $tags
     * @param array $swappingFor
     * @param string $description
     * @return ResponseBootstrap
     */
    public function editSwap(string $token, int $id, string $name, array $images, array $tags, array $swappingFor,string $description):ResponseBootstrap{
        // get user it from token
        $client = new \GuzzleHttp\Client();
        $res = $client->get($this->configuration['users_url'] . '/users/handletoken?token='.$token);
        // user id
        $userId = json_decode($res->getBody()->getContents(), true)['data']['user_id'];

        // request
        $client = new \GuzzleHttp\Client();
        $res = $client->put($this->configuration['swap_url'] . '/swapp/swapp?id='.$id,
            [
                \GuzzleHttp\RequestOptions::JSON => [
                    'name' => $name,
                    'images' => $images,
                    'tags' => $tags,
                    'swapping_for' => $swappingFor,
                    'from_user' => (int)$userId,
                    'description' => $description
                ]
            ]);

        $res = $res->getStatusCode();

        if($res == 200){
            $this->response->setStatus(200);
            $this->response->setMessage('Success');
        }else {
            $this->response->setStatus(304);
            $this->response->setMessage('Not modified');
        }

        // return response
        return $this->response;
    }

    /**
     * Delete swap
     *
     * @param int $id
     * @return ResponseBootstrap
     */
    public function deleteSwap(string $token, int $id):ResponseBootstrap{
        // create response object
        $response = new ResponseBootstrap();

        // check authorization
//        $authController = new AuthHelper($token, $scope = 'all', $this->configuration);
//        $allowed = $authController->checkAuthorization();
        $allowed = 200; // DEMO

        if($allowed == 200){
            // create guzzle client and call MS for data
            $client = new \GuzzleHttp\Client();
            $res = $client->request('DELETE', $this->configuration['swap_url'] . '/swapp/swapp?id=' . $id, []);

            // set data to variable
            $res = $res->getStatusCode();

            // set response
            if($res == 200){
                $response->setStatus(200);
                $response->setMessage('Success');
            }else {
                $response->setStatus(304);
                $response->setMessage('Not modified');
            }
        }else {
            $response->setStatus(401);
            $response->setMessage('Bad credentials');
        }

        // return response
        return $response;
    }


    /**
     * Send acceptance
     *
     * @param int $id
     * @return ResponseBootstrap
     */
    public function sendSwapAcceptance(string $token, int $id):ResponseBootstrap {
        // create response object
        $response = new ResponseBootstrap();

        // check authorization
//        $authController = new AuthHelper($token, $scope = 'all', $this->configuration);
//        $allowed = $authController->checkAuthorization();
        $allowed = 200;

        if($allowed == 200){
            // create guzzle client and send it data
            $client = new \GuzzleHttp\Client();
            $res = $client->post($this->configuration['swap_url'] . '/swapp/acceptance',
                [
                    \GuzzleHttp\RequestOptions::JSON => [
                        'id' => $id
                    ]
                ]);

            // set data to variable
            $res = $res->getStatusCode();

            // set response
            if($res == 200){
                $response->setStatus(200);
                $response->setMessage('Success');
            }else {
                $response->setStatus(304);
                $response->setMessage('Not modified');
            }
        }else {
            $response->setStatus(401);
            $response->setMessage('Bad credentials');
        }

        // return response
        return $response;
    }


    /**
     * Change acceptance state
     *
     * @param int $id
     * @param string $state
     * @return ResponseBootstrap
     */
    public function changeSwapAcceptanceState(string $token, int $id, string $state):ResponseBootstrap {
        // create response object
        $response = new ResponseBootstrap();

        $allowed = 200;

        if($allowed == 200){
            // create guzzle client and send it data
            $client = new \GuzzleHttp\Client();
            $res = $client->put($this->configuration['swap_url'] . '/swapp/acceptance',
                [
                    \GuzzleHttp\RequestOptions::JSON => [
                        'id' => $id,
                        'state' => $state
                    ]
                ]);

            // set data to variable
            $res = $res->getStatusCode();

            // set response
            if($res == 200){
                $response->setStatus(200);
                $response->setMessage('Success');
            }else {
                $response->setStatus(304);
                $response->setMessage('Not modified');
            }

//            // send push notification
//            @$this->sendNotification(
//                trim($this->getUserFcm($toUserId)),
//                []);

        }else {
            $response->setStatus(401);
            $response->setMessage('Bad credentials');
        }

        // return response
        return $response;
    }


    /**
     * Send Swapp Request
     *
     * @param string $token
     * @param int $fromSwappId
     * @param int $toUserId
     * @param int $toSwappId
     * @return ResponseBootstrap
     */
    public function sendSwappRequest(string $token, int $fromSwappId, int $toUserId, int $toSwappId):ResponseBootstrap {

        // get user it from token
        $client = new \GuzzleHttp\Client();
        $res = $client->get($this->configuration['users_url'] . '/users/handletoken?token='.$token);
        // user id
        $userId = json_decode($res->getBody()->getContents(), true)['data']['user_id'];

        // create guzzle client and send it data
        $client = new \GuzzleHttp\Client();
        $res = $client->post($this->configuration['swap_url'] . '/swapp/request',
            [
                \GuzzleHttp\RequestOptions::JSON => [
                    'from_user_id' => $userId,
                    'from_swapp_id' => $fromSwappId,
                    'to_user_id'  => $toUserId,
                    'to_swapp_id' => $toSwappId
                ]
            ]);

        // set data to variable
        $res = $res->getStatusCode();

        // set response
        if($res == 200){
            $this->response->setStatus(200);
            $this->response->setMessage('Success');
        }else {
            $this->response->setStatus(304);
            $this->response->setMessage('Not modified');
        }

        // send push notification
        @$this->sendNotification(
            trim($this->getUserFcm($toUserId)),
            []);

        // return response
        return $this->response;
    }


    /**
     * Send Push Notificatino of Message bing sent
     *
     * @param string $fcm
     * @param array $message
     */
    public  function sendNotification(string $fcm , array $message)
    {
        // request
        $client = new \GuzzleHttp\Client();
        $client->post($this->configuration['notification_url'] . '/notification/send',
            [
                \GuzzleHttp\RequestOptions::JSON => [
                    'to' => $fcm,
                    'message' => [
                        "title" => "Karina swapp",
                        "text" => "Karina user"
                    ],
                    'data' => [
                        'type' => 'message',
                        'content' => $message
                    ]
                ]
            ]);
    }


    /**
     * Send Push Notification
     *
     * @param $toUser
     * @return string
     */
    public function getUserFcm($userId):string
    {
        $shared = new Shared();
        $messages = new Message();
        $messages->setToUser($userId);
        $this->messageMapper->getUserFcm($shared, $messages);

        return $shared->getFcm();
    }


    /**
     * Change request state
     *
     * @param int $id
     * @param string $state
     * @return ResponseBootstrap
     */
    public function editSwappRequest(string $token, int $id, string $state):ResponseBootstrap {
        // create response object
        $response = new ResponseBootstrap();

        // check authorization
//        $authController = new AuthHelper($token, $scope = 'all', $this->configuration);
//        $allowed = $authController->checkAuthorization();
        $allowed = 200; // DEMO

        if($allowed == 200){
            // create guzzle client and send it data
            $client = new \GuzzleHttp\Client();
            $res = $client->put($this->configuration['swap_url'] . '/swapp/request',
                [
                    \GuzzleHttp\RequestOptions::JSON => [
                        'id' => $id,
                        'state' => $state
                    ]
                ]);

            // set data to variable
            $res = $res->getStatusCode();

            // set response
            if($res == 200){
                $response->setStatus(200);
                $response->setMessage('Success');
            }else {
                $response->setStatus(304);
                $response->setMessage('Not modified');
            }
        }else {
            $response->setStatus(401);
            $response->setMessage('Bad credentials');
        }

        // return response
        return $response;
    }


    /**
     * Get swaps
     *
     * @param int $from
     * @param int $limit
     * @return ResponseBootstrap
     */
    public function getSwaps($token,$from = null,$limit = null, $last = null, $tags = null, $location = null, $range = null, $type = null,$userId = null, $state = null):ResponseBootstrap{
        // create response object
        $response = new ResponseBootstrap();

        $allowed = 200; // DEMO
        
        /**
         * Get User Id from Token
         * @var \GuzzleHttp\Client $client
         */
        $client = new \GuzzleHttp\Client();
        $res = $client->get($this->configuration['users_url'] . '/users/handletoken?token='.$token);
        // user id
        $tokenUserId = json_decode($res->getBody()->getContents(), true)['data']['user_id'];

        if($allowed == 200){
            
            $urlQuery = [];
            if($from){
                $urlQuery = array_merge($urlQuery , ['from'=>$from]);
            }
            if($limit){
                $urlQuery = array_merge($urlQuery , ['limit'=>$limit]);
            }
            if($last){
                $urlQuery = array_merge($urlQuery , ['last'=>$last]);
            }
            if($tags){
                $urlQuery = array_merge($urlQuery , ['tags'=>$tags]);
            }
            if($location){
                $urlQuery = array_merge($urlQuery , ['location'=>$location]);
            }
            if($range){
                $urlQuery = array_merge($urlQuery , ['range'=>$range]);
            }
            if($type){
                $urlQuery = array_merge($urlQuery , ['type'=>$type]);
            }
            if($userId){
                $urlQuery = array_merge($urlQuery , ['user_id'=>$userId]);
            }
            if($tokenUserId){
                $urlQuery = array_merge($urlQuery , ['token_user_id'=>$tokenUserId]);
            }
            if($state){
                $urlQuery = array_merge($urlQuery , ['state'=>$state]);
            }
            
            // create guzzle client and call MS for data
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $this->configuration['swap_url'] . '/swapp/all?' . http_build_query($urlQuery), []);

            // set data to variable
            $data = $res->getBody()->getContents();
            $data = json_decode($data,true)['data'];

            // if data empty
            if(!empty($data)){
                // form user
                foreach($data as $key=>$swapp){
                    if(!is_null($this->getUserInfo($swapp['user']))){
                        $data[$key]['user'] = $this->getUserInfo($swapp['user']);
                    }else{
                        unset($data[$key]);
                    }
                }
            }

            // set response
            if(!empty($data)){
                $response->setStatus(200);
                $response->setMessage('Success');
                $response->setData($data);
            }else {
                $response->setStatus(204);
                $response->setMessage('No content');
            }
        }else {
            $response->setStatus(401);
            $response->setMessage('Bad credentials');
        }

        // return response
        return $response;
    }


    /**
     * Get user swapps
     *
     * @param int $id
     * @return ResponseBootstrap
     */
    public function getUserSwapps(string $token, int $id):ResponseBootstrap{
        // create response object
        $response = new ResponseBootstrap();

        $allowed = 200; // DEMO

        if($allowed == 200){
            // create guzzle client and call MS for data
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $this->configuration['swap_url'] . '/swapp/swapps?id=' . $id, []);

            // set data to variable
            $data = $res->getBody()->getContents();

            // set response
            if(!empty($data)){
                $response->setStatus(200);
                $response->setMessage('Success');
                $response->setData([
                    'data' => json_decode($data)
                ]);
            }else {
                $response->setStatus(204);
                $response->setMessage('No content');
            }
        }else {
            $response->setStatus(401);
            $response->setMessage('Bad credentials');
        }

        // return response
        return $response;
    }


    /**
     * Search swaps
     *
     * @param string|null $like
     * @param string|null $category
     * @param string|null $condition
     * @param string|null $location
     * @return ResponseBootstrap
     */
    public function searchSwaps(string $token, string $like = null, string $category = null, string $condition = null, string $location = null):ResponseBootstrap{
        // create response object
        $response = new ResponseBootstrap();

        // check authorization
//        $authController = new AuthHelper($token, $scope = 'all', $this->configuration);
//        $allowed = $authController->checkAuthorization();
        $allowed = 200; // DEMO

        if($allowed == 200){
            // create guzzle client and call MS for data
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $this->configuration['swap_url'] . '/swapp/search?like=' . $like . '&category=' . $category . '&condition=' . $condition . '&location=' . $location, []);  // ??????

            // set data to variable
            $data = $res->getBody()->getContents();

            // set response
            if(!empty($data)){
                $response->setStatus(200);
                $response->setMessage('Success');
                $response->setData([
                    'data' => json_decode($data)
                ]);
            }else {
                $response->setStatus(204);
                $response->setMessage('No content');
            }
        }else {
            $response->setStatus(401);
            $response->setMessage('Bad credentials');
        }

        // return response
        return $response;
    }


    /**
     * Like swap
     *
     * @param int $id
     * @param int $userId
     * @return ResponseBootstrap
     */
    public function likeSwap(string $token, int $id):ResponseBootstrap {
        // create response object
        $response = new ResponseBootstrap();

        /**
         * Get User Id from Token
         * @var \GuzzleHttp\Client $client
         */
        $client = new \GuzzleHttp\Client();
        $res = $client->get($this->configuration['users_url'] . '/users/handletoken?token='.$token);
        // user id
        $userId = json_decode($res->getBody()->getContents(), true)['data']['user_id'];
        
        $allowed = 200; // DEMO

        if($allowed == 200){
            // create guzzle client and send it data
            $client = new \GuzzleHttp\Client();
            $res = $client->post($this->configuration['swap_url'] . '/swapp/like',
                [
                    \GuzzleHttp\RequestOptions::JSON => [
                        'id' => $id,
                        'user_id' => $userId
                    ]
                ]);

            // set data to variable
            $res = $res->getStatusCode();

            // set response
            if($res == 200){
                $response->setStatus(200);
                $response->setMessage('Success');
            }else {
                $response->setStatus(304);
                $response->setMessage('Not modified');
            }
        }else {
            $response->setStatus(401);
            $response->setMessage('Bad credentials');
        }

        // return response
        return $response;
    }


    /**
     * Upload image/s
     * @param UploadedFile $fileRaw
     * @return ResponseBootstrap
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function uploadImages($fileRaw): ResponseBootstrap {
        // create response object
        $response = new ResponseBootstrap();

        $allowed = 200; // DEMO

        if($allowed == 200){

            // create guzzle client and send it data
            $client = new \GuzzleHttp\Client();
//            $res = $client->post($this->configuration['system_url'] . '/upload',
//                [
//                    'form_params' => [
//                        'files' => "test"
//                    ]
//                ]
//            );

            $res = $client->request('POST', $this->configuration['system_url'] . '/upload', [
                'headers' => [
                    'Content-Type' => 'multipart/form-data',
                ],
                'multipart' => [
                    [
                        'name'     => 'test',
                        'contents' => fopen('https://images.pexels.com/photos/34950/pexels-photo.jpg?auto=compress&cs=tinysrgb&h=350', 'r'),
                    ]
                ],
            ]);


            die(print_r($res->getBody()->getContents()));


//
//            $res = $client->request('POST', $this->configuration['system_url'] . '/upload', [
//                'multipart' => [
//
//                    [
//                        'name'  => 'image',
//                        'contents' => fopen($fileRaw->getPathname(), 'r')
//                    ]
//                ]
//            ]);

            // set data to variable
            $res = $res->getStatusCode();

            // set response
            if($res == 200){
                $response->setStatus(200);
                $response->setMessage('Success');
            }else {
                $response->setStatus(304);
                $response->setMessage('Not modified');
            }

        }else {
            $response->setStatus(401);
            $response->setMessage('Bad credentials');
        }

        // return response
        return $response;
    }


    /**
     * Get User Info
     *
     * @param $id
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getUserInfo($id)
    {
        // if is not cahsed
        if(sizeof($this->cashedUsers) != 0){
            // check if cached
            foreach($this->cashedUsers as $cachedTemp){
                // if cashed
                if((int)$cachedTemp['user_id'] === (int)$id){
                    return $cachedTemp;
                }
                // if not cashed
                else{
                    $user = $this->getUserInfoRequest($id);
                    array_push($this->cashedUsers,$user);
                    return $user;
                }
            }
        }
        // request new user
        else{
            $user = $this->getUserInfoRequest($id);
            array_push($this->cashedUsers,$user);
            return $user;
        }


    }


    /**
     * Get user Info Request
     *
     * @param $id
     * @return 
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getUserInfoRequest($id)
    {
        // create guzzle client and call MS for data
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', $this->configuration['users_url'] . '/users/profile?id=' . $id, []);
        $data = $res->getBody()->getContents();

        $data = json_decode($data, true)['data'];

        return $data;
    }

}