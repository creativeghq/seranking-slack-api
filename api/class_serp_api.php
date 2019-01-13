<?php
class class_serp_api
{

    private $url  = 'https://api2.seranking.com/';
    public $token = '';

    public function __construct($login, $pass)
    {
        $array    = array('login' => $login, 'pass' => md5($pass));
        $response = $this->sendRequest('login', $array);

        if (isset($response['token']) && $response['token']) {
            $this->token = $response['token'];
        }
    }

    public function getSiteKeywords($siteId)
    {
        return $this->sendRequest('siteKeywords', array('siteid' => $siteId, 'token' => $this->token));
    }

    public function getAllSites()
    {
        return $this->sendRequest('sites', array('token' => $this->token));
    }

    public function getSearchEngines()
    {
        $method   = 'searchEngines';
        $params   = array('token' => $this->token);
        $params   = http_build_query($params);
        $response = file_get_contents($this->url . '?method=' . $method . '&' . $params);
        // print_r($response);die;
        return $response;

    }

    public function getSiteStats($siteId)
    {
        $dateStart = date('Y-m-d', strtotime(date('Y-m-d') . ' -1 day'));
        $dateEnd   = date('Y-m-d');
        $params    = array('dateStart' => $dateStart, 'dateEnd' => $dateEnd, 'siteid' => $siteId, 'token' => $this->token);
        $params    = http_build_query($params);
        $method    = 'stat';
        $response  = file_get_contents($this->url . '?method=' . $method . '&' . $params);
        return $response;
    }

    public function getSiteId($sites)
    {
        $current_site = $_SERVER['HTTP_HOST'];
        if ($sites) {
            foreach ($sites as $site) {
                if ($site['name'] == $current_site) {
                    return $site['id'];
                }
            }
        }
        return false;

    }

    public function postToSlack($hook_url, $message, $channel, $username = '')
    {
        $array = array("text" => $message, 'channel' => $channel, 'username' => $username);

        $data = wp_remote_post($hook_url, array(
            'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
            'body'    => json_encode($array),
            'method'  => 'POST',
        ));
    }

    public function sendRequest($method, $params)
    {
        $params = http_build_query($params);
        try {
            $response = wp_remote_get($this->url . '?method=' . $method . '&' . $params);
        } catch (Exception $e) {
            print_r($e);die;
        }
        return json_decode($response['body'], true);
    }

}
