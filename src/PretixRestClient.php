<?php

namespace Jcpw;

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;


class PretixRestClient extends Client {

    private $env;
    private $env_path;
    private $env_name = null;
    private $base_uri;
    private $accesstoken;
    private $http_options = [];
    public $organizer_id;

    function __construct($base_uri, $organizer_id, $env_path) {

        $this->env_path = $env_path;
        $this->base_uri = $base_uri;
        $this->organizer_id = $organizer_id;

        $config = [
            'base_uri' => $this->base_uri,
            'headers' => [
                'Accept' => '*/*',
                'Connection' => 'keep-alive',
                'Accept-Encoding' => 'gzip, deflate, br',
            ]
        ];

        parent::__construct($config);

    }

    public function setEnvName($env_name) {
        $this->env_name = $env_name;
    }

    public function getEvent($event_id) {
        $this->findToken();

        try {
            $response = $this->get('organizers/' . $this->organizer_id .  '/events/' . $event_id, $this->http_options);
        } catch (RequestException $req_exce) {
            return false;
        }

        if($response->getStatusCode() < 400) {
            return $response->getHeader('Content-Type')[0] == 'application/json' ? json_decode($response->getBody()) : $response->getBody();
        }

        return false;
    }

    public function getEvents($nexturl = null) {
        $this->findToken();

        try {
            if($nexturl) {
                $response = $this->get($nexturl, $this->http_options);
            } else {
                $response = $this->get('organizers/' . $this->organizer_id .  '/events/', $this->http_options);
            }
        } catch (RequestException $req_exce) {
            return false;
        }

        if($response->getStatusCode() < 400) {
            return $response->getHeader('Content-Type')[0] == 'application/json' ? json_decode($response->getBody()) : $response->getBody();
        }

        return false;
    }

    public function getAllEvents() {

        $response = $this->getEvents();

        $cumulative = $response->results;

        while($response->next) {

            $response = $this->getEvents($response->next);
            $cumulative = array_merge($cumulative, $response->results);
        }

        return $cumulative;

    }

    private function findToken()
    {

        $this->env = Dotenv::createArrayBacked($this->env_path, $this->env_name)->load();

        if (array_key_exists(strtoupper($this->organizer_id) . '_ACCESSTOKEN', $this->env)) {
            $this->accesstoken = $this->env[strtoupper($this->organizer_id) . '_ACCESSTOKEN'];
            $this->http_options['headers'] = ['Authorization' => 'Token ' . $this->accesstoken];

        } else {
            throw new NoOrganizerException("No accestoken found for organizer " . $this->organizer_id);
        }


    }

    public function getOrder($event_id, $ordercode) {
        $this->findToken();

        try {
            $response = $this->get('organizers/' . $this->organizer_id .  '/events/' . $event_id . '/orders/' . $ordercode , $this->http_options);
        } catch (RequestException $req_exce) {
            return false;
        }

        if($response->getStatusCode() < 400) {
            return $response->getHeader('Content-Type')[0] == 'application/json' ? json_decode($response->getBody()) : $response->getBody();
        }

        return false;

    }

    public function getOrderposition($event_id, $orderposition_id) {
        $this->findToken();

        try {
            $response = $this->get('organizers/' . $this->organizer_id .  '/events/' . $event_id . '/orderpositions/' . $orderposition_id , $this->http_options);

        } catch (RequestException $req_exce) {

            return false;
        }


        if($response->getStatusCode() < 400) {
            return $response->getHeader('Content-Type')[0] == 'application/json' ? json_decode($response->getBody()) : $response->getBody();
        }

        return false;

    }
    
    public function getCertificateOfAttendance($event_id , $orderposition_id) {
        $this->findToken();
        $this->http_options['allow_redirects'] = false;

        try {

            $response = $this->get("/api/v1/organizers/"  . $this->organizer_id .  "/events/" . $event_id . "/orderpositions/" . $orderposition_id . "/certificate/",  $this->http_options);
        } catch (RequestException $re) {
             return false;
        }

        if($response->getStatusCode() == 303 && $response->getHeaderLine("Location")) {

            $this->http_options['http_errors'] = false;

            for($tries = 0; $tries < 3; $tries++) {

                $certifacteResponse = $this->get($response->getHeaderLine("Location"), $this->http_options);

                if($certifacteResponse->getStatusCode() == 200) {
                    $this->http_options['allow_redirects'] = true;
                    $this->http_options['http_errors'] = true;

                    return $certifacteResponse;
                } else if($certifacteResponse->getStatusCode() != 409) {
                    break;
                }

                sleep(2);
            }
        }

        $this->http_options['allow_redirects'] = true;
        $this->http_options['http_errors'] = true;
        return false;
    }

}