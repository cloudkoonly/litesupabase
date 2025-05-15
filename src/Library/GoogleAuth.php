<?php

namespace Litesupabase\Library;

use Google\Service\Exception;
use Google_Client;
use Google_Service_Oauth2;

class GoogleAuth
{
    public string $clientID;
    public string $clientSecret;
    public string $redirectUri;
    public function __construct($clientID,$clientSecret,$redirectUri)
    {
        $this->clientID = $clientID;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
    }

    public function getClient(): Google_Client
    {
        // create Client Request to access Google API
        $client = new Google_Client();
        $client->setClientId($this->clientID);
        $client->setClientSecret($this->clientSecret);
        $client->setRedirectUri($this->redirectUri);
        $client->addScope("email");
        $client->addScope("profile");
        return $client;
    }

    /**
     * @throws Exception
     */
    public function getGoogleAccountInfo($code): array
    {
        if ($code) {
            $client = $this->getClient();
            $token = $client->fetchAccessTokenWithAuthCode($code);
            $client->setAccessToken($token['access_token']);

            $google_oauth = new Google_Service_Oauth2($client);
            $google_account_info = $google_oauth->userinfo->get();
            //email,name,gender,picture(pic url)
            $id = $google_account_info->id;
            $email =  $google_account_info->email;
            $name =  $google_account_info->name;
            $gender =  $google_account_info->gender;
            $picture =  $google_account_info->picture;
            return [$id, $email, $name, $gender, $picture];
        }
        return ['','','','',''];
    }

    public function getAuthUrl(): string
    {
        $client = $this->getClient();
        return $client->createAuthUrl();
    }
}